<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DefaultCertificateContext implements CertificateContext
{
    public function actor(): ?Authenticatable
    {
        return auth()->user();
    }

    public function canManage(?Authenticatable $actor = null): bool
    {
        $actor ??= $this->actor();
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

    public function recipientName(Model $recipient): string
    {
        return (string) ($recipient->name ?? trim(($recipient->first_name ?? '').' '.($recipient->last_name ?? '')));
    }

    public function recipientEmail(Model $recipient): ?string
    {
        return $recipient->email ?? null;
    }
}
