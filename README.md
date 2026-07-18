# David Oghi Certificate Generation

A self-contained Laravel package for designing certificate templates, rendering PNG certificates, issuing immutable certificate records, regenerating files, public verification, and secure downloads.

The drag-and-drop designer and server renderer use the same JSON payload, so the editor preview and issued file remain aligned.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- GD with FreeType support
- A local Laravel filesystem disk for private templates and issued files
- Bootstrap 5 and Tabler Icons in the management layout used by the bundled designer
- `@stack('scripts')` before the closing body tag of that layout

Composer installs `intervention/image`, `endroid/qr-code`, and Dompdf's DejaVu font files with the package.

## Install

```bash
composer require david-oghi/certificate-generation:^1.0
php artisan vendor:publish --tag=certificates-config
php artisan vendor:publish --tag=certificates-migrations
php artisan migrate
```

Publish the views only when they need application-specific styling:

```bash
php artisan vendor:publish --tag=certificates-views
```

The default routes are:

- `GET /certificates/manage/templates`
- `GET /certificates/manage/issued`
- `GET /certificates/{certificateNumber}/verify`
- `GET /certificates/{certificateNumber}/download`
- `GET /certificates/preview/{token}`
- `GET|POST /certificates/manage/templates/preview`
- `GET|POST /certificates/manage/templates/{template}/preview`

Management routes use `web` by default so the package returns explicit authorization errors instead of depending on host-side redirect middleware. Verification, preview, and download use `web`. All prefixes, names and middleware are configurable. Host apps that need an admin prefix can set `routes.admin_prefix` and a custom middleware stack.

## Configure the host application

Set `certificates.layout` to a Blade layout that provides Bootstrap 5, Tabler Icons, a `content` section, and a `scripts` stack.

The default package assumes `App\Models\User`. Override `models.user` if the application's authenticatable model is elsewhere.

The package does not assume a specific guard, role system, or menu schema. Configure certificate access by binding your own resolver classes or by replacing `CertificateContext`.

Most host-owned behavior is configurable without publishing package code:

- `modules`, `element_library`, `sample_data`, and `module_sample_data` define application vocabulary and preview data.
- `designer.canvas`, `designer.grid_size`, and `designer.element_defaults` define the editing defaults.
- `uploads.mimes` and `uploads.max_kb` control accepted background images.
- `fonts` is the renderer/designer font allow-list.
- `rendering.format`, `rendering.issued_date_format`, and `rendering.storage_date_format` control output and dates.
- `certificate_numbers` controls the standard prefix, separator, scope/year parts, random length, and collision attempts.
- `templates.delete_policy` is `restrict` by default; set it to `cascade` only when deleting issued records and files with their template is intentional.
- `ui` controls the bundled designer's main terminology; publish the views for complete presentation changes.
- `ui.package_version` lets the host display the exact package build that is currently installed.
- `ui.bootstrap_version` controls Bootstrap 4 vs 5 friendly markup defaults for the bundled designer views.
- `models`, `tables`, `routes`, `storage`, `authorization`, `scope`, and `issuance.triggers` cover application integration. Management, verification, download, and preview routes can each be disabled independently.
- `authorization.context`, `authorization.actor_resolver`, and `authorization.permission_resolver` let the host provide its own auth and permission adapter without forking the package. A small resolver can rely on the admin guard and your app's roles without touching menu permissions.

For behavior more specialized than scalar settings, replace a contract with a host class in configuration. Certificate-number and verification-URL generation are replaceable and remain compatible with `config:cache`:

```php
'certificate_numbers' => [
    'generator' => App\Certificates\MembershipNumberGenerator::class,
    // Standard generator settings are ignored by a custom generator.
],
'verification_urls' => [
    'generator' => App\Certificates\PublicVerificationUrlGenerator::class,
],
```

The classes implement `CertificateNumberGenerator` and `VerificationUrlGenerator`, respectively. The package still checks custom certificate numbers for uniqueness and retries up to `certificate_numbers.max_attempts`.

### Non-tenant application

No tenancy setup is required. The core package uses `NullCertificateScope`, and its base migration contains no tenant column, tenant relationship, or tenant assumptions.

### Multi-tenant application

Tenancy is a separate opt-in adapter. Publish the optional migration, then configure the column-based scope:

```bash
php artisan vendor:publish --tag=certificates-tenancy-migration
php artisan migrate
```

```php
'scope' => \DavidOghi\CertificateGeneration\Support\ColumnCertificateScope::class,
'tenancy' => [
    'column' => 'school_id',
    'actor_column' => 'school_id',
    'resolver' => null,
],
```

