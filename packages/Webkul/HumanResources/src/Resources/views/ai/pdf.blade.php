<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { color: #142443; font: 12px 'DejaVu Sans', sans-serif; margin: 36px; }
        h1 { border-bottom: 2px solid #244a96; font-size: 21px; padding-bottom: 12px; }
        section { line-height: 1.55; overflow-wrap: break-word; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <section>{!! nl2br(e($content)) !!}</section>
</body>
</html>
