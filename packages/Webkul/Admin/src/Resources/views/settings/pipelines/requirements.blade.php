<fieldset class="mt-3 flex flex-col gap-2 text-sm dark:text-gray-300">
    <legend class="font-medium">@lang('admin::pipeline_requirements.title')</legend>
    <input type="hidden" :name="'stages[' + element.id + '][required_fields][]'" value="">

    @foreach (\Webkul\Lead\Models\Stage::REQUIRED_FIELD_OPTIONS as $field)
        <label class="flex items-center gap-2" @if ($field === 'lost_reason') v-if="element.code === 'lost'" @endif>
            <input type="checkbox" :name="'stages[' + element.id + '][required_fields][]'" value="{{ $field }}"
                :checked="(element.required_fields || []).includes('{{ $field }}')">
            @lang('admin::pipeline_requirements.fields.'.$field)
        </label>
    @endforeach
</fieldset>