For a tenancy package or non-column ownership rules, implement and bind `CertificateScope` instead. This keeps tenant resolution and query scoping outside the certificate models, renderer, issuer, and core migration.

Application authorization and recipient display values remain customizable through `CertificateContext`:

```php
use DavidOghi\CertificateGeneration\Contracts\CertificateContext;
use App\Certificates\ApplicationCertificateContext;

$this->app->bind(CertificateContext::class, ApplicationCertificateContext::class);
```

`CertificateContext` controls the current actor, management authorization, and recipient name/email extraction. It has no tenancy methods.

The smallest host integration is usually a pair of resolver classes:

```php
use DavidOghi\CertificateGeneration\Contracts\CertificateActorResolver;
use DavidOghi\CertificateGeneration\Contracts\CertificateAuthorizationResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class HostCertificateActorResolver implements CertificateActorResolver
{
    public function resolve(): ?Authenticatable
    {
        return auth('admin')->user();
    }
}

class HostCertificateAuthorizationResolver implements CertificateAuthorizationResolver
{
    public function canManage(?Authenticatable $actor = null): bool
    {
        $roles = array_values(array_filter(array_map('strval', (array) ($actor?->roles ?? []))));

        return $actor?->id === 1 || count(array_intersect($roles, ['Admin', 'Facilitator', 'Grader'])) > 0;
    }
}
```

Then wire them in the host application config or service provider:

```php
'authorization' => [
    'context' => \DavidOghi\CertificateGeneration\Support\DefaultCertificateContext::class,
    'actor_resolver' => \App\Certificates\HostCertificateActorResolver::class,
    'permission_resolver' => \App\Certificates\HostCertificateAuthorizationResolver::class,
],
'ui' => [
    'package_version' => '1.0.0',
],
```

## Fonts

Rendering requires absolute TTF paths. Configure every font offered by the designer:

```php
'fonts' => [
    'DejaVuSans.ttf' => [
        'label' => 'DejaVu Sans',
        'path' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
    ],
],
```

Do not expose a font in this map unless its file exists. Only configured basenames are accepted, preventing arbitrary filesystem reads.

## Template settings format

```json
{
  "canvas": {"width": 1123, "height": 794, "orientation": "landscape"},
  "elements": [
    {
      "id": "recipient_name",
      "text_type": "name",
      "left": 300,
      "top": 340,
      "width": 520,
      "font": "DejaVuSerif.ttf",
      "font_size": 42,
      "color": "#172554",
      "align": "center"
    },
    {"id": "verification", "text_type": "qr_code", "left": 940, "top": 640, "size": 120}
  ]
}
```

Supported modules are optional configuration, not package policy. The default `modules` array is empty, the designer hides the module selector, and templates are unrestricted. Add module labels only when the host application wants templates limited to particular workflows. Data fields are configurable independently.

## Configure the Elements Library

The designer library is application vocabulary and is fully configurable. Its heading, help text, empty state, groups, labels, icons, sample text, module visibility and initial element properties all come from `certificates.element_library`. Set `enabled` to `false` to remove the library while retaining editing of elements already stored on a template, or use an empty `groups` array to show its configured empty state.

```php
'element_library' => [
    'enabled' => true,
    'title' => 'Course Certificate Fields',
    'description' => 'Add learner and course details to the canvas.',
    'item_action' => 'Add to certificate',
    'help' => 'Drag an item or click it to add it.',
    'empty_message' => 'No fields are available.',
    'allowed_types' => null, // null derives the allow-list from group items
    'groups' => [
        [
            'key' => 'course',
            'label' => 'Course',
            'module' => null, // or a key configured in certificates.modules
            'items' => [
                [
                    'text_type' => 'course_title',
                    'label' => 'Course Title',
                    'icon' => 'ti ti-book',
                    'sample_text' => 'Introduction to Biology',
                    'defaults' => ['font_size' => 32, 'align' => 'center', 'width' => 500],
                ],
            ],
        ],
    ],
],
```

When `allowed_types` is an array it is the explicit server-side validation allow-list. When it is `null`, types are derived from the configured group items. If the library is disabled and no allow-list exists, the package accepts host-defined element types so existing or programmatically supplied templates remain editable. Rendering resolves ordinary element types from the matching key in the data supplied by the host; `custom_text` and `qr_code` retain their special behavior.

## Issue a certificate

```php
use DavidOghi\CertificateGeneration\Facades\Certificates;
use DavidOghi\CertificateGeneration\Models\CertificateTemplate;

$issued = Certificates::issue(
    template: CertificateTemplate::findOrFail($templateId),
    recipient: $user,
    sourceType: 'course_completion',
    sourceId: $course->id,
    sourceRecordId: $completion->id,
    data: [
        'name' => $user->name,
        'course_title' => $course->title,
        'completion_date' => $completion->completed_at->format('F j, Y'),
    ],
    issuer: auth()->user(),
);
```

