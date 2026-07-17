<?php

namespace DavidOghi\CertificateGeneration\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CertificateAuthorizationResolver
{
    public function canManage(?Authenticatable $actor = null): bool;
}
