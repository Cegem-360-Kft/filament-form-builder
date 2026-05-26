<?php

declare(strict_types=1);

namespace Madbox99\FilamentFormBuilder\Support;

final class FieldJsonExporter
{
    /**
     * Return a pretty-printed JSON string for a Builder item state, or null
     * when the state doesn't look like a valid `{type, data: ...}` shape.
     *
     * @param  array<string, mixed>|null  $state
     */
    public static function encode(?array $state): ?string
    {
        if (! is_array($state)) {
            return null;
        }

        $type = $state['type'] ?? null;
        $data = $state['data'] ?? null;

        if (! is_string($type) || ! is_array($data)) {
            return null;
        }

        return (string) json_encode(
            ['type' => $type, 'data' => $data],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
