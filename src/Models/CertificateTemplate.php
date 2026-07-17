<?php

namespace DavidOghi\CertificateGeneration\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('certificates.tables.templates', 'certificate_templates');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'supported_modules' => 'array',
            'status' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(config('certificates.models.user'), 'created_by');
    }

    public function issuedCertificates()
    {
        return $this->hasMany(config('certificates.models.issued_certificate'), 'certificate_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where(function ($builder) use ($module): void {
            $builder->whereNull('supported_modules')
                ->orWhereJsonLength('supported_modules', 0)
                ->orWhereJsonContains('supported_modules', $module);
        });
    }

    public function supportsModule(?string $module): bool
    {
        return ! $module || empty($this->supported_modules) || in_array($module, $this->supported_modules, true);
    }
}
