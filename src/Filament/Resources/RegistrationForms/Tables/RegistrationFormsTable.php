<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ExportFormAction;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

final class RegistrationFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-form-builder::form.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('filament-form-builder::form.fields.slug'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament-form-builder::form.fields.active'))
                    ->boolean(),
                TextColumn::make('submissions_count')
                    ->label(__('filament-form-builder::form.fields.submissions_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-form-builder::form.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label(__('filament-form-builder::form.actions.preview'))
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->url(fn (RegistrationForm $record): string => $record->getPublicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (RegistrationForm $record): bool => (bool) $record->is_active),
                ExportFormAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
