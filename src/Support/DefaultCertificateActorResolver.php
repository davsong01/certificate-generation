<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateActorResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class DefaultCertificateActorResolver implements CertificateActorResolver
{
    public function resolve(): ?Authenticatable
    {
        $guard = config('certificates.authorization.guard');

        if (! empty($guard)) {
            return auth($guard)->user();
        }

        return auth()->user();
    }
}
