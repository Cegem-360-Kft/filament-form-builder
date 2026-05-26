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
