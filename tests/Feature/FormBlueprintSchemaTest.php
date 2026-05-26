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
