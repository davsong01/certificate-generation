<?php

namespace DavidOghi\CertificateGeneration\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CertificateStorage
{
    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs(trim($directory, '/'), $name, $this->disk());

        if (! $path) {
            throw new RuntimeException('Unable to store the certificate file.');
        }

        return $path;
    }

    public function copy(string $source, string $directory, string $name): string
    {
        $target = trim($directory, '/').'/'.$name;
        Storage::disk($this->disk())->copy($source, $target);

        return $target;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk($this->disk())->exists($path);
    }

    public function absolutePath(string $path): string
    {
        $disk = Storage::disk($this->disk());

        if (! method_exists($disk, 'path')) {
            throw new RuntimeException('Certificate storage requires a local filesystem disk.');
        }

        return $disk->path($path);
    }

    public function relativePath(string $absolutePath): string
    {
        $root = rtrim($this->absolutePath(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolutePath, $root)) {
            throw new RuntimeException('The certificate path is outside the configured storage disk.');
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
    }

    public function ensureDirectory(string $directory): string
    {
        Storage::disk($this->disk())->makeDirectory($directory);

        return $this->absolutePath($directory);
    }

    public function deleteDirectory(string $directory): void
    {
        Storage::disk($this->disk())->deleteDirectory($directory);
    }

    public function disk(): string
    {
        return config('certificates.storage.disk', 'local');
    }
}
