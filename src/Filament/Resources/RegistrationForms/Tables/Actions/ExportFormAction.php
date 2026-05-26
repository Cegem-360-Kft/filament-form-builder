<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Madbox99\FilamentFormBuilder\Actions\ExportFormAsJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportFormAction
{
    public static function make(): Action
    {
        return Action::make('export_json')
            ->label(__('filament-form-builder::form.actions.export_json'))
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->action(fn (RegistrationForm $record): StreamedResponse => self::download($record));
    }

    public static function download(RegistrationForm $form): StreamedResponse
    {
        $payload = (new ExportFormAsJson)->execute($form);
        $json = (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $filename = $form->slug.'.json';

        return new StreamedResponse(
            static function () use ($json): void {
                echo $json;
            },
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }
}
