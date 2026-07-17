<?php

namespace DavidOghi\CertificateGeneration\Models;

use Illuminate\Database\Eloquent\Model;

class IssuedCertificate extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('certificates.tables.issued_certificates', 'issued_certificates');
    }

    protected function casts(): array
    {
        return [
            'data_snapshot' => 'array',
            'template_snapshot' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function template()
    {
        return $this->belongsTo(config('certificates.models.template'), 'certificate_template_id');
    }

    public function recipient()
    {
        return $this->belongsTo(config('certificates.models.user'), 'user_id');
    }

    public function issuer()
    {
        return $this->belongsTo(config('certificates.models.user'), 'issued_by');
    }
}
