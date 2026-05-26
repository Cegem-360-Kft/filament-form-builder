<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Schemas\Sections;

use Filament\Forms\Components\Builder;
use Filament\Schemas\Components\Section;

final class FieldsSection
{
    public static function make(): Section
    {
        return Section::make(__('filament-form-builder::form.sections.form_fields'))
            ->schema([
                Builder::make('fields')
                    ->label(__('filament-form-builder::form.sections.form_fields'))
                    ->blockNumbers(false)
                    ->blockIcons()
                    ->blocks(FieldBlocks::all())
                    ->extraItemActions([
                        CopyFieldJsonAction::make(),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->required(),
            ]);
    }
}
