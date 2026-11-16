@extends('human_resources::ai.pdf-layout', ['issued' => now()->format('d/m/Y H:i')])

@section('document')
    <main>
        @php
            $body = \Illuminate\Support\Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        @endphp
        {!! preg_replace('/<img\b[^>]*>/i', '', $body) !!}
    </main>
@endsection
