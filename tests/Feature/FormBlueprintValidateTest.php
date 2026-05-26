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

it('rejects empty fields', function (): void {
    $payload = validPayload();
    $payload['fields'] = [];

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects an unknown field type', function (): void {
    $payload = validPayload();
    $payload['fields'] = [[
        'type' => 'wat',
        'data' => ['label' => 'X', 'name' => 'x', 'required' => false],
    ]];

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects a field name with invalid characters', function (): void {
    $payload = validPayload();
    $payload['fields'] = [[
        'type' => 'text_input',
        'data' => ['label' => 'X', 'name' => 'bad-name', 'required' => false],
    ]];

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects a select field without options', function (): void {
    $payload = validPayload();
    $payload['fields'] = [[
        'type' => 'select',
        'data' => ['label' => 'X', 'name' => 'x', 'required' => false],
    ]];

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('accepts a select field with options', function (): void {
    $payload = validPayload();
    $payload['fields'] = [[
        'type' => 'select',
        'data' => [
            'label' => 'Choice',
            'name' => 'choice',
            'required' => false,
            'options' => [
                ['label' => 'One', 'value' => 'one'],
                ['label' => 'Two', 'value' => 'two'],
            ],
        ],
    ]];

    FormBlueprint::validate($payload);

    expect(true)->toBeTrue();
});

it('rejects an invalid redirect_url', function (): void {
    $payload = validPayload();
    $payload['redirect_url'] = 'not a url';

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects a non-boolean is_active', function (): void {
    $payload = validPayload();
    $payload['is_active'] = 'sometimes';

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('rejects custom_css over 65535 chars', function (): void {
    $payload = validPayload();
    $payload['custom_css'] = str_repeat('a', 65536);

    expect(fn () => FormBlueprint::validate($payload))
        ->toThrow(ValidationException::class);
});

it('sanitizes custom_css through CssSanitizer', function (): void {
    $payload = validPayload();
    $payload['custom_css'] = '@import url("evil");';

    $clean = FormBlueprint::sanitize($payload);

    expect($clean['custom_css'])->not->toContain('@import');
});

it('normalises empty strings to null for nullable scalar fields', function (): void {
    $payload = validPayload();
    $payload['description'] = '';
    $payload['redirect_url'] = '';
    $payload['thank_you_message'] = '';
    $payload['custom_css'] = '';

    $clean = FormBlueprint::sanitize($payload);

    expect($clean['description'])->toBeNull()
        ->and($clean['redirect_url'])->toBeNull()
        ->and($clean['thank_you_message'])->toBeNull()
        ->and($clean['custom_css'])->toBeNull();
});
