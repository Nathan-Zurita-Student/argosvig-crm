<section id="incident-images" aria-labelledby="incident-images-heading" class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 sm:p-5">
    <h2 id="incident-images-heading" class="font-semibold text-gray-800 dark:text-white">
        @lang('human_resources::app.incidents.images.title')
    </h2>

    @if ($images->total() === 0)
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">@lang('human_resources::app.incidents.images.empty')</p>
    @else
        <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($images as $image)
                @php($url = route('admin.human_resources.incidents.images.show', [$incident->id, $image->id]))

                <li class="min-w-0">
                    <a href="{{ $url }}" class="block overflow-hidden rounded-md border border-gray-200 text-brandColor hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandColor dark:border-gray-700 dark:text-blue-300">
                        <img src="{{ $url }}" alt="" loading="lazy" decoding="async" class="aspect-[4/3] w-full bg-gray-50 object-contain dark:bg-gray-950">
                        <span class="block break-all px-3 py-2 text-sm font-medium">{{ $image->original_name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @include('human_resources::workforce.pagination', ['page' => $images])
    @endif
</section>
