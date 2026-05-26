<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

final class ImportFormAction
{
    public static function make(): Action
    {
        return Action::make('import_json')
            ->label(__('filament-form-builder::form.actions.import_json'))
            ->icon(Heroicon::ArrowUpTray)
            ->modalSubmitActionLabel(__('filament-form-builder::form.actions.import_json'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('filament-form-builder::form.fields.json_file'))
                    ->acceptedFileTypes(['application/json'])
                    ->storeFiles(false),
                Textarea::make('json')
                    ->label(__('filament-form-builder::form.fields.json_text'))
                    ->rows(10),
            ])
            ->action(function (array $data): void {
                /** @var ?UploadedFile $file */
                $file = $data['file'] ?? null;
                $json = is_string($data['json'] ?? null) ? $data['json'] : null;

                try {
                    $form = self::handle($json, $file);
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title(__('filament-form-builder::form.notifications.json_invalid'))
                        ->body(implode("\n", array_map(
                            static fn (array $msgs): string => implode("\n", $msgs),
                            $e->errors(),
                        )))
                        ->danger()
                        ->send();

                    throw $e;
                }

                Notification::make()
                    ->title(__('filament-form-builder::form.notifications.imported', ['slug' => $form->slug]))
                    ->success()
                    ->send();
            });
    }

    public static function handle(?string $json, ?UploadedFile $file): RegistrationForm
    {
        $raw = match (true) {
            $file !== null => (string) file_get_contents($file->getRealPath()),
            is_string($json) && trim($json) !== '' => $json,
            default => throw ValidationException::withMessages([
                'json' => __('filament-form-builder::form.notifications.json_invalid'),
            ]),
        };

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'json' => __('filament-form-builder::form.notifications.json_invalid'),
            ]);
        }

        return (new ImportFormFromJson)->execute($payload);
    }
}
