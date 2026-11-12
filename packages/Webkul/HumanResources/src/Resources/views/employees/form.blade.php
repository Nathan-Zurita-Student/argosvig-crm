<div class="grid gap-4 md:grid-cols-2">
    @foreach ([
        ['employee_number', 'text', true],
        ['full_name', 'text', true],
        ['cpf', 'text', false],
        ['birth_date', 'date', false],
        ['rg', 'text', false],
        ['email', 'email', false],
        ['phone', 'text', false],
        ['job_title', 'text', false],
        ['hire_date', 'date', false],
        ['alterdata_employee_id', 'text', false],
    ] as [$field, $type, $required])
        <x-admin::form.control-group>
            <x-admin::form.control-group.label :class="$required ? 'required' : ''">
                @lang("human_resources::app.employees.fields.{$field}")
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                :type="$type"
                :name="$field"
                :id="$field"
                :value="old($field, $employee?->{$field})"
                :rules="$required ? 'required' : ''"
                :label="trans('human_resources::app.employees.fields.'.$field)"
            />

            <x-admin::form.control-group.error :control-name="$field" />
        </x-admin::form.control-group>
    @endforeach

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>
            @lang('human_resources::app.employees.fields.department')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="department_id"
            id="department_id"
            :value="old('department_id', $employee?->department_id)"
            :label="trans('human_resources::app.employees.fields.department')"
        >
            <option value="">@lang('human_resources::app.common.select')</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="department_id" />
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">
            @lang('human_resources::app.common.status')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="select"
            name="status"
            id="status"
            :value="old('status', $employee?->status ?? 'active')"
            rules="required"
            :label="trans('human_resources::app.common.status')"
        >
            @foreach (['active', 'on_leave', 'inactive'] as $status)
                <option value="{{ $status }}">@lang("human_resources::app.employees.statuses.{$status}")</option>
            @endforeach
        </x-admin::form.control-group.control>

        <x-admin::form.control-group.error control-name="status" />
    </x-admin::form.control-group>

    <x-admin::form.control-group class="md:col-span-2">
        <x-admin::form.control-group.label>
            @lang('human_resources::app.employees.fields.notes')
        </x-admin::form.control-group.label>

        <x-admin::form.control-group.control
            type="textarea"
            name="notes"
            id="notes"
            :value="old('notes', $employee?->notes)"
            :label="trans('human_resources::app.employees.fields.notes')"
        />

        <x-admin::form.control-group.error control-name="notes" />
    </x-admin::form.control-group>
</div>
