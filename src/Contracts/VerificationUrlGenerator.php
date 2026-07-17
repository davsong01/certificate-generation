<?php

namespace DavidOghi\CertificateGeneration\Contracts;

interface VerificationUrlGenerator
{
    public function generate(?string $certificateNumber): string;
}
