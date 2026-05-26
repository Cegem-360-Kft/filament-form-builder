# JSON form import/export, field copy, and schema help page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship JSON-based form definition import/export in the Filament admin, a per-field "copy as JSON" action in the Builder, and an admin help page documenting the JSON schema. Spec: `docs/superpowers/specs/2026-05-26-json-import-export-and-field-copy-design.md`.

**Architecture:** A new `Support\FormBlueprint` is the single source of truth for the on-disk JSON shape (serialize/deserialize + Laravel validation rules). Two action classes (`Actions\ExportFormAsJson`, `Actions\ImportFormFromJson`) bridge model ↔ array. Filament Actions (`ImportFormAction` header action, `ExportFormAction` row action) wrap the use cases for the UI. `FieldBlocks` gains `extraItemActions()` for clipboard copy. A `FormJsonSchemaHelp` Filament Page renders `Support\FormBlueprintSchema` examples.

**Tech Stack:** Laravel 11/12/13, Filament 5.6, Livewire 3/4, Pest 3/4, PHP 8.3.

---

## File structure

**Create:**
- `src/Support/FormBlueprint.php`
- `src/Support/FormBlueprintSchema.php`
- `src/Actions/ExportFormAsJson.php`
- `src/Actions/ImportFormFromJson.php`
- `src/Filament/Resources/RegistrationForms/Tables/Actions/ImportFormAction.php`
- `src/Filament/Resources/RegistrationForms/Tables/Actions/ExportFormAction.php`
- `src/Filament/Pages/FormJsonSchemaHelp.php`
- `resources/views/pages/form-json-schema-help.blade.php`
- `tests/Unit/FormBlueprintValidateTest.php`
- `tests/Unit/FormBlueprintFromModelTest.php`
- `tests/Unit/FormBlueprintSchemaTest.php`
- `tests/Unit/ExportFormAsJsonTest.php`
- `tests/Unit/ImportFormFromJsonTest.php`
- `tests/Unit/CopyFieldJsonActionTest.php`
- `tests/Feature/JsonExportTest.php`
- `tests/Feature/JsonImportTest.php`
- `tests/Feature/JsonRoundTripTest.php`

**Modify:**
- `src/Filament/Resources/RegistrationForms/Schemas/Sections/FieldBlocks.php` — add `extraItemActions()` per block
- `src/Filament/Resources/RegistrationForms/Tables/RegistrationFormsTable.php` — wire `ExportFormAction` row action
- `src/Filament/Resources/RegistrationForms/Pages/ListRegistrationForms.php` — wire `ImportFormAction` header action
- `src/FilamentFormBuilderPlugin.php` — register `FormJsonSchemaHelp` page
- `resources/lang/en/form.php`, `resources/lang/hu/form.php` — new strings

---

## Task 1: `FormBlueprint::fromModel()` extracts the data scope

**Files:**
- Create: `src/Support/FormBlueprint.php`
- Create: `tests/Unit/FormBlueprintFromModelTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/FormBlueprintFromModelTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprint;

it('extracts only the data-scope keys plus schema_version', function (): void {
    $form = RegistrationForm::factory()->make([
        'name' => 'Lead capture',
        'slug' => 'lead-capture',
        'description' => 'desc',
        'thank_you_message' => 'thanks',
        'redirect_url' => null,
        'custom_css' => '.x{color:red}',
        'is_active' => true,
    ]);
    $form->id = 999;
    $form->submissions_count = 42;

    $payload = FormBlueprint::fromModel($form);

    expect($payload)
        ->toHaveKey('schema_version', 1)
        ->toHaveKey('name', 'Lead capture')
        ->toHaveKey('slug', 'lead-capture')
        ->toHaveKey('description', 'desc')
        ->toHaveKey('thank_you_message', 'thanks')
        ->toHaveKey('redirect_url', null)
        ->toHaveKey('custom_css', '.x{color:red}')
        ->toHaveKey('is_active', true)
        ->toHaveKey('fields')
        ->not->toHaveKey('id')
        ->not->toHaveKey('submissions_count')
        ->not->toHaveKey('created_at')
        ->not->toHaveKey('updated_at')
        ->not->toHaveKey('deleted_at');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/FormBlueprintFromModelTest.php`
Expected: FAIL with "Class Madbox99\FilamentFormBuilder\Support\FormBlueprint not found".

- [ ] **Step 3: Implement `FormBlueprint::fromModel()` and the `SCHEMA_VERSION` constant**

Create `src/Support/FormBlueprint.php`:

```php
<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Support;

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
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintFromModelTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintFromModelTest.php
git commit -m "feat(blueprint): extract form data scope into FormBlueprint::fromModel"
```

---

## Task 2: `FormBlueprint::validate()` enforces schema_version

**Files:**
- Modify: `src/Support/FormBlueprint.php`
- Create: `tests/Unit/FormBlueprintValidateTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/FormBlueprintValidateTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: FAIL with "Method ... validate ... does not exist".

- [ ] **Step 3: Implement `FormBlueprint::validate()` minimally (schema_version only)**

Add to `src/Support/FormBlueprint.php` (above the closing brace):

```php
    use Illuminate\Support\Facades\Validator;

    // ...

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
        ];
    }
