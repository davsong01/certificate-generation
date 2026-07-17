<?php

namespace DavidOghi\CertificateGeneration\Support;

use DavidOghi\CertificateGeneration\Contracts\CertificateNumberGenerator;
use Illuminate\Support\Str;

class DefaultCertificateNumberGenerator implements CertificateNumberGenerator
{
    public function generate(int|string|null $scopeKey = null): string
    {
        $separator = (string) config('certificates.certificate_numbers.separator', '-');
        $parts = [(string) config('certificates.certificate_numbers.prefix', 'CERT')];

        if (config('certificates.certificate_numbers.include_scope', true)) {
            $scope = $scopeKey === null ? 'GLOBAL' : preg_replace('/[^A-Za-z0-9]+/', '', (string) $scopeKey);
            $parts[] = $scope ?: 'GLOBAL';
        }

        if (config('certificates.certificate_numbers.include_year', true)) {
            $parts[] = now()->format('Y');
        }

        $length = max(4, (int) config('certificates.certificate_numbers.random_length', 8));
        $parts[] = Str::upper(Str::random($length));

        return implode($separator, array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
