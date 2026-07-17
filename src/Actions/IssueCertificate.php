<?php

namespace DavidOghi\CertificateGeneration\Actions;

use DavidOghi\CertificateGeneration\Models\CertificateTemplate;
use DavidOghi\CertificateGeneration\Models\IssuedCertificate;
use DavidOghi\CertificateGeneration\Services\CertificateManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class IssueCertificate
{
    public function __construct(private CertificateManager $certificates) {}

    public function handle(
        CertificateTemplate $template,
        Model $recipient,
        string $sourceType,
        Model|int $source,
        Model|int|null $sourceRecord = null,
        array $data = [],
        ?Authenticatable $issuer = null,
    ): IssuedCertificate {
        $sourceId = $this->modelOrIntegerId($source, 'source');
        $sourceRecordId = $sourceRecord === null
            ? $sourceId
            : $this->modelOrIntegerId($sourceRecord, 'source record');

        return $this->certificates->issue(
            template: $template,
            recipient: $recipient,
            sourceType: $sourceType,
            sourceId: $sourceId,
            sourceRecordId: $sourceRecordId,
            data: $data,
            issuer: $issuer,
        );
    }

    public function __invoke(
        CertificateTemplate $template,
        Model $recipient,
        string $sourceType,
        Model|int $source,
        Model|int|null $sourceRecord = null,
        array $data = [],
        ?Authenticatable $issuer = null,
    ): IssuedCertificate {
        return $this->handle($template, $recipient, $sourceType, $source, $sourceRecord, $data, $issuer);
    }

    private function modelOrIntegerId(Model|int $value, string $label): int
    {
        $id = $value instanceof Model ? $value->getKey() : $value;

        if (! is_numeric($id) || (int) $id < 1) {
            throw new InvalidArgumentException("The certificate {$label} must have a positive integer ID.");
        }

        return (int) $id;
    }
}
