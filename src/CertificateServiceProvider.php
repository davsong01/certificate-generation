<?php

namespace DavidOghi\CertificateGeneration;

use DavidOghi\CertificateGeneration\Actions\IssueCertificate;
use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use DavidOghi\CertificateGeneration\Contracts\CertificateNumberGenerator;
use DavidOghi\CertificateGeneration\Contracts\CertificateScope;
use DavidOghi\CertificateGeneration\Contracts\CertificateTrigger;
use DavidOghi\CertificateGeneration\Contracts\VerificationUrlGenerator;
use DavidOghi\CertificateGeneration\Services\CertificateManager;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateContext;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateNumberGenerator;
use DavidOghi\CertificateGeneration\Support\NullCertificateScope;
use DavidOghi\CertificateGeneration\Support\RouteVerificationUrlGenerator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class CertificateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/certificates.php', 'certificates');

        $this->app->bind(CertificateContext::class, DefaultCertificateContext::class);
        $this->app->bind(CertificateScope::class, config('certificates.scope', NullCertificateScope::class));
        $this->app->bind(CertificateNumberGenerator::class, config('certificates.certificate_numbers.generator', DefaultCertificateNumberGenerator::class));
        $this->app->bind(VerificationUrlGenerator::class, config('certificates.verification_urls.generator', RouteVerificationUrlGenerator::class));
        $this->app->singleton(CertificateManager::class);
        $this->app->alias(CertificateManager::class, 'certificates');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'certificates');
        $this->registerIssuanceTriggers();

        if (config('certificates.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([
            __DIR__.'/../config/certificates.php' => config_path('certificates.php'),
        ], 'certificates-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_certificate_package_tables.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_certificate_package_tables.php'),
        ], 'certificates-migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/add_certificate_tenancy.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_add_certificate_tenancy.php'),
        ], 'certificates-tenancy-migration');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/certificates'),
        ], 'certificates-views');
    }

    private function registerIssuanceTriggers(): void
    {
        $events = $this->app->make(Dispatcher::class);

        foreach (config('certificates.issuance.triggers', []) as $eventClass => $handlers) {
            foreach (Arr::wrap($handlers) as $handlerClass) {
                $events->listen($eventClass, function (object $event) use ($handlerClass): void {
                    $handler = $this->app->make($handlerClass);

                    if (! $handler instanceof CertificateTrigger) {
                        throw new InvalidArgumentException("Certificate trigger [{$handlerClass}] must implement CertificateTrigger.");
                    }

                    $handler->handle($event, $this->app->make(IssueCertificate::class));
                });
            }
        }
    }
}