```

Also add the `use Illuminate\Support\Facades\Validator;` to the top of the file (next to existing imports).

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: PASS (the third test passes because the validator only checks `schema_version`).

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintValidateTest.php
git commit -m "feat(blueprint): validate schema_version"
```

---

## Task 3: `FormBlueprint::validate()` enforces name and slug

**Files:**
- Modify: `src/Support/FormBlueprint.php`
- Modify: `tests/Unit/FormBlueprintValidateTest.php`

- [ ] **Step 1: Append failing tests**

Append to `tests/Unit/FormBlueprintValidateTest.php`:

```php
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
```

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: 4 failures (no rules enforce name/slug yet).

- [ ] **Step 3: Extend rules**

Update `rules()` in `src/Support/FormBlueprint.php`:

```php
    private static function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:'.self::SCHEMA_VERSION],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
        ];
    }
```

- [ ] **Step 4: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintValidateTest.php
git commit -m "feat(blueprint): validate name and slug"
```

---

## Task 4: `FormBlueprint::validate()` enforces fields shape

**Files:**
- Modify: `src/Support/FormBlueprint.php`
- Modify: `tests/Unit/FormBlueprintValidateTest.php`

- [ ] **Step 1: Append failing tests**

Append to `tests/Unit/FormBlueprintValidateTest.php`:

```php
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
```

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: at least the first three new tests fail. (Select tests may incidentally pass — that's fine; we still add the rules.)

- [ ] **Step 3: Extend rules to cover fields**

Update `rules()` in `src/Support/FormBlueprint.php`:

```php
    private static function rules(): array
    {
        $allowedTypes = implode(',', \Madbox99\FilamentFormBuilder\Support\FormFieldBlueprint::TYPES);
        $typesNeedingOptions = ['select', 'checkbox_list', 'radio'];

        return [
            'schema_version' => ['required', 'integer', 'in:'.self::SCHEMA_VERSION],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.type' => ['required', 'string', 'in:'.$allowedTypes],
            'fields.*.data' => ['required', 'array'],
            'fields.*.data.label' => ['required', 'string', 'max:255'],
            'fields.*.data.name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'fields.*.data.required' => ['nullable', 'boolean'],
            'fields.*.data.options' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($typesNeedingOptions): void {
                    // path is "fields.N.data.options" — find the type at fields.N.type
                    $parts = explode('.', $attribute);
                    $index = $parts[1] ?? null;
                    $payload = request()->all();
                    $type = data_get($payload, "fields.{$index}.type");
                    if (in_array($type, $typesNeedingOptions, true) && (! is_array($value) || $value === [])) {
                        $fail(__('validation.required', ['attribute' => $attribute]));
                    }
                },
            ],
            'fields.*.data.options.*.label' => ['required_with:fields.*.data.options', 'string', 'max:255'],
            'fields.*.data.options.*.value' => [
                'required_with:fields.*.data.options',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\-]+$/',
            ],
        ];
    }
```

The `request()->all()` trick won't work in unit-test context — replace it with an injected payload. Refactor to pass the payload explicitly:

```php
    public static function validate(array $payload): void
    {
        Validator::make($payload, self::rules($payload))->validate();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function rules(array $payload): array
    {
        $allowedTypes = implode(',', \Madbox99\FilamentFormBuilder\Support\FormFieldBlueprint::TYPES);
        $typesNeedingOptions = ['select', 'checkbox_list', 'radio'];

        return [
            'schema_version' => ['required', 'integer', 'in:'.self::SCHEMA_VERSION],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.type' => ['required', 'string', 'in:'.$allowedTypes],
            'fields.*.data' => ['required', 'array'],
            'fields.*.data.label' => ['required', 'string', 'max:255'],
            'fields.*.data.name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'fields.*.data.required' => ['nullable', 'boolean'],
            'fields.*.data.options' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($payload, $typesNeedingOptions): void {
                    $parts = explode('.', $attribute);
                    $index = $parts[1] ?? null;
                    $type = data_get($payload, "fields.{$index}.type");
                    if (in_array($type, $typesNeedingOptions, true) && (! is_array($value) || $value === [])) {
                        $fail("The {$attribute} field is required for {$type} fields.");
                    }
                },
            ],
            'fields.*.data.options.*.label' => ['required', 'string', 'max:255'],
            'fields.*.data.options.*.value' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._\-]+$/',
            ],
        ];
    }
```

- [ ] **Step 4: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintValidateTest.php
git commit -m "feat(blueprint): validate fields, types, and option shapes"
```

---

## Task 5: `FormBlueprint::validate()` covers remaining nullable fields

**Files:**
- Modify: `src/Support/FormBlueprint.php`
- Modify: `tests/Unit/FormBlueprintValidateTest.php`

- [ ] **Step 1: Append failing tests**

```php
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
```

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: 3 new tests fail.

- [ ] **Step 3: Extend rules**

Add to the `rules()` return array in `src/Support/FormBlueprint.php`:

```php
            'submission_actions' => ['nullable', 'array'],
            'design_tokens' => ['nullable', 'array'],
            'custom_css' => ['nullable', 'string', 'max:65535'],
            'thank_you_message' => ['nullable', 'string'],
            'redirect_url' => ['nullable', 'string', 'max:2048', 'url'],
            'is_active' => ['required', 'boolean'],
```

