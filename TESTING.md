# Testing

This package should be tested in two layers.

## Package Checks

Run these from the package root:

```bash
composer validate --strict
php -l src/CertificateServiceProvider.php
php -l src/Http/Controllers/CertificateTemplateController.php
php -l src/Actions/IssueCertificate.php
```

## Living Laravel App

Use Oakland as the living integration test app.

- Package source folder to edit when updating: `/Applications/MAMP/htdocs/oakland/packages/david-oghi/certificate-generation`
- Local path: `/Applications/MAMP/htdocs/oakland`
- Repository: `https://github.com/davsong01/oakland.git`

For local package development, point Oakland to this package using a Composer path repository:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/david-oghi/certificate-generation",
        "options": {
            "symlink": true
        }
    }
]
```

Then install/update it in Oakland:

```bash
composer require david-oghi/certificate-generation:@dev
php artisan vendor:publish --tag=certificate-generation-config
php artisan vendor:publish --tag=certificate-generation-migrations
php artisan migrate
```

Confirm the template designer, certificate issuing flow, verification page, downloads, storage paths, and project menu links work before pushing or tagging a release.
