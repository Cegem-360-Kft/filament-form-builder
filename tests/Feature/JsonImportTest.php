<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ImportFormAction;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;

uses(RefreshDatabase::class);

it('creates a form from a valid payload', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['name'] = 'Imported';
    $payload['slug'] = 'imported';

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form)->toBeInstanceOf(RegistrationForm::class);
    expect($form->name)->toBe('Imported');
    expect($form->slug)->toBe('imported');
    expect($form->is_active)->toBeTrue();
    expect($form->submissions_count)->toBe(0);
    expect(RegistrationForm::count())->toBe(1);
});

it('defaults missing schema_version to the current version', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'no-schema-version';
    unset($payload['schema_version']);

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form)->toBeInstanceOf(RegistrationForm::class);
    expect($form->slug)->toBe('no-schema-version');
});

it('still rejects an unsupported schema_version', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'bad-schema-version';
    $payload['schema_version'] = 99;

    expect(fn () => (new ImportFormFromJson)->execute($payload))
        ->toThrow(ValidationException::class);

    expect(RegistrationForm::count())->toBe(0);
});

it('uniquifies the slug on collision', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'lead-capture';

    (new ImportFormFromJson)->execute($payload);
    $second = (new ImportFormFromJson)->execute($payload);

    expect($second->slug)->toBe('lead-capture-2');
    expect($second->name)->toBe($payload['name']);
});

it('handles multiple collisions in sequence', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'collide';

    (new ImportFormFromJson)->execute($payload);
    (new ImportFormFromJson)->execute($payload);
    $third = (new ImportFormFromJson)->execute($payload);

    expect($third->slug)->toBe('collide-3');
});

it('throws ValidationException on invalid payload', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'Not Valid';

    expect(fn () => (new ImportFormFromJson)->execute($payload))
        ->toThrow(ValidationException::class);

    expect(RegistrationForm::count())->toBe(0);
});

it('sanitizes custom_css before persisting', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'css-test';
    $payload['custom_css'] = '@import url("evil");';

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form->custom_css)->not->toContain('@import');
});

it('ImportFormAction::make() returns a configured Filament action', function (): void {
    $action = ImportFormAction::make();

    expect($action)->toBeInstanceOf(Action::class);
    expect($action->getName())->toBe('import_json');
});

it('ImportFormAction::handle() creates a form from textarea JSON', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'from-textarea';

    $form = ImportFormAction::handle(json: json_encode($payload), file: null);

    expect($form)->not->toBeNull();
    expect($form->slug)->toBe('from-textarea');
});

it('ImportFormAction::handle() rejects malformed JSON', function (): void {
    expect(fn () => ImportFormAction::handle(json: '{not-json', file: null))
        ->toThrow(ValidationException::class);
});