- [ ] **Step 4: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintValidateTest.php
git commit -m "feat(blueprint): validate optional and remaining fields"
```

---

## Task 6: `FormBlueprint::sanitize()` strips dangerous CSS

**Files:**
- Modify: `src/Support/FormBlueprint.php`
- Modify: `tests/Unit/FormBlueprintValidateTest.php` (rename to a new file later, but for now extend)

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/FormBlueprintValidateTest.php`:

```php
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
```

- [ ] **Step 2: Run to verify failures**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: 2 failures (no `sanitize` method).

- [ ] **Step 3: Implement `sanitize()`**

Read `src/Support/CssSanitizer.php` first to find the public API. Then add to `FormBlueprint`:

```php
use Madbox99\FilamentFormBuilder\Support\CssSanitizer;

// ...

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
```

(If the public method on `CssSanitizer` is not `sanitize()`, swap to whatever it exposes — verify by reading the file.)

- [ ] **Step 4: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintValidateTest.php`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprint.php tests/Unit/FormBlueprintValidateTest.php
git commit -m "feat(blueprint): sanitize CSS and normalise empty strings"
```

---

## Task 7: `FormBlueprintSchema` provides examples per field type

**Files:**
- Create: `src/Support/FormBlueprintSchema.php`
- Create: `tests/Unit/FormBlueprintSchemaTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/FormBlueprintSchemaTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Support\FormBlueprint;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;
use Madbox99\FilamentFormBuilder\Support\FormFieldBlueprint;

it('provides one example per field type', function (): void {
    $examples = FormBlueprintSchema::fieldExamples();

    foreach (FormFieldBlueprint::TYPES as $type) {
        expect($examples)->toHaveKey($type);
        expect($examples[$type])->toHaveKey('type', $type);
        expect($examples[$type])->toHaveKey('data');
        expect($examples[$type]['data'])->toHaveKey('name');
        expect($examples[$type]['data'])->toHaveKey('label');
    }
});

it('fullExample() validates against FormBlueprint', function (): void {
    FormBlueprint::validate(FormBlueprintSchema::fullExample());

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/pest tests/Unit/FormBlueprintSchemaTest.php`
Expected: FAIL with "Class FormBlueprintSchema not found".

- [ ] **Step 3: Implement `FormBlueprintSchema`**

Create `src/Support/FormBlueprintSchema.php`:

```php
<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Support;

final class FormBlueprintSchema
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fieldExamples(): array
    {
        $simple = static fn (string $type, string $label, string $name): array => [
            'type' => $type,
            'data' => [
                'label' => $label,
                'name' => $name,
                'required' => false,
                'placeholder' => '',
                'width' => FormFieldBlueprint::WIDTH_FULL,
            ],
        ];

        $withOptions = static fn (string $type, string $label, string $name): array => [
            'type' => $type,
            'data' => [
                'label' => $label,
                'name' => $name,
                'required' => false,
                'options' => [
                    ['label' => 'Option A', 'value' => 'a'],
                    ['label' => 'Option B', 'value' => 'b'],
                ],
            ],
        ];

        return [
            FormFieldBlueprint::TYPE_TEXT => $simple(FormFieldBlueprint::TYPE_TEXT, 'Full name', 'full_name'),
            FormFieldBlueprint::TYPE_EMAIL => $simple(FormFieldBlueprint::TYPE_EMAIL, 'Email', 'email'),
            FormFieldBlueprint::TYPE_PHONE => $simple(FormFieldBlueprint::TYPE_PHONE, 'Phone', 'phone'),
            FormFieldBlueprint::TYPE_NUMBER => array_merge_recursive(
                $simple(FormFieldBlueprint::TYPE_NUMBER, 'Age', 'age'),
                ['data' => ['min' => 0, 'max' => 120]],
            ),
            FormFieldBlueprint::TYPE_TEXTAREA => [
                'type' => FormFieldBlueprint::TYPE_TEXTAREA,
                'data' => [
                    'label' => 'Message',
                    'name' => 'message',
                    'required' => false,
                    'placeholder' => '',
                    'max_length' => 5000,
                ],
            ],
            FormFieldBlueprint::TYPE_SELECT => $withOptions(FormFieldBlueprint::TYPE_SELECT, 'Country', 'country'),
            FormFieldBlueprint::TYPE_CHECKBOX => [
                'type' => FormFieldBlueprint::TYPE_CHECKBOX,
                'data' => ['label' => 'I agree', 'name' => 'agree', 'required' => true],
            ],
            FormFieldBlueprint::TYPE_CHECKBOX_LIST => $withOptions(FormFieldBlueprint::TYPE_CHECKBOX_LIST, 'Interests', 'interests'),
            FormFieldBlueprint::TYPE_RADIO => $withOptions(FormFieldBlueprint::TYPE_RADIO, 'Gender', 'gender'),
            FormFieldBlueprint::TYPE_DATE => $simple(FormFieldBlueprint::TYPE_DATE, 'Birthday', 'birthday'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fullExample(): array
    {
        return [
            'schema_version' => FormBlueprint::SCHEMA_VERSION,
            'name' => 'Lead capture',
            'slug' => 'lead-capture',
            'description' => 'A short marketing form.',
            'fields' => array_values(self::fieldExamples()),
            'submission_actions' => null,
            'thank_you_message' => 'Thanks for signing up!',
            'redirect_url' => null,
            'custom_css' => null,
            'design_tokens' => null,
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/pest tests/Unit/FormBlueprintSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/FormBlueprintSchema.php tests/Unit/FormBlueprintSchemaTest.php
git commit -m "feat(blueprint): provide schema examples per field type"
```

