<?php

namespace DavidOghi\CertificateGeneration\Services;

use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use DavidOghi\CertificateGeneration\Contracts\CertificateNumberGenerator;
use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use DavidOghi\CertificateGeneration\Contracts\VerificationUrlGenerator;
use DavidOghi\CertificateGeneration\Events\CertificateIssued;
use DavidOghi\CertificateGeneration\Models\CertificateTemplate;
use DavidOghi\CertificateGeneration\Models\IssuedCertificate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;
use RuntimeException;
use Throwable;

class CertificateManager
{
    public function version(): string
    {
        return (string) config('certificates.ui.package_version', '1.0.0');
    }

    public function __construct(
        private CertificateStorage $storage,
        private CertificateContext $context,
        private CertificateScope $scope,
        private CertificateNumberGenerator $numberGenerator,
        private VerificationUrlGenerator $verificationUrls,
    ) {}

    public function create(array $data, Authenticatable $actor): CertificateTemplate
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->authorizeActor($actor);
            $data = $this->normalizeIncomingData($data);
            $this->validatePayload($data, null, $actor);

            $background = $this->resolveUploadedOrExistingTemplatePath($data);

            $template = $this->templateQuery()->create([
                ...$this->scope->attributes($actor),
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->generateUniqueSlug($data['name'], scopeSubject: $actor),
                'description' => $data['description'] ?? null,
                'certificate_template' => $background,
                'settings' => $this->normalizeSettings($data['settings'] ?? null),
                'supported_modules' => $this->normalizeSupportedModules($data['supported_modules'] ?? null),
                'status' => (bool) ($data['status'] ?? true),
                'created_by' => $actor->getAuthIdentifier(),
            ]);