Issuance is idempotent for the active certificate scope + source type + source record. In the default non-tenant scope this is simply source type + source record. Calling it again returns the existing record rather than creating a duplicate.

## Render without issuing

```php
$rendered = app(\DavidOghi\CertificateGeneration\Services\CertificateManager::class)->render(
    template: $template,
    data: ['name' => 'Ada Lovelace'],
    outputDirectory: 'certificates/exports',
    certificateNumber: 'DEMO-0001',
);
```

`render()` returns the filename, certificate number, absolute output path, storage-relative path, and issue date.

## Integrating an exam, LMS, HR, or result module

Keep module policy outside this package. The host module decides when a recipient qualifies, builds its data array, and calls `issue()`. This prevents the certificate package from depending on a particular exam, course, student, or result schema.

Recommended flow:

1. Check the source record is complete and qualifies.
2. Resolve an active template supporting the source module.
3. Build the module-specific data array.
4. Call `CertificateManager::issue()`.
5. Store or expose the returned `IssuedCertificate` relation if the source module needs one.

### Attach issuance to any application event

The package includes a generic `IssueCertificate` action. `sourceType` is an application-defined string; it does not need to be a package module. Both the source and the source record may be Eloquent models or integer IDs. When no separate record is supplied, the source ID is also used as the idempotency record.

```php
use DavidOghi\CertificateGeneration\Actions\IssueCertificate;

app(IssueCertificate::class)->handle(
    template: $template,
    recipient: $event->attempt->user,
    sourceType: 'professional_test_passed',
    source: $event->test,
    sourceRecord: $event->attempt,
    data: [
        'name' => $event->attempt->user->name,
        'test_title' => $event->test->title,
        'score' => $event->attempt->score,
    ],
    issuer: $event->approvedBy,
);
```

The host can call this action from its own listener, workflow action, queued job, observer, controller, or command. For configuration-based attachment, map a host event to one or more trigger handlers:

```php
// config/certificates.php
'issuance' => [
    'triggers' => [
        \App\Events\ProfessionalTestPassed::class => [
            \App\Certificates\IssueProfessionalTestCertificate::class,
        ],
    ],
],
```

```php
namespace App\Certificates;

use App\Events\ProfessionalTestPassed;
use DavidOghi\CertificateGeneration\Actions\IssueCertificate;
use DavidOghi\CertificateGeneration\Contracts\CertificateTrigger;

class IssueProfessionalTestCertificate implements CertificateTrigger
{
    public function handle(object $event, IssueCertificate $issueCertificate): void
    {
        if (! $event instanceof ProfessionalTestPassed || ! $event->attempt->passed) {
            return;
        }

        $template = $event->test->certificateTemplate;
        if (! $template) {
            return;
        }

        $issueCertificate->handle(
            template: $template,
            recipient: $event->attempt->user,
            sourceType: 'professional_test_passed',
            source: $event->test,
            sourceRecord: $event->attempt,
            data: ['test_title' => $event->test->title, 'score' => $event->attempt->score],
        );
    }
}
```

Trigger handlers remain in the host because only the host understands qualification rules and its models. Issuance remains idempotent per certificate scope, source type, and source-record ID. A `CertificateIssued` event is dispatched after a genuinely new certificate is committed; repeated calls returning the existing certificate do not dispatch it again.

## Security behavior

- Template backgrounds, previews, and issued images use a private filesystem disk.
- Preview tokens are encrypted.
- Font files are allow-listed.
- Optional ownership/scoping is enforced by the configured `CertificateScope`; the core defaults to a non-tenant scope.
- Issued data and template settings are snapshotted for auditability.
- Certificate numbers are globally unique and QR codes point to the public verification route.
- Public files are served through controllers; raw storage paths are not exposed.

## Custom models

Extend the package models when relationships or traits are application-specific, then configure their class names:

```php
class CertificateTemplate extends \DavidOghi\CertificateGeneration\Models\CertificateTemplate
{
    use BelongsToOrganisation;
}
```

```php
'models' => [
    'template' => App\Models\CertificateTemplate::class,
    'issued_certificate' => App\Models\IssuedCertificate::class,
    'user' => App\Models\User::class,
],
```

## Publishing the package

The directory is a complete Composer package. Put it in its own Git repository, choose a vendor/package name in `composer.json`, tag a semantic version, and publish through Packagist or install from a private Composer repository.
