# JSON form import/export, field copy action, and JSON schema help page

**Status:** Approved (design)
**Date:** 2026-05-26
**Scope:** Add JSON-based form definition import/export to the admin panel, a per-field "copy as JSON" clipboard action in the Builder, and an admin help page that documents the JSON schema.

## Motivation

Users want to move form definitions between environments (dev → staging → prod, or across tenants) and share field templates between forms. Today the only way to recreate a form is to rebuild it by hand in the Builder. CSV is unsuitable because the form payload is nested (fields tree, design tokens, submission actions, custom CSS) — JSON is the natural fit and lets us version the schema.

## Non-goals

- Importing or exporting form submissions (`FormSubmission` rows). Out of scope.
- Cross-tenant import: the imported form lands in the current tenant (Filament's normal tenant scoping applies). No tenant remapping.
- Real-time clipboard paste action in the Builder for a copied field. Phase 1 only copies; paste workflow is manual (paste into another form's JSON, or hand-edit). A "paste field JSON" action can be a follow-up.
- Overwriting an existing form via import. Import always creates a new record.

## Data scope (what goes into the JSON)

Exported and imported fields of `RegistrationForm`:

- `name`
- `slug`
- `description`
- `fields` (Builder content, as currently stored — array of blocks)
- `submission_actions` (cast via `SubmissionActionsCast`)
- `thank_you_message`
- `redirect_url`
- `custom_css`
- `design_tokens` (cast via `DesignTokensCast`)
- `is_active`

Plus a top-level `schema_version: 1` discriminator so future schema changes can be migrated.

Explicitly excluded: `id`, tenant foreign key (set from the current tenant on import), `submissions_count` (reset to 0 on import), `created_at` / `updated_at` / `deleted_at`.

## Architecture

New components, sized to match the existing `Sections/` separation pattern:

```
src/
├── Support/
│   ├── FormBlueprint.php           // serialize/deserialize + validate
│   └── FormBlueprintSchema.php     // schema description + curated examples
├── Actions/
│   ├── ExportFormAsJson.php        // RegistrationForm → array
│   └── ImportFormFromJson.php      // array → new RegistrationForm
└── Filament/
    ├── Resources/RegistrationForms/Tables/
    │   └── Actions/                // new dir for table/header actions
    │       ├── ImportFormAction.php
    │       └── ExportFormAction.php
    └── Pages/
        └── FormJsonSchemaHelp.php  // Filament Page (admin-only help)
```

Modified:

- `Filament/Resources/RegistrationForms/Schemas/Sections/FieldBlocks.php` — add `extraItemActions()` on each block for "copy field JSON".
- `Filament/Resources/RegistrationForms/Tables/RegistrationFormsTable.php` — wire the new row/header actions.
- `Filament/Resources/RegistrationForms/Pages/ListRegistrationForms.php` — register the header `ImportFormAction`.
- `resources/lang/en/form.php`, `resources/lang/hu/form.php` — translations.
- New: `resources/lang/{en,hu}/pages.php` (or extend an existing namespace) for the help page strings.
- New: `resources/views/pages/form-json-schema-help.blade.php` for the help page.

### `Support/FormBlueprint.php`

Single source of truth for the on-disk JSON shape.

- `FormBlueprint::fromModel(RegistrationForm $form): array` — extracts the data-scope keys, prepends `schema_version: 1`.
- `FormBlueprint::validate(array $payload): void` — runs Laravel `Validator` rules; throws `Illuminate\Validation\ValidationException` on failure. Used by both the import action and feature tests.
- `FormBlueprint::sanitize(array $payload): array` — runs `custom_css` through the existing `Support\CssSanitizer` and normalizes nullable fields (empty string → null where appropriate).
- `FormBlueprint::SCHEMA_VERSION = 1` constant.

Validation rules (highlights):
- `schema_version`: `required|integer|in:1`
- `name`: `required|string|max:255`
- `slug`: `required|string|max:255|regex:/^[a-z0-9-]+$/`
- `description`: `nullable|string`
- `fields`: `required|array`
- `fields.*.type`: `required|string` and a closure rule that asserts membership in `FormFieldBlueprint::TYPES`
- `fields.*.data.name`: `required|string|regex:/^[a-zA-Z0-9_]+$/|max:64`
- `fields.*.data.label`: `required|string|max:255`
- `fields.*.data.required`: `boolean`
- For `select`, `checkbox_list`, `radio` types: `fields.*.data.options.*.label` and `.value` required, value regex `/^[A-Za-z0-9._\-]+$/`
- `submission_actions`: `nullable|array` (deeper validation delegated to `SubmissionActionsCast`'s existing rules)
- `design_tokens`: `nullable|array` (deeper validation delegated to `DesignTokensCast`)
- `custom_css`: `nullable|string|max:65535` (sanitized before persisting)
- `is_active`: `boolean`
- `redirect_url`: `nullable|string|max:2048|url`
- `thank_you_message`: `nullable|string`

### `Support/FormBlueprintSchema.php`

Drives the help page. Pure data, no rendering.

- `FormBlueprintSchema::fullExample(): array` — a representative payload with one of each field type.
- `FormBlueprintSchema::fieldExamples(): array` — keyed by `FormFieldBlueprint::TYPE_*`, each value is a minimal `{type, data: {...}}` block.
- `FormBlueprintSchema::fieldSchema(string $type): array` — describes which keys are valid for a given field type (used to render the type table on the help page).

### `Actions/ExportFormAsJson.php`

- `execute(RegistrationForm $form): array` — calls `FormBlueprint::fromModel()`.
- Stateless, `final`, `declare(strict_types=1)`.

### `Actions/ImportFormFromJson.php`

- `execute(array $payload): RegistrationForm` — runs `FormBlueprint::validate()`, then `FormBlueprint::sanitize()`, then resolves slug uniqueness (see below), then creates the model.
- Slug uniqueness: the existing `DetailsSection::autoGeneratedSlug()` only wraps `Str::slug()` and does not check uniqueness, so `ImportFormFromJson` adds its own loop: normalize via `Str::slug()`, then query `RegistrationForm::where('slug', $candidate)->exists()` (respecting current tenant scope) and append `-2`, `-3`, … until unique. Returns the resolved slug to the caller via the created model so the success notification can surface it. `name` is preserved verbatim.
- Tenant assignment: relies on Filament's standard tenant context (Filament fills the tenant FK on create when scoped). No explicit handling needed beyond not exporting the tenant FK.
- `submissions_count` defaults to 0 via the column default; do not set it from JSON.

### `Filament/Resources/RegistrationForms/Tables/Actions/ImportFormAction.php`

- Static factory returning a `Filament\Actions\Action` named `import_json`.
- Modal schema: `FileUpload::make('file')->acceptedFileTypes(['application/json'])` + `Textarea::make('json')` (either one provided; validate at least one is filled).
- On submit: decode JSON, call `ImportFormFromJson::execute()`. On `ValidationException`, surface messages via `Notification::make()->danger()` and keep the modal open. On success, redirect to `EditRegistrationForm` for the new record and show a success notification.

### `Filament/Resources/RegistrationForms/Tables/Actions/ExportFormAction.php`

- Static factory returning a `Filament\Actions\Action` named `export_json` for use as a row action.
- Returns a `Symfony\Component\HttpFoundation\StreamedResponse` (Filament supports returning responses from actions).
- Filename: `{slug}.json`. Encoding flags: `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

### `FieldBlocks.php` — copy field JSON

Each block in `FieldBlocks::block()` gets `extraItemActions([self::copyJsonAction()])`. The action:

- Icon: `Heroicon::ClipboardDocument`.
- Label: `__('filament-form-builder::form.actions.copy_field_json')`.
- Reads the current block state via the action's closure (`function (array $arguments, $component) { ... }`), wraps it as `['type' => ..., 'data' => $state]`, JSON-encodes.
- Dispatches a browser event with the JSON; an Alpine snippet on the edit page listens and writes to `navigator.clipboard`. Filament shows a success notification.
- Implementation detail to validate during the implementation plan: the exact Filament 4 API for `extraItemActions` on a Builder block, and the cleanest way to push a string to `navigator.clipboard` from a server-side action. If `navigator.clipboard.writeText` from the dispatched browser event proves brittle, fall back to rendering a hidden input populated with the JSON and selecting it client-side. The plan must verify both before committing to one.

### `Filament/Pages/FormJsonSchemaHelp.php`

- Extends `Filament\Pages\Page`.
- `protected static string|UnitEnum|null $navigationGroup = '<same as RegistrationFormResource>'`.
- `protected static ?string $navigationIcon = Heroicon::QuestionMarkCircle`.
- `getTitle()` → translated `JSON séma súgó` / `JSON schema reference`.
- View: `resources/views/pages/form-json-schema-help.blade.php`.
- The view consumes `FormBlueprintSchema::fullExample()` and `FormBlueprintSchema::fieldExamples()`; no inline literals so the help page cannot drift from the code.
- Sections:
  1. **Mire jó / Overview** — short prose: when to import, when to export, what's not in scope.
  2. **Teljes form séma / Full form schema** — pretty-printed JSON in a `<pre>` block with a copy button (Alpine, native `navigator.clipboard`).
  3. **Mező típusok / Field types** — table listing each `TYPE_*`, followed by one minimal JSON block per type.
  4. **Munkamenetek / Workflows** — *Export → edit → Import* and *Field copy → paste*.
- Authorization: no extra ACL; if the panel is gated, the page inherits that gating like other Filament pages.

## Data flow

```
[Form list page]
   ├── header: "Import JSON" → modal → ImportFormFromJson::execute(decoded)
   │                                       └─→ FormBlueprint::validate
   │                                       └─→ FormBlueprint::sanitize (CssSanitizer)
   │                                       └─→ slug uniqueness loop (Str::slug + DB check)
   │                                       └─→ RegistrationForm::create
   │                                       └─→ redirect to Edit
   │
   └── row: "Export JSON" → ExportFormAsJson::execute → StreamedResponse({slug}.json)

[Edit form page → Builder → field item]
   └── extra item action: "Copy JSON"
         └─→ build {type, data} from block state
         └─→ dispatch browser event with JSON string
         └─→ Alpine writes to navigator.clipboard, Filament shows success toast

[Admin nav]
   └── "JSON schema help" page → renders FormBlueprintSchema::* into Blade
```

## Error handling

- Invalid JSON (decode fails) → modal-level error: *"A megadott JSON nem értelmezhető."*
- Validation failures from `FormBlueprint::validate()` → grouped under their input keys; the modal shows all messages.
- Empty payload (neither file nor textarea) → validation rule `required_without`.
- `custom_css` containing forbidden constructs → `CssSanitizer` strips them silently (consistent with current behavior on the Builder edit form). Document this in the help page.
- Slug uniqueness collisions → handled by the import's own `Str::slug` + DB-check loop; the notification message includes the resolved slug ("Imported as `{slug}`") so the user knows it was uniquified.
- Export of a deleted (soft-deleted) form → out of scope; row action visibility follows existing list page filters.
- Clipboard copy failures (browser blocked `navigator.clipboard`) → fall back to a textarea preview the user can select manually; the implementation plan must include this branch.

## Testing

**Feature (`tests/Feature/`)**
- `JsonImportTest`: success path creates a new form with expected attributes.
- `JsonImportTest`: slug collision → uniquified, name preserved.
- `JsonImportTest`: invalid JSON → modal stays open with error.
- `JsonImportTest`: validation failures (missing required keys, bad regex) → surfaced per-key.
- `JsonImportTest`: `custom_css` with `<script>` payload → sanitized.
- `JsonExportTest`: response is a streamed download with the expected filename and `schema_version: 1`.
- `JsonRoundTripTest`: factory-built form → export → import → assert relevant attributes are identical (excluding `id`, slug suffix, timestamps).
- `CopyFieldJsonActionTest`: action exists on each `FieldBlocks::all()` block and produces the expected JSON shape when invoked.

**Unit (`tests/Unit/`)**
- `FormBlueprintValidateTest`: one case per validation rule branch.
- `FormBlueprintFromModelTest`: extracted keys exactly match the data scope (no leakage of `id`/timestamps/submissions count).
- `FormBlueprintSchemaTest`: every `FormFieldBlueprint::TYPE_*` has an entry in `fieldExamples()` and `fieldSchema()`.

**Architecture (Pest)**
- New classes in `src/Actions` and `src/Support` are `final` and declare strict types (matches existing project conventions).

## Translations

Add keys under `filament-form-builder::form`:
- `actions.import_json`, `actions.export_json`, `actions.copy_field_json`
- `notifications.imported`, `notifications.imported_with_renamed_slug`, `notifications.json_invalid`, `notifications.field_copied`

Add a new translation namespace for the help page: `filament-form-builder::pages.json_schema_help.*` (title, intro, section headings, workflow steps).

Both `en` and `hu` locales must ship with the feature.

## Open implementation decisions (to be resolved in the plan)

1. Exact Filament 4 API surface for `extraItemActions()` on a `Builder\Block` — verify against the installed version and the existing usages (none currently in `FieldBlocks.php`).
2. Clipboard write strategy: server-side action dispatching a browser event vs. a small Livewire/Alpine component on the edit page. Pick the one with the lighter footprint, document the choice in the plan.
3. Where the "JSON schema help" page sits in the navigation: same group as `RegistrationFormResource`, or under a generic "Help" group. Default to the same group; revisit if the project has navigation conventions to follow.

These do not affect the data model or public-facing contract, only internal implementation. They are resolved during planning, not now.
