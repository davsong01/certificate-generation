<?php

use DavidOghi\CertificateGeneration\Http\Controllers\CertificateController;
use DavidOghi\CertificateGeneration\Http\Controllers\CertificateTemplateController;
use DavidOghi\CertificateGeneration\Http\Controllers\IssuedCertificateController;
use Illuminate\Support\Facades\Route;

$prefix = trim(config('certificates.routes.prefix', 'certificates'), '/');
$managementPrefix = trim(config('certificates.routes.management_prefix', 'manage'), '/');
$adminPrefix = trim((string) config('certificates.routes.admin_prefix', ''), '/');
$name = config('certificates.routes.name', 'certificates.');

Route::middleware(config('certificates.routes.public_middleware', ['web']))
    ->prefix($prefix)->name($name)->group(function (): void {
        if (config('certificates.routes.verification_enabled', true)) {
            Route::get('{certificateNumber}/verify', [CertificateController::class, 'verify'])->name('verify');
        }
        if (config('certificates.routes.download_enabled', true)) {
            Route::get('{certificateNumber}/download', [CertificateController::class, 'download'])->name('download');
        }
        if (config('certificates.routes.preview_enabled', true)) {
            Route::get('preview/{token}', [CertificateController::class, 'preview'])->name('preview-file');
        }
    });

if (config('certificates.routes.management_enabled', true)) {
    Route::middleware(config('certificates.routes.middleware', ['web', 'auth']))
        ->prefix(implode('/', array_filter([$adminPrefix, $prefix, $managementPrefix])))->name($name.'manage.')->group(function (): void {
            Route::get('issued', IssuedCertificateController::class)->name('issued.index');
            if (config('certificates.routes.preview_enabled', true)) {
                Route::post('templates/preview', [CertificateTemplateController::class, 'preview'])->name('templates.preview');
                Route::post('templates/{template}/preview', [CertificateTemplateController::class, 'preview'])->name('templates.preview-template');
            }
            Route::post('templates/{template}/duplicate', [CertificateTemplateController::class, 'duplicate'])->name('templates.duplicate');
            Route::resource('templates', CertificateTemplateController::class);
        });
}
