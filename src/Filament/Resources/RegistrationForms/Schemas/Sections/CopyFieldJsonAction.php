<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Schemas\Sections;

use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Madbox99\FilamentFormBuilder\Support\FieldJsonExporter;

final class CopyFieldJsonAction
{
    public static function make(): Action
    {
        return Action::make('copy_json')
            ->label(__('filament-form-builder::form.actions.copy_field_json'))
            ->icon(Heroicon::ClipboardDocument)
            ->color('gray')
            ->action(function (array $arguments, Builder $component): void {
                $state = $component->getRawItemState($arguments['item']);
                $json = FieldJsonExporter::encode(is_array($state) ? $state : null);

                if ($json === null) {
                    return;
                }

                $component->getLivewire()->dispatch('ffb-copy-to-clipboard', json: $json);

                Notification::make()
                    ->title(__('filament-form-builder::form.notifications.field_copied'))
                    ->success()
                    ->send();
            });
    }
}
