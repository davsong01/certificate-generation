<?php

namespace DavidOghi\CertificateGeneration\Http\Controllers;

use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use DavidOghi\CertificateGeneration\Models\CertificateTemplate;
use DavidOghi\CertificateGeneration\Services\CertificateManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CertificateTemplateController extends Controller
{
    public function __construct(
        private CertificateManager $certificates,
        private CertificateContext $context,
        private CertificateScope $scope,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->context->canManage(), 403);
        $filters = $request->only(['search', 'status', 'module']);
        $query = $this->scope->apply($this->templateQuery());

        $templates = $query
            ->with('creator')->withCount('issuedCertificates')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', (bool) $filters['status']))
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->forModule($module))
            ->latest('id')->paginate(15)->withQueryString();

        $model = config('certificates.models.template', CertificateTemplate::class);

        return view('certificates::templates.index', [
            'templates' => $templates,
            'filters' => $filters,
            'modules' => $this->certificates->supportedModules(),
            'summary' => [
                'total' => $this->scope->apply($model::query())->count(),
                'active' => $this->scope->apply($model::query())->active()->count(),
                'issued' => $this->scope->apply(config('certificates.models.issued_certificate')::query())->count(),
            ],
        ]);
    }

    public function create()
    {
        abort_unless($this->context->canManage(), 403);

        return view('certificates::templates.create', $this->formData());
    }

    public function store(Request $request)
    {
        abort_unless($this->context->canManage(), 403);
        $template = $this->certificates->create($this->validated($request), $this->context->actor());

        return redirect()->route($this->routeName('manage.templates.edit'), $template)
            ->with('success', 'Certificate template created successfully.');
    }

    public function show(string|int $template)
    {
        $template = $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        abort_unless($this->scope->owns($template, $this->context->actor()), 404);

        return view('certificates::templates.show', array_merge($this->formData($template), compact('template')));
    }

    public function edit(string|int $template)
    {
        $template = $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        abort_unless($this->scope->owns($template, $this->context->actor()), 404);

        return view('certificates::templates.edit', array_merge($this->formData($template), compact('template')));
    }

    public function update(Request $request, string|int $template)
    {
        $template = $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        $this->certificates->update($template, $this->validated($request, false), $this->context->actor());

        return redirect()->route($this->routeName('manage.templates.edit'), $template)
            ->with('success', 'Certificate template updated successfully.');
    }

    public function destroy(string|int $template)
    {
        $template = $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        $this->certificates->delete($template, $this->context->actor());

        return redirect()->route($this->routeName('manage.templates.index'))
            ->with('success', 'Certificate template deleted successfully.');
    }

    public function duplicate(string|int $template)
    {
        $template = $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        $copy = $this->certificates->duplicate($template, $this->context->actor());

        return redirect()->route($this->routeName('manage.templates.edit'), $copy)
            ->with('success', 'Certificate template duplicated successfully.');
    }

    public function preview(Request $request, string|int|null $template = null)
    {
        $template = $template === null ? null : $this->resolveTemplate($template);
        abort_unless($this->context->canManage(), 403);
        if ($template) {
            abort_unless($this->scope->owns($template, $this->context->actor()), 404);
        }
        $preview = $this->certificates->preview($this->validated($request, false), $template);
        $absolute = Storage::disk(config('certificates.storage.disk'))->path($preview['relative_path']);
        $mime = mime_content_type($absolute) ?: 'image/png';

        return response()->json([
            'preview_url' => $preview['preview_url'],
            'preview_data_url' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute)),
            'message' => 'Preview generated successfully.',
        ]);
    }

    private function validated(Request $request, bool $backgroundRequired = true): array
    {
        $modules = $this->certificates->supportedModules();
        $mimes = implode(',', config('certificates.uploads.mimes', ['jpg', 'jpeg', 'png', 'webp']));
        $maxKilobytes = max(1, (int) config('certificates.uploads.max_kb', 10240));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'supported_modules' => $modules === [] ? ['prohibited'] : ['nullable', 'array'],
            'supported_modules.*' => ['string', Rule::in(array_keys($modules))],
            'settings' => ['nullable'],
            'preview_key' => ['nullable', 'string', 'max:100'],
            'certificate_template_upload' => [$backgroundRequired ? 'required' : 'nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ]);

        $data['status'] = $request->boolean('status');

        return $data;
    }

    private function formData(?CertificateTemplate $template = null): array
    {
        $background = null;
        if ($template?->certificate_template) {
            $disk = Storage::disk(config('certificates.storage.disk'));
            if ($disk->exists($template->certificate_template)) {
                $absolute = $disk->path($template->certificate_template);
                $background = 'data:'.(mime_content_type($absolute) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($absolute));
            }
        }

        return [
            'modules' => $this->certificates->supportedModules(),
            'fontOptions' => $this->certificates->certificateFontType(),
            'backgroundPreview' => $background,
            'previewKey' => $template ? 'template-'.$template->id : 'draft-'.str()->uuid(),
        ];
    }

    private function templateQuery()
    {
        $model = config('certificates.models.template', CertificateTemplate::class);

        return $model::query();
    }

    private function resolveTemplate(string|int $value): CertificateTemplate
    {
        $model = config('certificates.models.template', CertificateTemplate::class);
        $instance = new $model;
        $template = $model::query()
            ->where($instance->getRouteKeyName(), $value)
            ->firstOrFail();

        abort_unless($template instanceof CertificateTemplate, 500, 'The configured certificate template model must extend the package model.');

        return $template;
    }

    private function routeName(string $suffix): string
    {
        return config('certificates.routes.name', 'certificates.').$suffix;
    }
}
