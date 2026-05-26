<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Tests;

use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Madbox99\FilamentFormBuilder\FilamentFormBuilderServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentFormBuilderServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
