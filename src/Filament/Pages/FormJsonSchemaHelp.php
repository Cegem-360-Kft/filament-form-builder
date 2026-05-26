<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;

final class FormJsonSchemaHelp extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::QuestionMarkCircle;

    protected string $view = 'filament-form-builder::pages.form-json-schema-help';

    public static function getNavigationLabel(): string
    {
        return __('filament-form-builder::form.help.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-form-builder::form.help.title');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFieldExamples(): array
    {
        return FormBlueprintSchema::fieldExamples();
    }

    public function getFullExampleJson(): string
    {
        return (string) json_encode(
            FormBlueprintSchema::fullExample(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
