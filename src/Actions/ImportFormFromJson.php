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

        $form = RegistrationForm::create($payload);

        return $form->refresh();
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
