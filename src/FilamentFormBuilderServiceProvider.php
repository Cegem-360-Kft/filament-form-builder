<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Madbox99\FilamentFormBuilder\Contracts\FormTenantResolver;
use Madbox99\FilamentFormBuilder\Livewire\PublicRegistrationForm;
use Madbox99\FilamentFormBuilder\Support\EloquentTenantResolver;

final class FilamentFormBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-form-builder.php',
            'filament-form-builder'
        );

        $this->app->singleton(FormTenantResolver::class, function ($app) {
            /** @var class-string|null $tenantModel */
            $tenantModel = config('filament-form-builder.tenant_model');
            $slugColumn = (string) config('filament-form-builder.tenant_slug_column', 'slug');

            return new EloquentTenantResolver($tenantModel, $slugColumn);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/filament-form-builder.php' => config_path('filament-form-builder.php'),
        ], 'filament-form-builder-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'filament-form-builder-migrations');

        $this->publishes([
            __DIR__.'/../resources/views/' => resource_path('views/vendor/filament-form-builder'),
        ], 'filament-form-builder-views');

        $this->publishes([
            __DIR__.'/../resources/lang/' => lang_path('vendor/filament-form-builder'),
        ], 'filament-form-builder-translations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-form-builder');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-form-builder');

        Livewire::component('filament-form-builder.public-registration-form', PublicRegistrationForm::class);

        FilamentAsset::register([
            Js::make('ffb-copy-to-clipboard', __DIR__.'/../resources/js/copy-to-clipboard.js'),
        ], 'madbox99/filament-form-builder');

        if ((bool) config('filament-form-builder.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
