<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateAuthorizationResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class DefaultCertificateAuthorizationResolver implements CertificateAuthorizationResolver
{
    public function canManage(?Authenticatable $actor = null): bool
    {
        if (! $actor) {
            return false;
        }

        $ability = config('certificates.authorization.manage_ability');
        if ($ability && Gate::forUser($actor)->check($ability)) {
            return true;
        }

        $column = config('certificates.authorization.actor_type_column', 'type');

        return in_array($actor->{$column} ?? null, config('certificates.authorization.allowed_actor_types', []), true);
    }
}
