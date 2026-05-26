<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Madbox99\FilamentFormBuilder\Filament\Pages\FormJsonSchemaHelp;
use Madbox99\FilamentFormBuilder\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\RegistrationFormResource;

final class FilamentFormBuilderPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'filament-form-builder';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if ((bool) config('filament-form-builder.filament.register_forms_resource', true)) {
            $resources[] = RegistrationFormResource::class;
        }

        if ((bool) config('filament-form-builder.filament.register_submissions_resource', true)) {
            $resources[] = FormSubmissionResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        if ((bool) config('filament-form-builder.filament.register_help_page', true)) {
            $panel->pages([FormJsonSchemaHelp::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
