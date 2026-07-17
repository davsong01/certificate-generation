<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NullCertificateScope implements CertificateScope
{
    public function apply(Builder $query, Model|Authenticatable|null $subject = null): Builder
    {
        return $query;
    }

    public function attributes(Model|Authenticatable|null $subject = null): array
    {
        return [];
    }

    public function owns(Model $record, Model|Authenticatable $actor): bool
    {
        return true;
    }

    public function key(Model|Authenticatable|null $subject = null): int|string|null
    {
        return null;
    }

    public function pathSegment(Model|Authenticatable|null $subject = null): string
    {
        return 'global';
    }
}