---

## Task 8: `ExportFormAsJson` action

**Files:**
- Create: `src/Actions/ExportFormAsJson.php`
- Create: `tests/Unit/ExportFormAsJsonTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ExportFormAsJsonTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Actions\ExportFormAsJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

it('returns FormBlueprint::fromModel output', function (): void {
    $form = RegistrationForm::factory()->make(['name' => 'X', 'slug' => 'x']);

    $payload = (new ExportFormAsJson)->execute($form);

    expect($payload)
        ->toHaveKey('schema_version', 1)
        ->toHaveKey('name', 'X')
        ->toHaveKey('slug', 'x');
});
```

- [ ] **Step 2: Run to verify fails**

Run: `vendor/bin/pest tests/Unit/ExportFormAsJsonTest.php`
Expected: FAIL with "Class ExportFormAsJson not found".

- [ ] **Step 3: Implement**

Create `src/Actions/ExportFormAsJson.php`:

```php
<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Actions;

use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprint;

final class ExportFormAsJson
{
    /**
     * @return array<string, mixed>
     */
    public function execute(RegistrationForm $form): array
    {
        return FormBlueprint::fromModel($form);
    }
}
```

- [ ] **Step 4: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/ExportFormAsJsonTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Actions/ExportFormAsJson.php tests/Unit/ExportFormAsJsonTest.php
git commit -m "feat(actions): add ExportFormAsJson"
```

---

## Task 9: `ImportFormFromJson` happy path

**Files:**
- Create: `src/Actions/ImportFormFromJson.php`
- Create: `tests/Unit/ImportFormFromJsonTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ImportFormFromJsonTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
```

NOTE: this test needs a database. Verify the test suite has `RefreshDatabase` support by reading `tests/TestCase.php`. The current TestCase extends Orchestra `TestCase` without `RefreshDatabase` — add the trait import via the `uses()` call above. If migrations aren't auto-loaded by `orchestra/testbench`, add a `setUp()` hook in `tests/TestCase.php` later (Task 9b below).

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/pest tests/Unit/ImportFormFromJsonTest.php`
Expected: FAIL — first because the class is missing, then potentially because the DB isn't set up.

- [ ] **Step 3: Move the test to Feature suite and verify Testbench DB setup**

Move the file from `tests/Unit/ImportFormFromJsonTest.php` to `tests/Feature/JsonImportTest.php` (Feature tests already use the configured Testbench via `Pest.php`). Update the namespace usage to not need migrations explicitly by ensuring the testbench loads them:

Open `tests/TestCase.php` and verify it boots migrations. If it does NOT, add:

```php
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
```

Inside the `TestCase` class.

Re-run: `vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: FAIL with "Class ImportFormFromJson not found".

- [ ] **Step 4: Implement `ImportFormFromJson`**

Create `src/Actions/ImportFormFromJson.php`:

```php
<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Actions;

use Illuminate\Support\Str;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;
use Madbox99\FilamentFormBuilder\Support\FormBlueprint;

final class ImportFormFromJson
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function execute(array $payload): RegistrationForm
    {
        FormBlueprint::validate($payload);
        $payload = FormBlueprint::sanitize($payload);

        $payload['slug'] = $this->uniqueSlug((string) $payload['slug']);

        unset($payload['schema_version']);

        return RegistrationForm::create($payload);
    }

    private function uniqueSlug(string $candidate): string
    {
        $base = Str::slug($candidate) !== '' ? Str::slug($candidate) : 'form';
        $slug = $base;
        $suffix = 2;

        while (RegistrationForm::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
```

- [ ] **Step 5: Run to verify passes**

Run: `vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Actions/ImportFormFromJson.php tests/Feature/JsonImportTest.php tests/TestCase.php
git commit -m "feat(actions): import RegistrationForm from JSON payload"
```

---

## Task 10: `ImportFormFromJson` slug-uniqueness on collision

**Files:**
- Modify: `tests/Feature/JsonImportTest.php`

- [ ] **Step 1: Append failing test**

```php
it('uniquifies the slug on collision', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'lead-capture';

    (new ImportFormFromJson)->execute($payload);
    $second = (new ImportFormFromJson)->execute($payload);

    expect($second->slug)->toBe('lead-capture-2');
    expect($second->name)->toBe($payload['name']); // name unchanged
});

it('handles multiple collisions in sequence', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'collide';

    (new ImportFormFromJson)->execute($payload);
    (new ImportFormFromJson)->execute($payload);
    $third = (new ImportFormFromJson)->execute($payload);

    expect($third->slug)->toBe('collide-3');
});
```

- [ ] **Step 2: Run and confirm pass**

The implementation from Task 9 already handles this. Run:
`vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/JsonImportTest.php
git commit -m "test(import): verify slug-uniqueness on collisions"
```

---

## Task 11: `ImportFormFromJson` rejects invalid payloads

**Files:**
- Modify: `tests/Feature/JsonImportTest.php`

- [ ] **Step 1: Append failing tests**

```php
it('throws ValidationException on invalid payload', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'Not Valid';

    expect(fn () => (new ImportFormFromJson)->execute($payload))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(RegistrationForm::count())->toBe(0);
});

