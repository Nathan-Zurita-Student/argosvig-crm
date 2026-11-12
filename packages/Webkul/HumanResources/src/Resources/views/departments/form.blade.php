<div class="grid gap-4 md:grid-cols-2">
    <x-admin::form.control-group>
        <x-admin::form.control-group.label class="required">@lang('human_resources::app.departments.fields.name')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="text" name="name" id="name" :value="old('name', $department?->name)" rules="required" :label="trans('human_resources::app.departments.fields.name')" />
        <x-admin::form.control-group.error control-name="name" />
    </x-admin::form.control-group>

    <x-admin::form.control-group>
        <x-admin::form.control-group.label>@lang('human_resources::app.departments.fields.code')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="text" name="code" id="code" :value="old('code', $department?->code)" :label="trans('human_resources::app.departments.fields.code')" />
        <x-admin::form.control-group.error control-name="code" />
    </x-admin::form.control-group>

    <x-admin::form.control-group class="md:col-span-2">
        <x-admin::form.control-group.label>@lang('human_resources::app.departments.fields.description')</x-admin::form.control-group.label>
        <x-admin::form.control-group.control type="textarea" name="description" id="description" :value="old('description', $department?->description)" :label="trans('human_resources::app.departments.fields.description')" />
        <x-admin::form.control-group.error control-name="description" />
    </x-admin::form.control-group>

    <div>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department?->is_active ?? true))>
            @lang('human_resources::app.departments.fields.is_active')
        </label>
    </div>
</div>
