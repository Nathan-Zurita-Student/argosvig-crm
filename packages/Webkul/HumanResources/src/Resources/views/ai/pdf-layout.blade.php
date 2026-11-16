<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 60pt 43pt 57pt; }
        body { margin: 0; color: #172337; font: 10pt "DejaVu Sans", sans-serif; line-height: 1.48; }
        header {
            position: fixed; top: -38pt; left: 0; right: 0;
            border-bottom: 1pt solid #29477d; padding: 0 0 8pt;
        }
        footer {
            position: fixed; bottom: -35pt; left: 0; right: 0;
            border-top: .65pt solid #bbc7d8; color: #4b5b71; font-size: 8pt; padding: 8pt 0 0;
        }
        .wordmark { font-size: 12pt; letter-spacing: 1.4pt; font-weight: 700; color: #122750; }
        .right { float: right; text-align: right; }
        header .right { margin-top: 3pt; font-size: 8pt; color: #4b5b71; }
        .page-number::after { content: counter(page); }
        h1 { margin: 16pt 0 7pt; font-size: 25pt; line-height: 1.16; color: #102653; font-weight: 700; }
        h2 { margin: 24pt 0 9pt; font-size: 15pt; line-height: 1.25; color: #102653; }
        h3 { margin: 12pt 0 6pt; font-size: 11pt; line-height: 1.25; color: #172337; }
        h2, h3, h4, h5, h6 { page-break-after: avoid; }
        h4, h5, h6 { margin: 12pt 0 6pt; font-size: 10pt; }
        p { margin: 0 0 10pt; overflow-wrap: break-word; }
        ul, ol { margin: 6pt 0 10pt 17pt; padding: 0; }
        li { margin-bottom: 3pt; }
        blockquote { margin: 10pt 0; padding: 2pt 0 2pt 10pt; border-left: 2pt solid #bbc7d8; }
        pre { white-space: pre-wrap; overflow-wrap: break-word; }
        hr { border: 0; border-top: 1pt solid #c8d3e2; }
        a { color: #234b88; }
        .meta { font-size: 9pt; color: #4b5b71; margin-bottom: 15pt; }
        .standfirst { color: #334967; font-size: 11pt; margin-bottom: 14pt; }
        .note { color: #4b5b71; font-size: 8.5pt; line-height: 1.45; }
        .chapter-first { page-break-before: always; }
        .chapter > h2 { margin: 18pt 0 11pt; padding-bottom: 7pt; border-bottom: 1pt solid #29477d; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; margin: 5pt 0 11pt; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { text-align: left; vertical-align: top; padding: 5pt 5pt; border-bottom: .55pt solid #d4dce7; overflow-wrap: break-word; }
        th { color: #132a53; background-color: #eef2f7; font-weight: 700; }
        td { color: #233248; }
        .index-heading { margin-top: 15pt; }
        .index { font-size: 9pt; line-height: 1.18; }
        .index th, .index td { padding: 3pt 5pt; }
        .records { font-size: 8.8pt; line-height: 1.3; table-layout: auto; }
        .records th, .records td { padding: 5pt 5pt; }
        .numeric { width: 83pt; text-align: right; }
        .group-name { width: 121pt; }
        .keep { page-break-inside: avoid; }
        .priorities { margin: 0 0 9pt; font-size: 9pt; color: #4b5b71; }
        .priorities strong { color: #102653; }
        .filters { margin: 12pt 0; font-size: 9pt; color: #4b5b71; }
        .records thead tr, .records .keep-next { page-break-after: avoid; }
        .records .section-heading { background: #fff; border: 0; padding: 6pt 0 2pt; font-size: 11pt; }
        .records .section-range { background: #fff; border: 0; padding: 0 0 7pt; font-weight: 400; color: #4b5b71; }
        .continued { font-size: 8pt; font-weight: 400; color: #4b5b71; }
    </style>
</head>
<body>
    <header>
        <span class="right">@lang('human_resources::reports.header')</span>
        <span class="wordmark">ARGOSVIG</span>
    </header>
    <footer>
        <span class="page-number right">@lang('human_resources::reports.page') </span>
        @lang('human_resources::reports.internal') &nbsp; · &nbsp; {{ $issued }}
    </footer>

    <h1>{{ $title }}</h1>
    @yield('document')
</body>
</html>
