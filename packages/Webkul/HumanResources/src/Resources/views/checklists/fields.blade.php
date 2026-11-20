{{-- Card fields shared by the create modal and the details drawer; $model is the Vue object being edited. --}}
<div class="mb-4">
    <label class="{{ $label }}" for="{{ $prefix }}-description">@lang('human_resources::checklists.fields.description')</label>

    <textarea
        id="{{ $prefix }}-description"
        rows="3"
        maxlength="5000"
        v-model="{{ $model }}.description"
        class="{{ $input }}"
    ></textarea>
</div>

<div class="grid grid-cols-2 gap-x-4 max-sm:grid-cols-1">
    <div class="mb-4">
        <label class="{{ $label }}" for="{{ $prefix }}-employee">@lang('human_resources::checklists.fields.employee')</label>

        <select data-searchable id="{{ $prefix }}-employee" v-model="{{ $model }}.employee_id" class="{{ $input }}">
            <option value="">@lang('human_resources::checklists.none')</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">@{{ employee.name }}</option>
        </select>
    </div>

    <div class="mb-4">
        <label class="{{ $label }}" for="{{ $prefix }}-work-site">@lang('human_resources::checklists.fields.work_site')</label>

        <select data-searchable id="{{ $prefix }}-work-site" v-model="{{ $model }}.work_site_id" class="{{ $input }}">
            <option value="">@lang('human_resources::checklists.none')</option>
            <option v-for="site in workSites" :key="site.id" :value="site.id">@{{ site.name }}</option>
        </select>
    </div>

    <div class="mb-4">
        <label class="{{ $label }}" for="{{ $prefix }}-assignee">@lang('human_resources::checklists.fields.assignee')</label>

        <select data-searchable id="{{ $prefix }}-assignee" v-model="{{ $model }}.assigned_to" class="{{ $input }}">
            <option value="">@lang('human_resources::checklists.none')</option>
            <option v-for="user in users" :key="user.id" :value="user.id">@{{ user.name }}</option>
        </select>
    </div>

    <div class="mb-4">
        <label class="{{ $label }}" for="{{ $prefix }}-due-date">@lang('human_resources::checklists.fields.due_date')</label>

        <input
            id="{{ $prefix }}-due-date"
            type="date"
            v-model="{{ $model }}.due_date"
            class="{{ $input }}"
        >
    </div>
</div>
