<?php

declare(strict_types=1);

use Madbox99\FilamentFormBuilder\Support\FieldJsonExporter;

it('encodes an item state as pretty JSON with type and data', function (): void {
    $state = ['type' => 'text_input', 'data' => ['label' => 'Name', 'name' => 'name', 'required' => true]];

    $json = FieldJsonExporter::encode($state);

    expect($json)->toContain('"type": "text_input"');
    expect($json)->toContain('"label": "Name"');
    expect(json_decode($json, true))->toBe($state);
});

it('returns null when the item state is missing or malformed', function (): void {
    expect(FieldJsonExporter::encode(null))->toBeNull();
    expect(FieldJsonExporter::encode(['data' => []]))->toBeNull(); // missing type
    expect(FieldJsonExporter::encode(['type' => 'x']))->toBeNull(); // missing data
});
