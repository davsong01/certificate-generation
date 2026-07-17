<?php

namespace DavidOghi\CertificateGeneration\Facades;

use DavidOghi\CertificateGeneration\Services\CertificateManager;
use Illuminate\Support\Facades\Facade;

class Certificates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CertificateManager::class;
    }
}
