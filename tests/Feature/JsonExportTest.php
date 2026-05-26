<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ExportFormAction;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

it('exports a form as a streamed JSON download', function (): void {
    $form = RegistrationForm::factory()->create(['slug' => 'my-form']);

    $response = ExportFormAction::download($form);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('application/json');
    expect($response->headers->get('Content-Disposition'))->toContain('my-form.json');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $decoded = json_decode($body, true);
    expect($decoded)->toHaveKey('schema_version', 1)
        ->toHaveKey('slug', 'my-form');
});
