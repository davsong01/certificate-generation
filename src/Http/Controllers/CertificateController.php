<?php

namespace DavidOghi\CertificateGeneration\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CertificateController extends Controller
{
    public function preview(string $token): Response
    {
        try {
            $path = Crypt::decryptString($token);
        } catch (Throwable) {
            abort(404);
        }

        abort_unless(Storage::disk($this->disk())->exists($path), 404);

        return Storage::disk($this->disk())->response($path);
    }

    public function verify(string $certificateNumber)
    {
        $model = config('certificates.models.issued_certificate');
        $certificate = $model::withoutGlobalScopes()
            ->with(['template', 'recipient', 'issuer'])
            ->where('certificate_number', $certificateNumber)->first();

        return view('certificates::verify', compact('certificate', 'certificateNumber'));
    }

    public function download(string $certificateNumber): Response
    {
        $model = config('certificates.models.issued_certificate');
        $certificate = $model::withoutGlobalScopes()->where('certificate_number', $certificateNumber)->firstOrFail();
        abort_unless(Storage::disk($this->disk())->exists($certificate->file_path), 404);

        return Storage::disk($this->disk())->download($certificate->file_path);
    }

    private function disk(): string
    {
        return config('certificates.storage.disk', 'local');
    }
}
