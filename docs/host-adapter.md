# Host Adapter Example

The package stays generic by resolving the current actor and permission check through contracts.

For an admin-backed host app, the smallest adapter is usually two classes:

```php
<?php

namespace App\Certificates;

use DavidOghi\CertificateGeneration\Contracts\CertificateActorResolver;
use DavidOghi\CertificateGeneration\Contracts\CertificateAuthorizationResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminActorResolver implements CertificateActorResolver
{
    public function resolve(): ?Authenticatable
    {
        return auth('admin')->user();
    }
}

class AdminCertificateAuthorizationResolver implements CertificateAuthorizationResolver
{
    public function canManage(?Authenticatable $actor = null): bool
    {
        $roles = array_values(array_filter(array_map('strval', (array) ($actor?->roles ?? []))));

        return $actor?->id === 1
            || count(array_intersect($roles, ['Admin', 'Facilitator', 'Grader'])) > 0;
    }
}
```

Bind them from the host app:

```php
$this->app->bind(
    \DavidOghi\CertificateGeneration\Contracts\CertificateActorResolver::class,
    \App\Certificates\AdminActorResolver::class,
);

$this->app->bind(
    \DavidOghi\CertificateGeneration\Contracts\CertificateAuthorizationResolver::class,
    \App\Certificates\AdminCertificateAuthorizationResolver::class,
);
```

If a host app uses a different guard, role system, or permission storage, only this adapter needs to change.

The package's preview routes accept both `GET` and `POST`, so the browser can open a preview URL directly without relying on a redirect back into the dashboard. Your designer JavaScript can keep posting JSON to the same route.
