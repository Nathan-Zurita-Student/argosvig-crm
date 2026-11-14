@props(['count' => 30])

<div class="flex gap-1.5">
    <div class="grid">
        @foreach (range(1, 10) as $i)
            <div class="shimmer h-[15px] w-[39px]"></div>
        @endforeach
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
        <div class="flex aspect-[3.23/1] w-full items-end border-b border-l pl-2.5 dark:border-gray-800">
            <div class="flex aspect-[3.23/1] w-full items-end justify-between gap-0.5">
                @foreach (range(1, $count) as $i)
                    <div
                        class="shimmer min-w-0 flex-1"
                        @style(['height: '.random_int(10, 100).'%'])
                    ></div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-between gap-0.5 pl-2.5">
            @foreach (range(1, $count) as $i)
                <div class="shimmer h-[15px] min-w-0 flex-1"></div>
            @endforeach
        </div>
    </div>
</div>
