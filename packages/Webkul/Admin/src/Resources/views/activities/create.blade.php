<x-admin::layouts>
    <x-slot:title>@lang('admin::app.components.activities.actions.activity.title')</x-slot>

    @php($activity = 'admin::app.components.activities.actions.activity.')

    <x-admin::form :action="route('admin.activities.store')">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <h1 class="text-xl font-bold dark:text-white">@lang($activity . 'title')</h1>

                <div class="flex items-center gap-2">
                    @if (bouncer()->hasPermission('activities'))
                        <a href="{{ route('admin.activities.index') }}" class="secondary-button">@lang('admin::app.layouts.activities')</a>
                    @endif

                    <button type="submit" class="primary-button">@lang($activity . 'save-btn')</button>
                </div>
            </div>

            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="type" class="required">@lang('admin::app.activities.index.type')</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control type="select" id="type" name="type" :value="old('type', 'call')" rules="required">
                        @foreach (['call', 'meeting', 'lunch'] as $type)
                            <option value="{{ $type }}">{{ trans($activity . $type) }}</option>
                        @endforeach
                        <option value="note">@lang('admin::app.components.activities.actions.note.btn')</option>
                    </x-admin::form.control-group.control>
                    <x-admin::form.control-group.error control-name="type" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="title" class="required">@lang($activity . 'title-control')</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control type="text" id="title" name="title" :value="old('title')" rules="required|max:80" maxlength="80" />
                    <x-admin::form.control-group.error control-name="title" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="location">@lang($activity . 'location')</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control type="text" id="location" name="location" :value="old('location')" maxlength="255" />
                    <x-admin::form.control-group.error control-name="location" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label for="comment">@lang($activity . 'description')</x-admin::form.control-group.label>
                    <x-admin::form.control-group.control type="textarea" id="comment" name="comment" :value="old('comment')" maxlength="500" />
                    <x-admin::form.control-group.error control-name="comment" />
                </x-admin::form.control-group>

                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                    @foreach (['schedule_from' => 'schedule-from', 'schedule_to' => 'schedule-to'] as $name => $label)
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label :for="$name">@lang($activity . $label)</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control type="datetime" :id="$name" :name="$name" :value="old($name)" />
                            <x-admin::form.control-group.error :control-name="$name" />
                        </x-admin::form.control-group>
                    @endforeach
                </div>
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
