<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Madbox99\FilamentFormBuilder\Actions\ExportFormAsJson;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

uses(RefreshDatabase::class);

it('preserves the data scope across export then import', function (): void {
    $original = RegistrationForm::factory()->create([
        'name' => 'Round trip',
        'slug' => 'round-trip',
        'description' => 'desc',
        'thank_you_message' => 'thanks',
    ]);

    $payload = (new ExportFormAsJson)->execute($original);
    $payload['slug'] = 'round-trip-import';

    $imported = (new ImportFormFromJson)->execute($payload);

    expect($imported->name)->toBe($original->name);
    expect($imported->description)->toBe($original->description);
    expect($imported->fields)->toEqual($original->fields);
    expect($imported->thank_you_message)->toBe($original->thank_you_message);
    expect($imported->is_active)->toBe($original->is_active);
    expect($imported->id)->not->toBe($original->id);
});