it('sanitizes custom_css before persisting', function (): void {
    $payload = FormBlueprintSchema::fullExample();
    $payload['slug'] = 'css-test';
    $payload['custom_css'] = '@import url("evil");';

    $form = (new ImportFormFromJson)->execute($payload);

    expect($form->custom_css)->not->toContain('@import');
});
```

- [ ] **Step 2: Run to verify passes**

Run: `vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: PASS (validation and sanitisation are already in place from earlier tasks).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/JsonImportTest.php
git commit -m "test(import): reject invalid payloads and sanitize CSS"
```

---

## Task 12: JSON round-trip stability

**Files:**
- Create: `tests/Feature/JsonRoundTripTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Actions\ExportFormAsJson;
use Madbox99\FilamentFormBuilder\Actions\ImportFormFromJson;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

it('preserves the data scope across export then import', function (): void {
    $original = RegistrationForm::factory()->create([
        'name' => 'Round trip',
        'slug' => 'round-trip',
        'description' => 'desc',
        'thank_you_message' => 'thanks',
    ]);

    $payload = (new ExportFormAsJson)->execute($original);
    $payload['slug'] = 'round-trip-import'; // avoid uniqueness collision

    $imported = (new ImportFormFromJson)->execute($payload);

    expect($imported->name)->toBe($original->name);
    expect($imported->description)->toBe($original->description);
    expect($imported->fields)->toEqual($original->fields);
    expect($imported->thank_you_message)->toBe($original->thank_you_message);
    expect($imported->is_active)->toBe($original->is_active);
    expect($imported->id)->not->toBe($original->id);
});
```

- [ ] **Step 2: Run to verify it passes**

Run: `vendor/bin/pest tests/Feature/JsonRoundTripTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/JsonRoundTripTest.php
git commit -m "test(roundtrip): export+import preserves the form data scope"
```

---

## Task 13: `ExportFormAction` row action returns a streamed download

**Files:**
- Create: `src/Filament/Resources/RegistrationForms/Tables/Actions/ExportFormAction.php`
- Modify: `src/Filament/Resources/RegistrationForms/Tables/RegistrationFormsTable.php`
- Modify: `resources/lang/en/form.php`, `resources/lang/hu/form.php`
- Create: `tests/Feature/JsonExportTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/JsonExportTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ExportFormAction;
use Madbox99\FilamentFormBuilder\Models\RegistrationForm;

it('exports a form as a streamed JSON download', function (): void {
    $form = RegistrationForm::factory()->create(['slug' => 'my-form']);

    $response = ExportFormAction::download($form);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('application/json');
    expect($response->headers->get('Content-Disposition'))->toContain('my-form.json');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $decoded = json_decode($body, true);
    expect($decoded)->toHaveKey('schema_version', 1)
        ->toHaveKey('slug', 'my-form');
});
```

- [ ] **Step 2: Run to verify fails**

Run: `vendor/bin/pest tests/Feature/JsonExportTest.php`
Expected: FAIL with "Class ExportFormAction not found".

- [ ] **Step 3: Implement the action**

Create `src/Filament/Resources/RegistrationForms/Tables/Actions/ExportFormAction.php`:

```php
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
```

- [ ] **Step 4: Wire it into the table**

Modify `src/Filament/Resources/RegistrationForms/Tables/RegistrationFormsTable.php`:

```php
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ExportFormAction;
```

In the `->recordActions([...])` array, add `ExportFormAction::make(),` just before `EditAction::make()`.

- [ ] **Step 5: Add translations**

In `resources/lang/en/form.php`, add to the `actions` array:

```php
'export_json' => 'Export JSON',
'import_json' => 'Import JSON',
'copy_field_json' => 'Copy field JSON',
```

(If the file doesn't have an `actions` group yet, read it and add the strings to wherever `preview` and similar action labels live.)

In `resources/lang/hu/form.php`, mirror:

```php
'export_json' => 'JSON exportálás',
'import_json' => 'JSON import',
'copy_field_json' => 'Mező JSON másolása',
```

- [ ] **Step 6: Run to verify passes**

Run: `vendor/bin/pest tests/Feature/JsonExportTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Filament/Resources/RegistrationForms/Tables/Actions/ExportFormAction.php \
        src/Filament/Resources/RegistrationForms/Tables/RegistrationFormsTable.php \
        resources/lang/en/form.php resources/lang/hu/form.php \
        tests/Feature/JsonExportTest.php
git commit -m "feat(admin): add JSON export row action with download response"
```

---

## Task 14: `ImportFormAction` header action

**Files:**
- Create: `src/Filament/Resources/RegistrationForms/Tables/Actions/ImportFormAction.php`
- Modify: `src/Filament/Resources/RegistrationForms/Pages/ListRegistrationForms.php`
- Modify: `tests/Feature/JsonImportTest.php`

- [ ] **Step 1: Append failing test**

Append to `tests/Feature/JsonImportTest.php`:

```php
use Filament\Actions\Action;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ImportFormAction;

