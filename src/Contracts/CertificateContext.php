<?php

namespace DavidOghi\CertificateGeneration\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

interface CertificateContext
{
    public function actor(): ?Authenticatable;

    public function canManage(?Authenticatable $actor = null): bool;

    public function recipientName(Model $recipient): string;

    public function recipientEmail(Model $recipient): ?string;
}
