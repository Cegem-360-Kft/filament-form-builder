<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model representing the "owner" of a form. Set to `null` to
    | disable tenant scoping entirely (single-tenant installation).
    |
    */
    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Tenant Foreign Key
    |--------------------------------------------------------------------------
    |
    | The column name on the registration_forms / form_submissions tables that
    | references the tenant. Defaults to `team_id` for zero-migration upgrades.
    |
    */
    'tenant_foreign_key' => 'team_id',

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Public form routes. `/forms/{slug}` renders a full-page form; `/embed/forms/{slug}`
    | serves an iframe-friendly version; `/forms/embed.js` serves the widget loader.
    |
    */
    'routes' => [
        'enabled' => true,
        'middleware' => ['web'],
        'show_path' => 'forms/{slug}',
        'embed_path' => 'embed/forms/{slug}',
        'widget_script_path' => 'forms/embed.js',
        'throttle' => [
            'show' => '60,1',
            'embed' => '60,1',
            'script' => '120,1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission rate limiting
    |--------------------------------------------------------------------------
    |
    | Per-IP limit for public form submissions (attempts per decay window).
    |
    */
    'submission_rate_limit' => [
        'attempts' => 5,
        'decay_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Embed security
    |--------------------------------------------------------------------------
    |
    | frame_ancestors:
    |   Content-Security-Policy frame-ancestors source list. The default `'*'`
    |   allows any site to embed the iframe, which is usually the point of a
    |   form widget. For production, set to an explicit allowlist such as
    |   `"https://example.com https://*.example.com"`.
    |
    | iframe_sandbox:
    |   `sandbox` attribute applied to the <iframe> the widget creates on the
    |   host page. `allow-forms allow-scripts allow-same-origin` is the minimum
    |   needed for Livewire to work. Set to null to disable (not recommended).
    |
    */
    'embed' => [
        'frame_ancestors' => '*',
        'iframe_sandbox' => 'allow-forms allow-scripts allow-same-origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom CSS
    |--------------------------------------------------------------------------
    |
    | Per-form CSS, edited in the admin via a CodeEditor. The stored CSS is
    | sanitised (no `@import`, no `url(javascript:...)`, no `</style>` escapes)
    | and scope-prefixed with the form container selector, so a user cannot
    | target `body` or elements outside the iframe. `max_length` bounds the
    | stored CSS blob.
    |
    */
    'custom_css' => [
        'enabled' => true,
        'max_length' => 20000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    */
    'filament' => [
        'scoped_to_tenant' => true,
        'navigation_group' => 'Forms',
        'register_forms_resource' => true,
        'register_submissions_resource' => true,
        'register_help_page' => true,
    ],

];