it('ImportFormAction::make() returns a configured Filament action', function (): void {
    $action = ImportFormAction::make();

    expect($action)->toBeInstanceOf(Action::class);
    expect($action->getName())->toBe('import_json');
});

it('ImportFormAction::handle() creates a form from textarea JSON', function (): void {
    $payload = \Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema::fullExample();
    $payload['slug'] = 'from-textarea';

    $form = ImportFormAction::handle(json: json_encode($payload), file: null);

    expect($form)->not->toBeNull();
    expect($form->slug)->toBe('from-textarea');
});

it('ImportFormAction::handle() rejects malformed JSON', function (): void {
    expect(fn () => ImportFormAction::handle(json: '{not-json', file: null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

- [ ] **Step 2: Run to verify fails**

Run: `vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement the action**

Create `src/Filament/Resources/RegistrationForms/Tables/Actions/ImportFormAction.php`:

```php
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
                            static fn (array $msgs) => implode("\n", $msgs),
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
```

Add the new translation keys to `resources/lang/en/form.php`:

```php
// inside the existing array
'fields' => [
    // ... existing entries ...
    'json_file' => 'JSON file',
    'json_text' => 'JSON text',
],
'notifications' => [
    'imported' => 'Form imported as ":slug"',
    'json_invalid' => 'The provided JSON is not a valid form definition.',
    'field_copied' => 'Field JSON copied to clipboard',
],
```

Mirror in `resources/lang/hu/form.php` (Hungarian translations):

```php
'fields' => [
    // ...
    'json_file' => 'JSON fájl',
    'json_text' => 'JSON szöveg',
],
'notifications' => [
    'imported' => 'Form importálva: ":slug"',
    'json_invalid' => 'A megadott JSON nem érvényes formdefiníció.',
    'field_copied' => 'Mező JSON-je a vágólapra másolva',
],
```

If the existing files use a flat structure, follow that structure instead — read them first.

- [ ] **Step 4: Wire into ListRegistrationForms**

Modify `src/Filament/Resources/RegistrationForms/Pages/ListRegistrationForms.php` to register the new header action. Read the file first to find the existing `getHeaderActions()` method (or add it). Example pattern:

```php
use Filament\Actions\CreateAction;
use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Tables\Actions\ImportFormAction;

// inside class
protected function getHeaderActions(): array
{
    return [
        ImportFormAction::make(),
        CreateAction::make(),
    ];
}
```

If the existing page already declares header actions, append `ImportFormAction::make()` before the `CreateAction`.

- [ ] **Step 5: Run to verify passes**

Run: `vendor/bin/pest tests/Feature/JsonImportTest.php`
Expected: PASS for all three new tests.

- [ ] **Step 6: Commit**

```bash
git add src/Filament/Resources/RegistrationForms/Tables/Actions/ImportFormAction.php \
        src/Filament/Resources/RegistrationForms/Pages/ListRegistrationForms.php \
        resources/lang/en/form.php resources/lang/hu/form.php \
        tests/Feature/JsonImportTest.php
git commit -m "feat(admin): add JSON import header action with file + textarea inputs"
```

---

## Task 15: Field-level "Copy JSON" action in the Builder

**Files:**
- Modify: `src/Filament/Resources/RegistrationForms/Schemas/Sections/FieldBlocks.php`
- Create: `tests/Unit/CopyFieldJsonActionTest.php`

**Note:** Filament 5.6's `Forms\Components\Builder\Block` supports `extraItemActions(array $actions)`. Verify the exact method name by grepping `vendor/filament/filament/packages/forms/src/Components/Builder/Block.php` for `extraItemActions` before implementing. If the method is named differently (`extraActions`, `itemActions`, etc.), use the discovered name everywhere.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CopyFieldJsonActionTest.php`:

```php
<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Filament\Resources\RegistrationForms\Schemas\Sections\FieldBlocks;

it('every Builder block exposes a copy-JSON extra item action', function (): void {
    foreach (FieldBlocks::all() as $block) {
        // The exact accessor depends on Filament's API. Both of these are
        // tried; one of them returns the configured extra actions.
        $actions = method_exists($block, 'getExtraItemActions')
            ? $block->getExtraItemActions()
            : (method_exists($block, 'getExtraActions') ? $block->getExtraActions() : []);

        $names = array_map(static fn ($action) => $action->getName(), $actions);

        expect($names)->toContain('copy_json');
    }
});
```

- [ ] **Step 2: Run to verify fails**

Run: `vendor/bin/pest tests/Unit/CopyFieldJsonActionTest.php`
Expected: FAIL.

- [ ] **Step 3: Add `copyJsonAction()` and apply it**

Modify `src/Filament/Resources/RegistrationForms/Schemas/Sections/FieldBlocks.php`. Add to the `use` block:

```php
use Filament\Actions\Action;
use Illuminate\Support\Js;
```

Inside the class, add the helper:

```php
    private static function copyJsonAction(): Action
    {
        return Action::make('copy_json')
            ->label(__('filament-form-builder::form.actions.copy_field_json'))
            ->icon(Heroicon::ClipboardDocument)
            ->color('gray')
            ->action(function (array $arguments, $component): void {
                /** @var array<string, mixed> $state */
                $state = is_array($arguments['state'] ?? null) ? $arguments['state'] : [];
                $type = is_string($arguments['type'] ?? null) ? $arguments['type'] : '';

                $json = (string) json_encode(
                    ['type' => $type, 'data' => $state],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                $component->getLivewire()->dispatch('ffb-copy-to-clipboard', json: $json);

                \Filament\Notifications\Notification::make()
                    ->title(__('filament-form-builder::form.notifications.field_copied'))
                    ->success()
                    ->send();
            });
    }
```

Then modify `private static function block(string $type, Heroicon $icon): Block` to chain the action:

```php
        return Block::make($type)
            ->label(function (?array $state) use ($type): string {
                $fieldLabel = isset($state['label']) && is_string($state['label']) ? trim($state['label']) : '';

                return $fieldLabel !== ''
                    ? $fieldLabel
                    : __('filament-form-builder::form.field_types.'.$type);
            })
            ->icon($icon)
            ->extraItemActions([self::copyJsonAction()]);
```

If the unit test fails at this step because `Action::action()` requires the closure arguments differently in Filament 5.6, adjust the closure signature to match the docs (`function (array $arguments, Builder $component)` or similar). The behaviour to preserve is: build the `{type, data}` JSON for the block being copied, dispatch a Livewire browser event with the payload, and show a success notification.

- [ ] **Step 4: Add the Alpine listener for clipboard write**

Read `src/Filament/Resources/RegistrationForms/Pages/EditRegistrationForm.php`. If it doesn't already register render hooks, add one in the service provider or page — easiest is to add a `<script>` blade view registered via `FilamentAsset::register([Js::make('ffb-copy-to-clipboard', ...)])` in the package service provider.

If that infrastructure is heavy, take the simpler route: render a static asset (raw JS) in a Filament view via the existing `FilamentFormBuilderServiceProvider`. Specifically:

1. Create `resources/js/copy-to-clipboard.js`:

```js
window.addEventListener('ffb-copy-to-clipboard', (event) => {
    const json = event.detail?.json ?? '';
    if (!json) return;
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(json).catch(() => fallback(json));
    } else {
        fallback(json);
    }
});

function fallback(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
}
```

2. Register it in `FilamentFormBuilderServiceProvider` so Filament loads it on every admin page. Use the existing asset registration pattern — read the provider first and follow its lead. If the provider has no asset registration yet, add (using Filament's `FilamentAsset` facade):

```php
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

// in boot()
FilamentAsset::register([
    Js::make('ffb-copy-to-clipboard', __DIR__.'/../resources/js/copy-to-clipboard.js'),
], 'madbox99/filament-form-builder');
```

Document any deviation from this plan in the commit message.

- [ ] **Step 5: Run to verify passes**

Run: `vendor/bin/pest tests/Unit/CopyFieldJsonActionTest.php`
Expected: PASS.

- [ ] **Step 6: Manually verify clipboard wiring**

Run the workbench (per `composer.json` `extra.laravel`), log in to the admin, open a form's Builder, click "Copy JSON" on a field, then paste somewhere — confirm the field's JSON arrives. If the asset isn't loading, fix the registration and re-test before committing.

- [ ] **Step 7: Commit**

```bash
git add src/Filament/Resources/RegistrationForms/Schemas/Sections/FieldBlocks.php \
        src/FilamentFormBuilderServiceProvider.php \
        resources/js/copy-to-clipboard.js \
        tests/Unit/CopyFieldJsonActionTest.php
git commit -m "feat(builder): copy field as JSON to clipboard"
```

---

## Task 16: `FormJsonSchemaHelp` Filament Page

**Files:**
- Create: `src/Filament/Pages/FormJsonSchemaHelp.php`
- Create: `resources/views/pages/form-json-schema-help.blade.php`
- Modify: `src/FilamentFormBuilderPlugin.php`
- Modify: `resources/lang/en/form.php`, `resources/lang/hu/form.php`

- [ ] **Step 1: Add translation keys for the help page**

Add to `resources/lang/en/form.php`:

```php
'help' => [
    'title' => 'JSON schema reference',
    'navigation_label' => 'JSON schema help',
    'overview_heading' => 'Overview',
    'overview_body' => 'Use the JSON export and import actions on the form list page to move form definitions between environments. Copy a single field as JSON from the Builder to share or paste into another form. Submissions are not included in any export.',
    'schema_heading' => 'Full form schema',
    'types_heading' => 'Field types',
    'workflows_heading' => 'Workflows',
    'workflow_export_import' => 'Export → edit → Import',
    'workflow_field_copy' => 'Builder field copy → paste into JSON',
],
```

Mirror in `resources/lang/hu/form.php`:

```php
'help' => [
    'title' => 'JSON séma súgó',
    'navigation_label' => 'JSON séma súgó',
    'overview_heading' => 'Áttekintés',
    'overview_body' => 'A form lista oldalán a JSON exportálás és importálás action-ökkel mozgathatod a formdefiníciókat környezetek között. Egy mezőt a Builder "Mező JSON másolása" gombjával oszthatsz meg vagy illeszthetsz be egy másik form JSON-jébe. A beküldések nem kerülnek bele az exportba.',
    'schema_heading' => 'Teljes form séma',
    'types_heading' => 'Mező típusok',
    'workflows_heading' => 'Munkamenetek',
    'workflow_export_import' => 'Exportálás → szerkesztés → Importálás',
    'workflow_field_copy' => 'Builder mező másolás → beillesztés JSON-be',
],
```

- [ ] **Step 2: Create the Page class**

Create `src/Filament/Pages/FormJsonSchemaHelp.php`:

```php
<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Madbox99\FilamentFormBuilder\Support\FormBlueprintSchema;

final class FormJsonSchemaHelp extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::QuestionMarkCircle;

    protected string $view = 'filament-form-builder::pages.form-json-schema-help';

    public static function getNavigationLabel(): string
    {
        return __('filament-form-builder::form.help.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament-form-builder::form.help.title');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getFieldExamples(): array
    {
        return FormBlueprintSchema::fieldExamples();
    }

    public function getFullExampleJson(): string
    {
        return (string) json_encode(
            FormBlueprintSchema::fullExample(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
```

(If `protected static string|\BackedEnum|null` causes a syntax error on the installed Filament version, check the parent class — Filament 5.6 expects `string|UnitEnum|null` or `string|null`. Match the parent signature.)

- [ ] **Step 3: Create the Blade view**

Create `resources/views/pages/form-json-schema-help.blade.php`:

```blade
<x-filament-panels::page>
    <div class="space-y-8">
        <section>
            <h2 class="text-lg font-semibold">{{ __('filament-form-builder::form.help.overview_heading') }}</h2>
            <p class="mt-2 text-sm">{{ __('filament-form-builder::form.help.overview_body') }}</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold">{{ __('filament-form-builder::form.help.schema_heading') }}</h2>
            <pre class="mt-2 overflow-auto rounded bg-gray-900 p-4 text-xs text-gray-100"><code>{{ $this->getFullExampleJson() }}</code></pre>
        </section>

        <section>
            <h2 class="text-lg font-semibold">{{ __('filament-form-builder::form.help.types_heading') }}</h2>
            <div class="mt-2 space-y-4">
                @foreach ($this->getFieldExamples() as $type => $example)
                    <div>
                        <h3 class="font-medium">{{ $type }}</h3>
                        <pre class="mt-1 overflow-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><code>{{ json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">{{ __('filament-form-builder::form.help.workflows_heading') }}</h2>
            <ul class="mt-2 list-disc space-y-1 pl-6 text-sm">
                <li>{{ __('filament-form-builder::form.help.workflow_export_import') }}</li>
                <li>{{ __('filament-form-builder::form.help.workflow_field_copy') }}</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 4: Register the page with the plugin**

Read `src/FilamentFormBuilderPlugin.php` to find where pages are registered. Add:

```php
use Madbox99\FilamentFormBuilder\Filament\Pages\FormJsonSchemaHelp;

// inside the register/pages method
$panel->pages([
    // existing pages ...
    FormJsonSchemaHelp::class,
]);
```

The exact integration depends on the plugin's existing structure — follow it.

- [ ] **Step 5: Verify the view loads (no test, manual check)**

If running the workbench is wired up, run it (`vendor/bin/testbench serve` or per the package's docs). Navigate to the admin and confirm the "JSON schema help" page appears in the nav and renders without errors.

- [ ] **Step 6: Commit**

```bash
git add src/Filament/Pages/FormJsonSchemaHelp.php \
        src/FilamentFormBuilderPlugin.php \
        resources/views/pages/form-json-schema-help.blade.php \
        resources/lang/en/form.php resources/lang/hu/form.php
git commit -m "feat(admin): add JSON schema help page rendered from FormBlueprintSchema"
```

---

## Task 17: Full test suite + lint

- [ ] **Step 1: Run the entire suite**

Run: `vendor/bin/pest`
Expected: all PASS.

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint`
Expected: changes applied (if any). Stage and commit any reformatting.

- [ ] **Step 3: Commit any Pint changes**

```bash
git add -u
git diff --cached --quiet || git commit -m "style: pint"
```

---

## Notes for the implementer

- The plan assumes Filament 5.6.0 (confirmed in `composer.lock`). If `extraItemActions` is unavailable on the installed Builder block class, grep `vendor/filament` for the actual hook name before implementing Task 15 and update the implementation accordingly.
- The translation files (`resources/lang/{en,hu}/form.php`) currently have a particular structure — read them first and add new keys in a way that matches the existing layout. The keys referenced in code must match exactly what you write in the lang file.
- `FilamentFormBuilderServiceProvider::boot()` is the natural home for `FilamentAsset::register([...])`, but read the file first to confirm.
- `tests/TestCase.php` may need the `loadMigrationsFrom()` hook (Task 9). Verify by trying to run a feature test that touches the DB — if the table is missing, add the hook and re-run.
- Do **not** export or import: `id`, the tenant FK, `submissions_count`, `created_at`, `updated_at`, `deleted_at`. The spec is explicit on this.
- Slug normalization (`Str::slug`) is part of the import action only — the validation rule for slug remains strict (`/^[a-z0-9-]+$/`). If a slug arrives non-normalized but otherwise valid, it should pass validation and then be uniquified. Don't relax the regex.
