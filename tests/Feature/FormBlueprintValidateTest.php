<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Madbox99\FilamentFormBuilder\Support\FormBlueprint;

function validPayload(): array
{
    return [
        'schema_version' => 1,
        'name' => 'Test form',
        'slug' => 'test-form',
        'description' => null,
        'fields' => [
            [
                'type' => 'text_input',
                'data' => ['label' => 'Name', 'name' => 'name', 'required' => true],
            ],
        ],
        'submission_actions' => null,
        'thank_you_message' => null,
        'redirect_url' => null,
        'custom_css' => null,
        'design_tokens' => null,
        'is_active' => true,
    ];
}

it('rejects missing schema_version', function (): void {
    $payload = validPayload();
    unset($payload['schema_version']);

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects unsupported schema_version', function (): void {
    $payload = validPayload();
    $payload['schema_version'] = 99;

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('accepts a minimal valid payload', function (): void {
    FormBlueprint::validate(validPayload());

    expect(true)->toBeTrue();
});

it('rejects missing name', function (): void {
    $payload = validPayload();
    unset($payload['name']);

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects too-long name', function (): void {
    $payload = validPayload();
    $payload['name'] = str_repeat('a', 256);

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects an invalid slug', function (): void {
    $payload = validPayload();
    $payload['slug'] = 'Bad Slug!';

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects missing slug', function (): void {
    $payload = validPayload();
    unset($payload['slug']);

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});
