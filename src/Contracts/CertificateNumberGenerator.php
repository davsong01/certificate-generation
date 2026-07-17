<?php

namespace DavidOghi\CertificateGeneration\Contracts;

interface CertificateNumberGenerator
{
    public function generate(int|string|null $scopeKey = null): string;
}
