<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Support;

use Closure;
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
        $serialized = $form->attributesToArray();

        $payload = ['schema_version' => self::SCHEMA_VERSION];

        foreach (self::DATA_KEYS as $key) {
            $payload[$key] = $serialized[$key] ?? null;
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
        Validator::make($payload, self::rules($payload))->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        foreach (['description', 'redirect_url', 'thank_you_message', 'custom_css'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) === '') {
                $payload[$key] = null;
            }
        }

        if (isset($payload['custom_css']) && is_string($payload['custom_css'])) {
            $payload['custom_css'] = CssSanitizer::sanitize($payload['custom_css']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function rules(array $payload): array
    {
        $allowedTypes = implode(',', FormFieldBlueprint::TYPES);
        $typesNeedingOptions = ['select', 'checkbox_list', 'radio'];

        return [
            'schema_version' => ['required', 'integer', 'in:'.self::SCHEMA_VERSION],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.type' => ['required', 'string', 'in:'.$allowedTypes],
            'fields.*.data' => [
                'required',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($payload, $typesNeedingOptions): void {
                    $parts = explode('.', $attribute);
                    $index = $parts[1] ?? null;
                    $type = data_get($payload, "fields.{$index}.type");
                    if (! in_array($type, $typesNeedingOptions, true)) {
                        return;
                    }
                    $options = is_array($value) ? ($value['options'] ?? null) : null;
                    if (! is_array($options) || $options === []) {
                        $fail("The {$attribute}.options field is required for {$type} fields.");
                    }
                },
            ],
            'fields.*.data.label' => ['required', 'string', 'max:255'],
            'fields.*.data.name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'fields.*.data.required' => ['nullable', 'boolean'],
            'fields.*.data.options' => ['nullable', 'array'],
            'fields.*.data.options.*.label' => ['required', 'string', 'max:255'],
            'fields.*.data.options.*.value' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\-]+$/',
            ],
            'submission_actions' => ['nullable', 'array'],
            'design_tokens' => ['nullable', 'array'],
            'custom_css' => ['nullable', 'string', 'max:65535'],
            'thank_you_message' => ['nullable', 'string'],
            'redirect_url' => ['nullable', 'string', 'max:2048', 'url'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
