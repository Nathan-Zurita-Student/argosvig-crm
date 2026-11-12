<x-admin::layouts>
    <x-slot:title>{{ $employee->full_name }}</x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">{{ $employee->full_name }}</p>
                <p class="text-sm text-gray-500">
                    {{ $employee->employee_number }} · {{ $employee->job_title ?: trans('human_resources::app.common.not_informed') }}
                </p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.human_resources.employees.index') }}" class="secondary-button">
                    @lang('human_resources::app.common.back')
                </a>

                @if (bouncer()->hasPermission('human-resources.employees.edit'))
                    <a href="{{ route('admin.human_resources.employees.edit', $employee->id) }}" class="primary-button">
                        @lang('human_resources::app.common.edit')
                    </a>
                @endif
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
                <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('human_resources::app.employees.profile.personal_data')
                </p>

                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        trans('human_resources::app.employees.fields.cpf') => $employee->cpf,
                        trans('human_resources::app.employees.fields.rg') => $employee->rg,
                        trans('human_resources::app.employees.fields.birth_date') => $employee->birth_date?->format('d/m/Y'),
                        trans('human_resources::app.employees.fields.email') => $employee->email,
                        trans('human_resources::app.employees.fields.phone') => $employee->phone,
                        trans('human_resources::app.employees.fields.department') => $employee->department?->name,
                        trans('human_resources::app.employees.fields.hire_date') => $employee->hire_date?->format('d/m/Y'),
                        trans('human_resources::app.employees.fields.alterdata_employee_id') => $employee->alterdata_employee_id,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-semibold uppercase text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($employee->notes)
                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500">@lang('human_resources::app.employees.fields.notes')</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $employee->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('human_resources::app.common.status')</p>
                <span class="mt-3 inline-flex rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                    @lang("human_resources::app.employees.statuses.{$employee->status}")
                </span>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
                <div class="mb-4">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('human_resources::app.documents.title')</p>
                    <p class="text-sm text-gray-500">@lang('human_resources::app.documents.description')</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                            <tr>
                                <th class="px-2 py-3">@lang('human_resources::app.documents.fields.name')</th>
                                <th class="px-2 py-3">@lang('human_resources::app.documents.fields.type')</th>
                                <th class="px-2 py-3">@lang('human_resources::app.documents.fields.expires_at')</th>
                                <th class="px-2 py-3">@lang('human_resources::app.common.status')</th>
                                <th class="px-2 py-3 text-right">@lang('human_resources::app.documents.fields.actions')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employee->documents->sortByDesc('created_at') as $document)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-2 py-3">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $document->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $document->original_name }} · {{ number_format($document->size / 1024, 1, ',', '.') }} KB</p>
                                    </td>
                                    <td class="px-2 py-3">@lang("human_resources::app.documents.types.{$document->type}")</td>
                                    <td class="px-2 py-3">{{ $document->expires_at?->format('d/m/Y') ?: '—' }}</td>
                                    <td class="px-2 py-3">
                                        <span class="{{ $document->is_expired ? 'text-red-600' : 'text-green-600' }}">
                                            @lang($document->is_expired ? 'human_resources::app.documents.statuses.expired' : 'human_resources::app.documents.statuses.valid')
                                        </span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a class="text-brandColor hover:underline" href="{{ route('admin.human_resources.employee_documents.download', [$employee->id, $document->id]) }}">
                                                @lang('human_resources::app.documents.download')
                                            </a>

                                            @if (bouncer()->hasPermission('human-resources.employees.documents'))
                                                <x-admin::form :action="route('admin.human_resources.employee_documents.delete', [$employee->id, $document->id])" method="DELETE">
                                                    <button type="submit" class="text-red-600 hover:underline">@lang('human_resources::app.common.delete')</button>
                                                </x-admin::form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-8 text-center text-gray-500">@lang('human_resources::app.documents.empty')</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (bouncer()->hasPermission('human-resources.employees.documents'))
                <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">@lang('human_resources::app.documents.upload')</p>

                    <x-admin::form :action="route('admin.human_resources.employee_documents.store', $employee->id)" method="POST" enctype="multipart/form-data">
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">@lang('human_resources::app.documents.fields.type')</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control type="select" name="type" rules="required" :label="trans('human_resources::app.documents.fields.type')">
                                @foreach (['identity', 'contract', 'certificate', 'occupational_health', 'payslip', 'other'] as $type)
                                    <option value="{{ $type }}">@lang("human_resources::app.documents.types.{$type}")</option>
                                @endforeach
                            </x-admin::form.control-group.control>
                            <x-admin::form.control-group.error control-name="type" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>@lang('human_resources::app.documents.fields.name')</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control type="text" name="name" :label="trans('human_resources::app.documents.fields.name')" />
                            <x-admin::form.control-group.error control-name="name" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label>@lang('human_resources::app.documents.fields.expires_at')</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control type="date" name="expires_at" :label="trans('human_resources::app.documents.fields.expires_at')" />
                            <x-admin::form.control-group.error control-name="expires_at" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">@lang('human_resources::app.documents.fields.file')</x-admin::form.control-group.label>
                            <x-admin::form.control-group.control type="file" name="file" rules="required" :label="trans('human_resources::app.documents.fields.file')" />
                            <x-admin::form.control-group.error control-name="file" />
                        </x-admin::form.control-group>

                        <button type="submit" class="primary-button w-full justify-center">@lang('human_resources::app.documents.upload')</button>
                    </x-admin::form>
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
