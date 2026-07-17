<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateActorResolver;
use DavidOghi\CertificateGeneration\Contracts\CertificateAuthorizationResolver;
use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class DefaultCertificateContext implements CertificateContext
{
    public function __construct(
        private CertificateActorResolver $actorResolver,
        private CertificateAuthorizationResolver $authorizationResolver,
    ) {}

    public function actor(): ?Authenticatable
    {
        return $this->actorResolver->resolve();
    }

    public function canManage(?Authenticatable $actor = null): bool
    {
        return $this->authorizationResolver->canManage($actor ?? $this->actor());
    }

    public function recipientName(Model $recipient): string
    {
        return (string) ($recipient->name ?? trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')));
    }

    public function recipientEmail(Model $recipient): ?string
    {
        return $recipient->email ?? null;
    }
}
