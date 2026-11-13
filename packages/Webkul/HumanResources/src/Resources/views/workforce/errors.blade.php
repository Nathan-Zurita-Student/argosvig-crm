@if ($errors->any())
    <ul role="alert" class="my-3 list-inside list-disc text-sm text-red-700 dark:text-red-300">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
