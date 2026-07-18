<?php

use App\Models\User;
use DavidOghi\CertificateGeneration\Models\CertificateTemplate;
use DavidOghi\CertificateGeneration\Models\IssuedCertificate;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateActorResolver;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateAuthorizationResolver;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateContext;
use DavidOghi\CertificateGeneration\Support\DefaultCertificateNumberGenerator;
use DavidOghi\CertificateGeneration\Support\NullCertificateScope;
use DavidOghi\CertificateGeneration\Support\RouteVerificationUrlGenerator;

return [
    'layout' => 'layouts.app',
    'content_section' => 'content',

    'models' => [
        'user' => User::class,
        'template' => CertificateTemplate::class,
        'issued_certificate' => IssuedCertificate::class,
    ],

    'tables' => [
        'templates' => 'certificate_templates',
        'issued_certificates' => 'issued_certificates',
    ],

    // The core is non-tenant. Applications that need tenancy opt into the
    // separate ColumnCertificateScope adapter and publish its migration.
    'scope' => NullCertificateScope::class,
    'tenancy' => [
        'column' => 'tenant_id',
        'actor_column' => 'tenant_id',
        'resolver' => null,
    ],

    'authorization' => [
        'context' => DefaultCertificateContext::class,
        'actor_resolver' => DefaultCertificateActorResolver::class,
        'permission_resolver' => DefaultCertificateAuthorizationResolver::class,
    ],

    'routes' => [
        'enabled' => true,
        'management_enabled' => true,
        'verification_enabled' => true,
        'download_enabled' => true,
        'preview_enabled' => true,
        'prefix' => 'certificates',
        'management_prefix' => 'manage',
        'admin_prefix' => null,
        'middleware' => ['web'],
        'public_middleware' => ['web'],
        'name' => 'certificates.',
    ],

    'storage' => [
        'disk' => 'local',
        'template_directory' => 'certificates/templates',
        'preview_directory' => 'certificates/previews',
        'issued_directory' => 'certificates/issued',
    ],

    'uploads' => [
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_kb' => 10240,
    ],

    'designer' => [
        'canvas' => ['width' => 1123, 'height' => 794, 'orientation' => 'landscape'],
        'grid_size' => 10,
        'element_defaults' => [
            'width' => 320, 'height' => 80, 'size' => 120,
            'font_size' => 36, 'font_weight' => 400, 'color' => '#000000',
            'align' => 'left', 'opacity' => 1, 'line_height' => 1.2,
            'letter_spacing' => 0,
        ],
    ],

    'rendering' => [
        'format' => 'jpg', // png, jpg, jpeg, or webp
        'issued_date_format' => 'jS \\d\\a\\y \\o\\f F, Y',
        'storage_date_format' => 'Y/m/d',
    ],

    'certificate_numbers' => [
        'generator' => DefaultCertificateNumberGenerator::class,
        'prefix' => 'CERT',
        'separator' => '-',
        'include_scope' => true,
        'include_year' => true,
        'random_length' => 8,
        'max_attempts' => 25,
    ],

    'verification_urls' => [
        'generator' => RouteVerificationUrlGenerator::class,
    ],

    'templates' => [
        // "restrict" preserves issued history; "cascade" removes issued rows
        // and files before deleting a template.
        'delete_policy' => 'restrict',
    ],

    // Optional host-domain event => CertificateTrigger class mappings. A
    // trigger decides eligibility, template, recipient and payload, then calls
    // the generic IssueCertificate action passed to it.
    'issuance' => [
        'triggers' => [],
    ],

    'ui' => [
        // The host can display this to verify which package build is deployed.
        'package_version' => env('CERTIFICATES_PACKAGE_VERSION', '2.0.0'),
        'bootstrap_version' => 4,
        'designer_badge' => 'Certificate Designer',
        'create_title' => 'Create Certificate Template',
        'edit_title' => 'Edit Certificate Template',
        'designer_description' => 'Build reusable certificates with drag-and-drop positioning, server preview, and one JSON settings payload.',
        'save_label' => 'Save Template',
    ],

    // Optional. Empty means templates are not restricted to host modules.
    'modules' => [],

    'sample_data' => [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'staffID' => 'STF-000',
        'certificate_number' => 'CERT-00129',
        'date_issued' => '14 July 2026',
        'organisation_name' => 'Example Organisation',
        'organisation_address' => '12 Example Street',
        'organisation_phone' => '+1 555 0100',
        'organisation_email' => 'hello@example.com',
    ],

    'module_sample_data' => [],

    // Optional and completely host-defined. Set enabled to false (or groups to
    // an empty array) when templates should only edit existing elements.
    'element_library' => [
        'enabled' => true,
        'eyebrow' => 'Library',
        'title' => 'Elements Library',
        'description' => 'Drag fields and static items onto the certificate canvas.',
        'item_action' => 'Drag to canvas',
        'help' => 'Drag an item onto the certificate or click it to place it at the current viewport center.',
        'empty_message' => 'No certificate elements are configured.',
        // Null derives the validation allow-list from the group items below.
        'allowed_types' => null,
        'groups' => [
            [
                'key' => 'general',
                'label' => 'General',
                'module' => null,
                'items' => [
                    ['text_type' => 'name', 'label' => 'Recipient Name', 'sample_text' => 'Ada Lovelace'],
                    ['text_type' => 'email', 'label' => 'Recipient Email', 'sample_text' => 'ada@example.com'],
                    ['text_type' => 'staffID', 'label' => 'Staff ID', 'sample_text' => 'STF-000'],
                    ['text_type' => 'certificate_number', 'label' => 'Certificate Number', 'sample_text' => 'CERT-00129'],
                    ['text_type' => 'date_issued', 'label' => 'Date Issued', 'sample_text' => '14 July 2026'],
                    ['text_type' => 'organisation_name', 'label' => 'Organisation Name', 'sample_text' => 'Example Organisation'],
                    ['text_type' => 'organisation_address', 'label' => 'Organisation Address', 'sample_text' => '12 Example Street'],
                    ['text_type' => 'custom_text', 'label' => 'Custom Text', 'sample_text' => 'Congratulations!'],
                    ['text_type' => 'qr_code', 'label' => 'QR Code', 'sample_text' => 'QR CODE'],
                ],
            ],
        ],
    ],

    // Map the designer's font filename to an absolute TTF path. This fork
    // keeps the legacy project fonts available out of the box.
    'fonts' => [
        'Times-New-Roman.ttf' => [
            'label' => 'Times New Roman',
            'path' => base_path('public/certificate_fonts/Times-New-Roman.ttf'),
        ],
        'Times-New-Roman-Bold.ttf' => [
            'label' => 'Times New Roman Bold',
            'path' => base_path('public/certificate_fonts/Times-New-Roman-Bold.ttf'),
        ],
        'Pesaro-Bold.ttf' => [
            'label' => 'Pesaro-Bold',
            'path' => base_path('public/certificate_fonts/Pesaro-Bold.ttf'),
        ],
        'Edwardian-Script-ITC.ttf' => [
            'label' => 'Edwardian Script ITC',
            'path' => base_path('public/certificate_fonts/Edwardian-Script-ITC.ttf'),
        ],
    ],
];
