<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Filament\Pages\FormJsonSchemaHelp;

it('exposes a navigation label and title via translations', function (): void {
    expect(FormJsonSchemaHelp::getNavigationLabel())->toBeString()->not->toBeEmpty();
});

it('renders the full example JSON deterministically', function (): void {
    $page = new FormJsonSchemaHelp;

    $json = $page->getFullExampleJson();

    expect($json)->toContain('"schema_version": 1');
    expect($json)->toContain('"slug": "lead-capture"');
    expect(json_decode($json, true))->toBeArray();
});

it('exposes one example per field type', function (): void {
    $page = new FormJsonSchemaHelp;

    $examples = $page->getFieldExamples();

    foreach (\Madbox99\FilamentFormBuilder\Support\FormFieldBlueprint::TYPES as $type) {
        expect($examples)->toHaveKey($type);
    }
});