            return $template->refresh();
        });
    }

    public function update(CertificateTemplate $template, array $data, Authenticatable $actor): CertificateTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor) {
            $this->authorizeActor($actor);
            $this->assertOwnership($template, $actor);
            $data = $this->normalizeIncomingData($data);
            $this->validatePayload($data, $template, $template);

            $newBackground = $this->resolveUploadedOrExistingTemplatePath($data, $template);

            $oldBackground = $template->certificate_template;

            $template->update([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->generateUniqueSlug($data['name'], $template->id, $template),
                'description' => $data['description'] ?? null,
                'certificate_template' => $newBackground,
                'settings' => $this->normalizeSettings($data['settings'] ?? null),
                'supported_modules' => $this->normalizeSupportedModules($data['supported_modules'] ?? null),
                'status' => (bool) ($data['status'] ?? true),
            ]);

            if ($oldBackground && $newBackground !== $oldBackground) {
                $this->deleteTemplateBackground($oldBackground);
            }

            return $template->refresh();
        });
    }

    public function delete(CertificateTemplate $template, Authenticatable $actor): void
    {
        $this->authorizeActor($actor);
        $this->assertOwnership($template, $actor);

        DB::transaction(function () use ($template) {
            $background = $template->certificate_template;
            $issued = $template->issuedCertificates()->get();
            $policy = config('certificates.templates.delete_policy', 'restrict');

            if ($issued->isNotEmpty() && $policy !== 'cascade') {
                throw ValidationException::withMessages([
                    'certificate_template' => 'This template has issued certificates and cannot be deleted under the configured deletion policy.',
                ]);
            }

            if ($policy === 'cascade') {
                $issued->each(fn (IssuedCertificate $certificate) => $this->storage->delete($certificate->file_path));
                $template->issuedCertificates()->delete();
            }

            $template->delete();
            $this->deleteTemplateBackground($background);
        });
    }

    public function duplicate(CertificateTemplate $template, Authenticatable $actor): CertificateTemplate
    {
        return DB::transaction(function () use ($template, $actor) {
            $this->authorizeActor($actor);
            $this->assertOwnership($template, $actor);

            $copyBackground = $this->duplicateTemplateBackground($template);

            return $this->templateQuery()->create([
                ...$this->scope->attributes($template),
                'name' => $template->name.' Copy',
                'slug' => $this->generateUniqueSlug($template->name.' copy', scopeSubject: $template),
                'description' => $template->description,
                'certificate_template' => $copyBackground,
                'settings' => $template->settings,
                'supported_modules' => $template->supported_modules,
                'status' => $template->status,
                'created_by' => $actor->getAuthIdentifier(),
            ]);
        });
    }

    public function preview(array $data, ?CertificateTemplate $template = null): array
    {
        $scopeSubject = $template ?? $this->context->actor();
        $scopeKey = $this->scope->key($scopeSubject);
        $merged = $this->normalizeIncomingData($data);
        $previewKey = $this->resolvePreviewKey($merged, $template);

        if ($template) {
            if ($actor = $this->context->actor()) {
                $this->assertOwnership($template, $actor);
            }

            $merged['supported_modules'] = $template->supported_modules;
            $background = $this->resolveUploadedOrExistingTemplatePath($merged, $template);
            $settings = $this->normalizeSettings($merged['settings'] ?? $template->settings);
        } else {
            $background = $this->resolveUploadedOrExistingTemplatePath($merged, null);
            $settings = $this->normalizeSettings($merged['settings'] ?? null);
        }

        $this->validateSettings($settings);

        $previewDirectory = $this->createPreviewDirectory($scopeSubject, $previewKey);
        $certificateNumber = $this->generateCertificateNumber($scopeKey);
        $sample = $this->buildSampleData($merged['supported_modules'] ?? null);

        $rendered = $this->render(
            template: $template ?? $this->newTemplate([
                ...$this->scope->attributes($scopeSubject),
                'certificate_template' => $background,
                'settings' => $settings,
                'supported_modules' => $merged['supported_modules'] ?? null,
                'status' => true,
            ]),
            data: $sample,
            outputDirectory: $previewDirectory,
            certificateNumber: $certificateNumber,
        );

        return [
            ...$rendered,
            'preview_url' => route($this->routeName('preview-file'), ['token' => Crypt::encryptString($rendered['relative_path'])]),
        ];
    }

    public function render(
        CertificateTemplate $template,
        array $data,
        string $outputDirectory,
        ?string $certificateNumber = null
    ): array {
        $background = $this->resolveTemplatePath($template);
        if (! is_file($background)) {
            throw new RuntimeException('The certificate background file could not be found.');
        }

        $settings = $this->normalizeSettings($template->settings ?? []);
        $this->validateSettings($settings);

        $image = Image::make($background);
        $canvas = $settings['canvas'] ?? [];
        $canvasWidth = max(1, (int) ($canvas['width'] ?? $image->width()));
        $canvasHeight = max(1, (int) ($canvas['height'] ?? $image->height()));

        if ($image->width() !== $canvasWidth || $image->height() !== $canvasHeight) {
            $image->resize($canvasWidth, $canvasHeight);
        }

        $elements = $this->normalizeElements($settings['elements'] ?? []);
        $verificationUrl = $this->verificationUrls->generate($certificateNumber);

        foreach ($elements as $element) {
            $this->renderElement($image, $element, $data, $verificationUrl);
        }

        if (! is_dir($outputDirectory)) {
            $outputDirectory = $this->storage->ensureDirectory($outputDirectory);
        }
        $format = strtolower((string) config('certificates.rendering.format', 'jpg'));
        $format = in_array($format, ['png', 'jpg', 'jpeg', 'webp'], true) ? $format : 'png';
        $filename = ($certificateNumber ?: 'preview').'.'.$format;
        $absoluteOutputPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        $image->save($absoluteOutputPath);

        return [
            'name' => $filename,
            'certificate_number' => $certificateNumber,
            'output_path' => $absoluteOutputPath,
            'relative_path' => $this->storage->relativePath($absoluteOutputPath),
            'date_issued' => now(),
        ];
    }

    public function issue(
        CertificateTemplate $template,
        Model $recipient,
        string $sourceType,
        int $sourceId,
        int $sourceRecordId,
        array $data,
        ?Authenticatable $issuer = null
    ): IssuedCertificate {
        return DB::transaction(function () use ($template, $recipient, $sourceType, $sourceId, $sourceRecordId, $data, $issuer) {
            $this->assertOwnership($template, $recipient);
            $scopeKey = $this->scope->key($template);
            if (! $template->status) {
                throw ValidationException::withMessages(['certificate_template' => 'The selected certificate template is inactive.']);
            }

            if (! $template->supportsModule($sourceType)) {
                throw ValidationException::withMessages(['certificate_template' => 'The selected certificate template does not support this certificate type.']);
            }

            $existing = $this->scope->apply($this->issuedQuery(), $template)
                ->where('source_type', $sourceType)
                ->where('source_record_id', $sourceRecordId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $certificateNumber = $this->generateCertificateNumber($scopeKey);
            $data = $this->mergeDefaultData($data, $recipient, $template, $sourceType);
            $data['certificate_number'] = $certificateNumber;
            $data['date_issued'] = now()->format(config('certificates.rendering.issued_date_format', 'jS \d\a\y \o\f F, Y'));

            $rendered = $this->render(
                template: $template,
                data: $data,
                outputDirectory: $this->createIssuedCertificateDirectory($template),
                certificateNumber: $certificateNumber,
            );

            $certificate = $this->issuedQuery()->create([
                ...$this->scope->attributes($template),
                'certificate_template_id' => $template->id,
                'user_id' => $recipient->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_record_id' => $sourceRecordId,
                'certificate_number' => $certificateNumber,
                'file_path' => $rendered['relative_path'],
                'data_snapshot' => $this->createDataSnapshot($data),
                'template_snapshot' => $this->createTemplateSnapshot($template),
                'issued_at' => $rendered['date_issued'],
                'issued_by' => $issuer?->getAuthIdentifier(),
            ]);

            event(new CertificateIssued($certificate));

            return $certificate;
        });
    }

    public function regenerate(IssuedCertificate $certificate, ?Authenticatable $actor = null): IssuedCertificate
    {
        return DB::transaction(function () use ($certificate, $actor) {
            $template = $certificate->template()->firstOrFail();
            if ($actor) {
                $this->authorizeActor($actor);
                $this->assertOwnership($template, $actor);
            }

            $recipient = $certificate->recipient()->firstOrFail();
            $data = $certificate->data_snapshot ?? [];
            $data['certificate_number'] = $certificate->certificate_number;

            $rendered = $this->render(
                template: $template,
                data: $data,
                outputDirectory: $this->createIssuedCertificateDirectory($certificate),
                certificateNumber: $certificate->certificate_number,
            );

            $certificate->update([
                'file_path' => $rendered['relative_path'],
                'template_snapshot' => $this->createTemplateSnapshot($template),
                'data_snapshot' => $this->createDataSnapshot($data),
                'issued_at' => $rendered['date_issued'],
            ]);

            return $certificate->fresh();
        });
    }

    public function certificateFontType(): array
    {
        return collect(config('certificates.fonts', []))
            ->mapWithKeys(fn (array|string $font, string $key) => [$key => is_array($font) ? ($font['label'] ?? $key) : $font])
            ->all();
    }

    public function supportedModules(): array
    {
        return config('certificates.modules', []);
    }

    private function normalizeIncomingData(array $data): array
    {
        $data['settings'] = $data['settings'] ?? null;
        $data['supported_modules'] = $data['supported_modules'] ?? null;
        $data['status'] = ! empty($data['status']);

        return $data;
    }

    private function validatePayload(array $data, ?CertificateTemplate $template, Model|Authenticatable|null $scopeSubject): void
    {
        if (blank($data['name'] ?? null)) {
            throw ValidationException::withMessages(['name' => 'The certificate name is required.']);
        }

        if (! $template && empty($data['certificate_template']) && empty($data['certificate_template_upload'])) {
            throw ValidationException::withMessages(['certificate_template' => 'The certificate background image is required.']);
        }

        $settings = $this->normalizeSettings($data['settings'] ?? null);
        $this->validateSettings($settings);

        $selectedModules = $this->normalizeSupportedModules($data['supported_modules'] ?? null) ?? [];
        $unknownModules = array_diff($selectedModules, array_keys($this->supportedModules()));
        if ($unknownModules !== []) {
            throw ValidationException::withMessages([
                'supported_modules' => 'One or more selected certificate modules are not configured by the host application.',
            ]);
        }

        if ($template) {
            $duplicateExists = $this->scope->apply($this->templateQuery(), $scopeSubject)
                ->where('name', $data['name'])
                ->whereKeyNot($template->id)
                ->exists();
        } else {
            $duplicateExists = $this->scope->apply($this->templateQuery(), $scopeSubject)
                ->where('name', $data['name'])
                ->exists();
        }

        if ($duplicateExists) {
            throw ValidationException::withMessages(['name' => 'A certificate template with this name already exists in the current certificate scope.']);
        }
    }

    private function normalizeSettings(array|string|null $settings): array
    {
        if (is_string($settings)) {
            try {
                $settings = json_decode($settings, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $throwable) {
                throw ValidationException::withMessages([
                    'settings' => 'The certificate settings JSON is invalid.',
                ]);
            }
        }

        $settings ??= [];

        if (array_is_list($settings)) {
            return [
                'canvas' => config('certificates.designer.canvas', ['width' => 1123, 'height' => 794, 'orientation' => 'landscape']),
                'elements' => $settings,
            ];
        }

        return [
            'canvas' => $settings['canvas'] ?? config('certificates.designer.canvas', ['width' => 1123, 'height' => 794, 'orientation' => 'landscape']),
            'elements' => $settings['elements'] ?? [],
        ];
    }

    private function validateSettings(array $settings): void
    {
        $canvas = $settings['canvas'] ?? [];
        $width = (int) ($canvas['width'] ?? 0);
        $height = (int) ($canvas['height'] ?? 0);

        if ($width < 1 || $height < 1) {
            throw ValidationException::withMessages(['settings' => 'The certificate canvas dimensions are invalid.']);
        }

        foreach (($settings['elements'] ?? []) as $index => $element) {
            if (! is_array($element)) {
                throw ValidationException::withMessages(["settings.elements.$index" => 'Invalid element configuration.']);
            }

            if (empty($element['text_type'])) {
                throw ValidationException::withMessages(["settings.elements.$index.text_type" => 'Each element must have a text type.']);
            }

            $allowedTextTypes = $this->allowedTextTypes();
            if ($allowedTextTypes !== [] && ! in_array($element['text_type'], $allowedTextTypes, true)) {
                throw ValidationException::withMessages(["settings.elements.$index.text_type" => 'The selected certificate element type is invalid.']);
            }

            $fontFace = $element['font'] ?? $element['text_type_face'] ?? null;
            if (! empty($fontFace)) {
                $font = basename((string) $fontFace);
                if ($font !== $fontFace || ! in_array($font, array_keys($this->certificateFontType()), true)) {
                    throw ValidationException::withMessages(["settings.elements.$index.font" => 'The selected font is not supported.']);
                }
            }
        }
    }

    private function normalizeElements(array $elements): array
    {
        return array_values(array_map(function ($element) {
            $element['id'] = $element['id'] ?? 'element_'.Str::uuid();
            $element['text_type'] = $element['text_type'] ?? 'custom_text';
            $element['label'] = $element['label'] ?? Str::headline(str_replace('_', ' ', $element['text_type']));
            $font = $element['font'] ?? $element['text_type_face'] ?? array_key_first($this->certificateFontType());
            $element['font'] = basename((string) $font);
            $element['text_type_face'] = $element['font'];
            $element['color'] = $element['color'] ?? $element['auto_certificate_color'] ?? '#000000';
            $element['auto_certificate_color'] = $element['color'];
            $element['top'] = (int) ($element['top'] ?? $element['auto_certificate_top_offset'] ?? 0);
            $element['left'] = (int) ($element['left'] ?? $element['auto_certificate_left_offset'] ?? 0);
            $element['auto_certificate_top_offset'] = $element['top'];
            $element['auto_certificate_left_offset'] = $element['left'];
            $element['font_size'] = (int) ($element['font_size'] ?? $element['auto_certificate_name_font_size'] ?? 24);
            $element['auto_certificate_name_font_size'] = $element['font_size'];
            $element['font_weight'] = (int) ($element['font_weight'] ?? $element['auto_certificate_name_font_weight'] ?? 400);
            $element['auto_certificate_name_font_weight'] = $element['font_weight'];
            $element['width'] = (int) ($element['width'] ?? 0);
            $element['height'] = (int) ($element['height'] ?? 0);
            $element['size'] = (int) ($element['size'] ?? max($element['width'], $element['height'], 120));
            $element['align'] = $element['align'] ?? $element['text_align'] ?? 'left';
            $element['text_align'] = $element['align'];
            $element['sample_text'] = $element['sample_text'] ?? null;
            $element['custom_text'] = $element['custom_text'] ?? null;
            $element['visible'] = (bool) ($element['visible'] ?? true);
            $element['opacity'] = (float) ($element['opacity'] ?? 1);
            if ($element['opacity'] > 1) {
                $element['opacity'] = max(0, min(100, (float) $element['opacity'])) / 100;
            }
            $element['rotation'] = (float) ($element['rotation'] ?? 0);
            $element['bold'] = (bool) ($element['bold'] ?? false);
            $element['italic'] = (bool) ($element['italic'] ?? false);
            $element['uppercase'] = (bool) ($element['uppercase'] ?? false);
            $element['line_height'] = (float) ($element['line_height'] ?? 1.2);
            $element['letter_spacing'] = (float) ($element['letter_spacing'] ?? 0);
            $element['z_index'] = (int) ($element['z_index'] ?? 1);
            $element['locked'] = (bool) ($element['locked'] ?? false);

            return $element;
        }, $elements));
    }

    private function resolveTemplatePath(CertificateTemplate|string $template): string
    {
        $path = is_string($template) ? $template : ($template->certificate_template ?? '');

        if (blank($path)) {
            throw new RuntimeException('The certificate template background is missing.');
        }

        if (is_file($path)) {
            return $path;
        }

        return $this->storage->absolutePath($path);
    }

    private function resolveUploadedOrExistingTemplatePath(array $data, ?CertificateTemplate $template = null): string
    {
        $upload = $data['certificate_template_upload'] ?? $data['certificate_template'] ?? null;

        if ($upload instanceof UploadedFile) {
            return $this->uploadTemplateBackground($upload, $template ?? $this->context->actor());
        }

        if ($template?->certificate_template) {
            return $template->certificate_template;
        }

        if (is_string($upload) && filled($upload)) {
            return $upload;
        }

        throw ValidationException::withMessages(['certificate_template' => 'The certificate background image is required.']);
    }

    private function uploadTemplateBackground(UploadedFile $file, Model|Authenticatable|null $scopeSubject): string
    {
        return $this->storage->storeUpload(
            $file,
            trim(config('certificates.storage.template_directory'), '/').'/'.$this->scope->pathSegment($scopeSubject),
        );
    }

    private function duplicateTemplateBackground(CertificateTemplate $template): ?string
    {
        if (! $template->certificate_template) {
            return null;
        }

        if (! $this->storage->exists($template->certificate_template)) {
            return $template->certificate_template;
        }

        $directory = trim(config('certificates.storage.template_directory'), '/').'/'.$this->scope->pathSegment($template);
        $filename = 'template-copy-'.Str::random(12).'.'.pathinfo($template->certificate_template, PATHINFO_EXTENSION);

        return $this->storage->copy($template->certificate_template, $directory, $filename);
    }

    private function deleteTemplateBackground(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $this->storage->delete($path);
    }

    private function normalizeHexColor(?string $value): string
    {
        $hex = strtolower(trim((string) $value));

        if ($hex === '') {
            return '#000000';
        }

        if (! str_starts_with($hex, '#')) {
            $hex = '#'.$hex;
        }

        if (preg_match('/^#([0-9a-f]{3})$/i', $hex, $matches)) {
            return '#'.implode('', array_map(
                static fn (string $char): string => $char.$char,
                str_split($matches[1], 1)
            ));
        }

        if (preg_match('/^#([0-9a-f]{6})$/i', $hex)) {
            return $hex;
        }

        return '#000000';
    }

    private function renderElement($image, array $element, array $data, string $verificationUrl): void
    {
        if (($element['text_type'] ?? '') === 'qr_code') {
            $this->renderQrCodeElement($image, $element, $verificationUrl);

            return;
        }

        $text = $this->resolveElementText($element, $data);
        if ($text === null || $text === '') {
            return;
        }

        $this->renderTextElement($image, $element, $text);
    }

    private function renderTextElement($image, array $element, string $text): void
    {
        $fontFile = $this->resolveFontPath($element['text_type_face'] ?? null);
        $x = (int) ($element['left'] ?? $element['auto_certificate_left_offset'] ?? 0);
        $y = (int) ($element['top'] ?? $element['auto_certificate_top_offset'] ?? 0);
        $width = (int) ($element['width'] ?? 0);
        $size = (float) ($element['font_size'] ?? $element['auto_certificate_name_font_size'] ?? 24);
        $color = $this->normalizeHexColor($element['color'] ?? $element['auto_certificate_color'] ?? '#000000');
        $align = $element['align'] ?? $element['text_align'] ?? 'left';
        $rotation = (float) ($element['rotation'] ?? 0);
        $text = (string) $text;

        if (! empty($element['uppercase'])) {
            $text = mb_strtoupper($text);
        }

        if ($width > 0) {
            $text = $this->wrapTextToWidth($text, $fontFile, $size, $width);
        }

        $image->text($text, $x, $y, function ($font) use ($fontFile, $size, $color, $align, $rotation, $width) {
            $font->file($fontFile);
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign('top');
            $font->angle($rotation);
        });
    }

    private function wrapTextToWidth(string $text, string $fontFile, float $size, int $maxWidth): string
    {
        if ($maxWidth <= 0 || ! function_exists('imagettfbbox') || ! is_file($fontFile)) {
            return $text;
        }

        $text = preg_replace('/[ \t]+/u', ' ', trim($text)) ?? $text;
        $paragraphs = preg_split("/\R/u", $text) ?: [$text];
        $wrapped = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                $wrapped[] = '';
                continue;
            }

            $lines = [];
            $currentLine = '';
            $words = preg_split('/\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [$paragraph];

            foreach ($words as $word) {
                $candidate = $currentLine === '' ? $word : $currentLine.' '.$word;

                if ($this->estimateTextWidth($candidate, $fontFile, $size) <= $maxWidth) {
                    $currentLine = $candidate;
                    continue;
                }

                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = '';
                }

                if ($this->estimateTextWidth($word, $fontFile, $size) <= $maxWidth) {
                    $currentLine = $word;
                    continue;
                }

                $segments = $this->breakWordToWidth($word, $fontFile, $size, $maxWidth);
                $lastIndex = count($segments) - 1;

                foreach ($segments as $index => $segment) {
                    if ($index === $lastIndex) {
                        $currentLine = $segment;
                    } else {
                        $lines[] = $segment;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }

            $wrapped[] = implode("\n", $lines);
        }

        return implode("\n", $wrapped);
    }

    private function breakWordToWidth(string $word, string $fontFile, float $size, int $maxWidth): array
    {
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [$word];
        $segments = [];
        $segment = '';

        foreach ($characters as $character) {
            $candidate = $segment.$character;

            if ($segment !== '' && $this->estimateTextWidth($candidate, $fontFile, $size) > $maxWidth) {
                $segments[] = $segment;
                $segment = $character;
                continue;
            }

            $segment = $candidate;
        }

        if ($segment !== '') {
            $segments[] = $segment;
        }

        return $segments;
    }

    private function estimateTextWidth(string $text, string $fontFile, float $size): int
    {
        $bbox = imagettfbbox($size, 0, $fontFile, $text);

        if (! is_array($bbox)) {
            return 0;
        }

        $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];

        return (int) (max($xs) - min($xs));
    }

    private function renderQrCodeElement($image, array $element, string $verificationUrl): void
    {
        $size = max(60, (int) ($element['size'] ?? $element['width'] ?? $element['height'] ?? 120));
        $offsetX = (int) ($element['left'] ?? $element['auto_certificate_left_offset'] ?? 0);
        $offsetY = (int) ($element['top'] ?? $element['auto_certificate_top_offset'] ?? 0);

        $qrCodeData = \QrCode::format('png')
            ->size($size)
            ->margin(0)
            ->backgroundColor(255, 255, 255, 0)
            ->generate($verificationUrl);

        $base64 = 'data:image/png;base64,'.base64_encode($qrCodeData);
        $qrImage = Image::make($base64);

        $image->insert($qrImage, 'top-left', $offsetX, $offsetY);
    }

    private function resolveElementText(array $element, array $data): ?string
    {
        $textType = $element['text_type'] ?? null;

        if (! $textType) {
            return null;
        }

        if ($textType === 'custom_text') {
            return $element['custom_text']
                ?? $element['content']
                ?? $element['sample_text']
                ?? $element['label']
                ?? null;
        }

        $aliases = [
            'staffID' => 'staffID',
            'date_issued' => 'date_issued',
            'name' => 'name',
            'email' => 'email',
            'organisation_name' => 'organisation_name',
            'organization_name' => 'organisation_name',
            'organisation_address' => 'organisation_address',
            'organization_address' => 'organisation_address',
            'organisation_phone' => 'organisation_phone',
            'organization_phone' => 'organisation_phone',
            'organisation_email' => 'organisation_email',
            'organization_email' => 'organisation_email',
        ];

        $key = $aliases[$textType] ?? $textType;

        return data_get($data, $key);
    }

    private function resolveFontPath(?string $fontFace): string
    {
        $fontFace = basename((string) $fontFace);
        $allowed = array_keys($this->certificateFontType());

        if (! in_array($fontFace, $allowed, true)) {
            $fontFace = array_key_first($this->certificateFontType());
        }

        $definition = config('certificates.fonts', [])[$fontFace] ?? null;
        $path = is_array($definition) ? ($definition['path'] ?? null) : null;

        if (! is_file($path)) {
            $fallback = public_path('certificate_fonts/'.$fontFace);

            if (is_file($fallback)) {
                return $fallback;
            }

            throw new RuntimeException("The certificate font [{$fontFace}] could not be found.");
        }

        return $path;
    }

    private function generateCertificateNumber(int|string|null $scopeKey): string
    {
        $attempts = max(1, (int) config('certificates.certificate_numbers.max_attempts', 25));

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $number = $this->numberGenerator->generate($scopeKey);
            if (filled($number) && ! $this->issuedQuery()->where('certificate_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Unable to generate a unique certificate number.');
    }

    private function createTemplateSnapshot(CertificateTemplate $template): array
    {
        return [
            'template_id' => $template->id,
            'name' => $template->name,
            'certificate_template' => $template->certificate_template,
            'settings' => $template->settings,
            'supported_modules' => $template->supported_modules,
        ];
    }

    private function createDataSnapshot(array $data): array
    {
        return Arr::only($data, array_keys($data));
    }

    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null,
        Model|Authenticatable|null $scopeSubject = null,
    ): string {
        $base = Str::slug($name) ?: 'certificate-template';
        $slug = $base;
        $suffix = 2;

        while ($this->scope->apply($this->templateQuery(), $scopeSubject)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function createPreviewDirectory(Model|Authenticatable|null $scopeSubject, ?string $previewKey = null): string
    {
        $directory = trim(config('certificates.storage.preview_directory'), '/').'/'.$this->scope->pathSegment($scopeSubject).'/'.$this->sanitizePreviewKey($previewKey ?? 'draft');

        return $this->storage->ensureDirectory($directory);
    }

    private function createIssuedCertificateDirectory(Model|Authenticatable|null $scopeSubject): string
    {
        $directory = trim(config('certificates.storage.issued_directory'), '/').'/'.$this->scope->pathSegment($scopeSubject).'/'.now()->format(config('certificates.rendering.storage_date_format', 'Y/m/d'));

        return $this->storage->ensureDirectory($directory);
    }

    private function normalizeSupportedModules(array|string|null $modules): ?array
    {
        if ($this->supportedModules() === []) {
            return null;
        }

        if (is_string($modules)) {
            $modules = json_decode($modules, true, flags: JSON_THROW_ON_ERROR);
        }

        if ($modules === null) {
            return null;
        }

        $modules = array_values(array_filter(array_map('strval', (array) $modules)));

        return $modules ?: null;
    }

    public function clearPreviewCache(int|string|null $scopeKey, string $previewKey): void
    {
        $segment = $scopeKey === null ? 'global' : trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $scopeKey), '-_');
        $directory = trim(config('certificates.storage.preview_directory'), '/').'/'.$segment.'/'.$this->sanitizePreviewKey($previewKey);
        $this->storage->deleteDirectory($directory);
    }

    private function resolvePreviewKey(array $data, ?CertificateTemplate $template = null): string
    {
        if ($template?->id) {
            return 'template-'.$template->id;
        }

        $previewKey = trim((string) ($data['preview_key'] ?? ''));

        return $previewKey !== '' ? 'draft-'.$previewKey : 'draft';
    }

    private function sanitizePreviewKey(string $previewKey): string
    {
        $previewKey = preg_replace('/[^A-Za-z0-9_-]+/', '-', $previewKey) ?: 'draft';

        return trim($previewKey, '-_') ?: 'draft';
    }

    private function mergeDefaultData(array $data, Model $recipient, CertificateTemplate $template, string $sourceType): array
    {
        return array_merge($this->buildSampleData($template->supported_modules ?: [$sourceType]), $data, [
            'name' => $data['name'] ?? $this->context->recipientName($recipient),
            'email' => $data['email'] ?? $this->context->recipientEmail($recipient),
            'date_issued' => $data['date_issued'] ?? now()->format(config('certificates.rendering.issued_date_format', 'jS \d\a\y \o\f F, Y')),
        ]);
    }

    private function buildSampleData(?array $modules = null): array
    {
        $data = config('certificates.sample_data', []);
        $moduleData = config('certificates.module_sample_data', []);

        foreach ($modules ?? [] as $module) {
            $data = array_merge($data, $moduleData[$module] ?? []);
        }

        return $data;
    }

    private function allowedTextTypes(): array
    {
        $configured = config('certificates.element_library.allowed_types');

        if (is_array($configured)) {
            return array_values(array_unique($configured));
        }

        return collect(config('certificates.element_library.groups', []))
            ->flatMap(fn (array $group): array => $group['items'] ?? [])
            ->pluck('text_type')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function assertOwnership(CertificateTemplate $template, Model|Authenticatable $actor, bool $throw = true): bool
    {
        $ok = $this->scope->owns($template, $actor);

        if (! $ok && $throw) {
            throw ValidationException::withMessages([
                'certificate_template' => 'This certificate template is outside the current certificate scope.',
            ]);
        }

        return $ok;
    }

    private function authorizeActor(Authenticatable $actor): void
    {
        if (! $this->context->canManage($actor)) {
            throw ValidationException::withMessages([
                'certificate_template' => 'You are not allowed to manage certificate templates.',
            ]);
        }
    }

    private function routeName(string $route): string
    {
        return config('certificates.routes.name', 'certificates.').$route;
    }

    private function templateQuery()
    {
        $model = config('certificates.models.template', CertificateTemplate::class);

        return $model::query();
    }

    private function issuedQuery()
    {
        $model = config('certificates.models.issued_certificate', IssuedCertificate::class);

        return $model::query();
    }

    private function newTemplate(array $attributes): CertificateTemplate
    {
        $model = config('certificates.models.template', CertificateTemplate::class);

        return new $model($attributes);
    }
}
