<?php

namespace DavidOghi\CertificateGeneration\Events;

use DavidOghi\CertificateGeneration\Models\IssuedCertificate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CertificateIssued implements ShouldDispatchAfterCommit
{
    public function __construct(public IssuedCertificate $certificate) {}
}
