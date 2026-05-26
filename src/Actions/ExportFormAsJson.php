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
