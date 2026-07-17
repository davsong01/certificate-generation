<?php

namespace DavidOghi\CertificateGeneration\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CertificateScope
{
    public function apply(Builder $query, Model|Authenticatable|null $subject = null): Builder;

    /** @return array<string, mixed> */
    public function attributes(Model|Authenticatable|null $subject = null): array;

    public function owns(Model $record, Model|Authenticatable $actor): bool;

    public function key(Model|Authenticatable|null $subject = null): int|string|null;

    public function pathSegment(Model|Authenticatable|null $subject = null): string;
}
