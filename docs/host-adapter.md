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
        return $actor?->id === 1
            || in_array('certificates.manage.templates.index', $actor?->menu_permissions ?? [], true);
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
