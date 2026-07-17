<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\VerificationUrlGenerator;

class RouteVerificationUrlGenerator implements VerificationUrlGenerator
{
    public function generate(?string $certificateNumber): string
    {
        return route(config('certificates.routes.name', 'certificates.').'verify', [
            'certificateNumber' => $certificateNumber ?? 'preview',
        ]);
    }
}
