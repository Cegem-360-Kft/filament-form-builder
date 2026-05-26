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
            FormFieldBlueprint::TYPE_NUMBER => [
                'type' => FormFieldBlueprint::TYPE_NUMBER,
                'data' => [
                    'label' => 'Age',
                    'name' => 'age',
                    'required' => false,
                    'placeholder' => '',
                    'width' => FormFieldBlueprint::WIDTH_FULL,
                    'min' => 0,
                    'max' => 120,
                ],
            ],
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
