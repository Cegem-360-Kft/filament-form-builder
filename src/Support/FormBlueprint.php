<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Support;

use Illuminate\Support\Facades\Validator;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

final class FormBlueprint
{
    public const SCHEMA_VERSION = 1;

    /**
     * Keys serialized into the JSON payload, in order.
     *
     * @var list<string>
     */
    private const DATA_KEYS = [
        'name',
        'slug',
        'description',
        'fields',
        'submission_actions',
        'thank_you_message',
        'redirect_url',
        'custom_css',
        'design_tokens',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(RegistrationForm $form): array
    {
        $payload = ['schema_version' => self::SCHEMA_VERSION];

        foreach (self::DATA_KEYS as $key) {
            $payload[$key] = $form->getAttribute($key);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $payload): void
    {
        Validator::make($payload, self::rules())->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private static function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:'.self::SCHEMA_VERSION],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
        ];
    }
}
