<x-admin::shimmer.leads.index.kanban.toolbar />

<div class="flex gap-2.5 md:max-h-[calc(100vh-300px)] md:overflow-x-auto" aria-hidden="true">
    <!-- Stages -->
    @for ($i = 1; $i <= 6; $i++)
        <div @class([
            'flex w-full min-w-0 flex-col gap-1 rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900 md:w-[275px] md:shrink-0',
            'max-md:hidden' => $i > 1,
        ])>
            <!-- Stage Header -->
            <div class="flex flex-col px-2 py-3">
                <div class="flex items-center justify-between">
                    <div class="shimmer h-4 w-20"></div>
                    <div class="shimmer h-[26px] w-[26px] rounded-md"></div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <div class="shimmer h-4 w-14"></div>
                    <div class="shimmer h-1 w-36"></div>
                </div>
            </div>

            <!-- Stage Lead Cards -->
            <div class="flex flex-col gap-2 p-2 md:h-[calc(100vh-317px)] md:overflow-hidden">
                @for ($j = 1; $j <= 3; $j++)
                    <!-- Card -->
                    <div class="flex w-full flex-col gap-5 rounded-lg border p-2 dark:border-gray-800">
                        <!-- Header -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-1">
                                <!-- Avatar -->
                                <div class="shimmer h-9 w-9 rounded-full"></div>

                                <!-- Name and Organization -->
                                <div class="flex flex-col gap-0.5">
                                    <div class="shimmer h-4 w-20"></div>
                                    <div class="shimmer h-[15px] w-12"></div>
                                </div>
                            </div>

                            <!-- Rotten Lead Info -->
                            <div class="shimmer h-5 w-5 rounded-md"></div>
                        </div>

                        <!-- Body -->
                        <div class="flex flex-col gap-0.5">
                            <div class="shimmer h-4 w-full"></div>
                            <div class="shimmer h-4 w-1/2"></div>
                        </div>

                        <!-- Footer -->
                        <div class="flex flex-wrap gap-1">
                            <div class="shimmer h-6 w-16 rounded-xl"></div>
                            <div class="shimmer h-6 w-16 rounded-xl"></div>
                            <div class="shimmer h-6 w-16 rounded-xl"></div>
                            <div class="shimmer h-6 w-16 rounded-xl"></div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @endfor
</div>
