<?php

namespace DavidOghi\CertificateGeneration\Contracts;

use DavidOghi\CertificateGeneration\Actions\IssueCertificate;

interface CertificateTrigger
{
    public function handle(object $event, IssueCertificate $issueCertificate): void;
}
