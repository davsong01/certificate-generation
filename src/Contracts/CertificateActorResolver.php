<?php

namespace DavidOghi\CertificateGeneration\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface CertificateActorResolver
{
    public function resolve(): ?Authenticatable;
}
