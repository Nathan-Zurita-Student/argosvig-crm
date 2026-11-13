<div class="grid gap-4 md:grid-cols-2">
    <x-admin::form.control-group class="md:col-span-2">
        <x-admin::form.control-group.label class="required">@lang('human_resources::app.vacations.fields.employee_id')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="select" name="employee_id" id="employee_id" :value="old('employee_id', $vacation?->employee_id)" rules="required" :label="trans('human_resources::app.vacations.fields.employee_id')">
            <option value="">@lang('human_resources::app.common.select')</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->employee_number }} · {{ $employee->full_name }}</option>
            @endforeach
        </x-admin::form.control-group.control>
        <x-admin::form.control-group.error control-name="employee_id" />
    </x-admin::form.control-group>

    @foreach ([['starts_at', 'date'], ['ends_at', 'date']] as [$field, $type])
        <x-admin::form.control-group>
            <x-admin::form.control-group.label class="required">@lang("human_resources::app.vacations.fields.{$field}")</x-admin::form.control-group.label>
            <x-admin::form.control-group.control :type="$type" :name="$field" :id="$field" :value="old($field, $vacation?->{$field}?->format('Y-m-d'))" rules="required" :label="trans('human_resources::app.vacations.fields.'.$field)" />
            <x-admin::form.control-group.error :control-name="$field" />
        </x-admin::form.control-group>
    @endforeach

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">@lang('human_resources::app.vacations.fields.type')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="select" name="type" id="type" :value="old('type', $vacation?->type ?? 'regular')" rules="required" :label="trans('human_resources::app.vacations.fields.type')">
            @foreach (['regular', 'collective', 'split'] as $type)
                <option value="{{ $type }}">@lang("human_resources::app.vacations.types.{$type}")</option>
            @endforeach
        </x-admin::form.control-group.control>
        <x-admin::form.control-group.error control-name="type" />
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">@lang('human_resources::app.common.status')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="select" name="status" id="status" :value="old('status', $vacation?->status ?? 'scheduled')" rules="required" :label="trans('human_resources::app.common.status')">
            @foreach (['scheduled', 'in_progress', 'completed', 'cancelled'] as $status)
                <option value="{{ $status }}">@lang("human_resources::app.vacations.statuses.{$status}")</option>
            @endforeach
        </x-admin::form.control-group.control>
        <x-admin::form.control-group.error control-name="status" />
    </x-admin::form.control-group>

    <x-admin::form.control-group class="md:col-span-2">
        <x-admin::form.control-group.label>@lang('human_resources::app.vacations.fields.notes')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="textarea" name="notes" id="notes" :value="old('notes', $vacation?->notes)" :label="trans('human_resources::app.vacations.fields.notes')" />
        <x-admin::form.control-group.error control-name="notes" />
    </x-admin::form.control-group>
</div>
