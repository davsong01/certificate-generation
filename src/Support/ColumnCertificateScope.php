<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ColumnCertificateScope implements CertificateScope
{
    public function __construct(private CertificateContext $context) {}

    public function apply(Builder $query, Model|Authenticatable|null $subject = null): Builder
    {
        $key = $this->key($subject);

        return $key === null ? $query : $query->where($this->column(), $key);
    }

    public function attributes(Model|Authenticatable|null $subject = null): array
    {
        return [$this->column() => $this->key($subject)];
    }

    public function owns(Model $record, Model|Authenticatable $actor): bool
    {
        return (string) $record->{$this->column()} === (string) $this->key($actor);
    }

    public function key(Model|Authenticatable|null $subject = null): int|string|null
    {
        $resolver = config('certificates.tenancy.resolver');
        if (is_callable($resolver)) {
            return $resolver($subject ?? $this->context->actor());
        }

        $subject ??= $this->context->actor();
        $column = $subject instanceof Authenticatable
            ? config('certificates.tenancy.actor_column', $this->column())
            : $this->column();

        return $subject?->{$column};
    }

    public function pathSegment(Model|Authenticatable|null $subject = null): string
    {
        $key = $this->key($subject);

        return $key === null ? 'unscoped' : trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $key), '-_');
    }

    private function column(): string
    {
        return config('certificates.tenancy.column', 'tenant_id');
    }
}
