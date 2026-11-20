<x-admin::layouts>
    <x-slot:title>
        {{ menu()->getLabel('dashboard', 'admin::app.dashboard.index.title') }}
    </x-slot>

    <!-- Head Details Section -->
    {!! view_render_event('admin.dashboard.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        {!! view_render_event('admin.dashboard.index.header.left.before') !!}

        <div class="grid gap-1.5">
            <h1 class="text-2xl font-semibold dark:text-white">
                {{ menu()->getLabel('dashboard', 'admin::app.dashboard.index.title') }}
            </h1>
        </div>

        {!! view_render_event('admin.dashboard.index.header.left.after') !!}

        <!-- Actions -->
        {!! view_render_event('admin.dashboard.index.header.right.before') !!}

        <v-dashboard-filters>
            <!-- Shimmer -->
            <div class="flex flex-wrap gap-1.5">
                @if ($pipelines->count() > 1)
                    <div class="light-shimmer-bg dark:shimmer h-[39px] w-[160px] rounded-md"></div>
                @endif

                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[110px] rounded-md"></div>
            </div>
        </v-dashboard-filters>

        {!! view_render_event('admin.dashboard.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.header.after') !!}

    @if ($operationalStats !== [])
    <section class="mb-5 overflow-hidden rounded-xl bg-gradient-to-r from-[#061534] via-[#0a2454] to-[#163d7a] p-6 text-white shadow-lg">
        <div class="flex flex-wrap items-center justify-between gap-5">
            <div>
                <h2 class="text-2xl font-bold">@lang('human_resources::app.dashboard.title')</h2>
                <p class="mt-1 max-w-2xl text-sm text-blue-100">@lang('human_resources::app.dashboard.description')</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (['employees' => 'employees', 'active_contracts' => 'contracts', 'open_incidents' => 'incidents'] as $stat => $module)
                    @if (isset($operationalStats[$stat]))
                        <a href="{{ route('admin.human_resources.'.$module.'.index') }}" class="{{ $module === 'employees' ? 'rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-950 shadow-sm hover:bg-blue-50' : 'rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white' }}">@lang('human_resources::app.menu.'.$module)</a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <section class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @foreach (['open_incidents', 'documents_expiring', 'active_contracts', 'active_employees', 'monthly_contract_value'] as $metric)
            @continue(! isset($operationalStats[$metric]))
            @php
                [$label, $detail, $color] = match ($metric) {
                    'active_employees' => ['active_employees', trans('human_resources::app.dashboard.total_employees', ['count' => $operationalStats['employees']]), 'text-gray-900 dark:text-white'],
                    'active_contracts' => ['active_contracts', trans('human_resources::app.dashboard.expiring_contracts', ['count' => $operationalStats['contracts_expiring']]), 'text-gray-900 dark:text-white'],
                    'monthly_contract_value' => ['monthly_value', isset($operationalStats['departments']) ? trans('human_resources::app.dashboard.departments', ['count' => $operationalStats['departments']]) : null, 'text-gray-900 dark:text-white'],
                    'documents_expiring' => ['expiring_documents', trans('human_resources::app.dashboard.next_thirty_days'), $operationalStats[$metric] ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-white'],
                    'open_incidents' => ['open_incidents', trans('human_resources::app.dashboard.requires_attention'), $operationalStats[$metric] ? 'text-red-700 dark:text-red-300' : 'text-gray-900 dark:text-white'],
                };
                $metricUrl = match ($metric) {
                    'open_incidents' => route('admin.human_resources.incidents.index'),
                    'documents_expiring' => route('admin.human_resources.document_center.index', ['expiry' => 'soon']),
                    default => null,
                };
            @endphp
            <div class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    @if ($metricUrl)
                        <a href="{{ $metricUrl }}" class="underline decoration-gray-400 underline-offset-4 hover:decoration-current">@lang('human_resources::app.dashboard.'.$label)</a>
                    @else
                        @lang('human_resources::app.dashboard.'.$label)
                    @endif
                </p>
                {{-- Currency values are longer than counters and overflowed the five-column row. --}}
                <p class="mt-2 break-words font-bold {{ $metric === 'monthly_contract_value' ? 'text-2xl xl:text-xl 2xl:text-2xl' : 'text-3xl' }} {{ $color }}">{{ $metric === 'monthly_contract_value' ? core()->formatBasePrice($operationalStats[$metric]) : $operationalStats[$metric] }}</p>
                @if ($detail)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $detail }}</p>
                @endif
            </div>
        @endforeach
    </section>
    @endif

    <!-- Body Component -->
    {!! view_render_event('admin.dashboard.index.content.before') !!}

    <div class="mt-3.5 flex gap-4 max-xl:flex-wrap">
        <!-- Left Section -->
        {!! view_render_event('admin.dashboard.index.content.left.before') !!}

        <div class="flex min-w-0 flex-1 flex-col gap-4 max-xl:flex-auto">
            <!-- Revenue Stats -->
            @include('admin::dashboard.index.revenue')

            <!-- Over All Stats -->
            @include('admin::dashboard.index.over-all')

            <!-- Total Leads Stats -->
            @include('admin::dashboard.index.total-leads')

            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Total Products -->
                @include('admin::dashboard.index.top-selling-products')

                <!-- Total Persons -->
                @include('admin::dashboard.index.top-persons')
            </div>
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            <!-- Revenue by Types -->
            @include('admin::dashboard.index.open-leads-by-states')

            <!-- Revenue by Sources -->
            @include('admin::dashboard.index.revenue-by-sources')

            <!-- Revenue by Types -->
            @include('admin::dashboard.index.revenue-by-types')
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.content.after') !!}

    @pushOnce('scripts')

        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="module"
            src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js"
            integrity="sha384-3s9Buv17hNMrULiw1fw1xLUac5oZZitue3rXD5XSWw8WACZcFJk1N79K4WeKwkDZ"
            crossorigin="anonymous"
        >
        </script>

        <script
            type="text/x-template"
            id="v-dashboard-filters-template"
        >
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex flex-wrap gap-1.5">
                @if ($pipelines->count() > 1)
                    <!-- Pipeline Selector -->
                    <select
                        data-searchable
                        class="custom-select flex min-h-[39px] w-[160px] rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-normal text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.pipeline_id"
                    >
                        @foreach ($pipelines as $pipeline)
                            <option value="{{ $pipeline->id }}">{{ \Webkul\Admin\Helpers\CommercialLabel::display($pipeline->name, 'pipeline') }}</option>
                        @endforeach
                    </select>
                @endif

                {{--
                    One range control in place of the previous pair of start/end inputs: picking
                    "last 30 days" or "this month" was otherwise a manual job of choosing two dates
                    and knowing today's, and neither input showed the range as a whole.
                --}}
                <x-admin::flat-picker.date-range
                    ::start-date="filters.start"
                    ::end-date="filters.end"
                    @change="applyDateRange"
                />

                <!-- Export PDF -->
                <a
                    :href="exportUrl"
                    target="_blank"
                    class="secondary-button !min-h-[39px]"
                >
                    @lang('admin::app.dashboard.index.export-pdf')
                </a>
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script type="module">
            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        filters: {
                            channel: '',

                            pipeline_id: "{{ $defaultPipeline?->id }}",

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}",
                        }
                    }
                },

                computed: {
                    exportUrl() {
                        let params = new URLSearchParams({
                            start: this.filters.start,
                            end: this.filters.end,
                        });

                        if (this.filters.pipeline_id) {
                            params.set('pipeline_id', this.filters.pipeline_id);
                        }

                        return "{{ route('admin.dashboard.export_pdf') }}?" + params.toString();
                    }
                },

                methods: {
                    /**
                     * The range picker reports both ends together, so they are applied in one go —
                     * setting them separately would emit an intermediate range (the new start with
                     * the old end) and send every report a query nobody asked for.
                     */
                    applyDateRange({ start, end }) {
                        this.filters.start = start;

                        this.filters.end = end;
                    },
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
