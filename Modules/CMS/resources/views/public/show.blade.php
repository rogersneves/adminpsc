<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $tenantName }}</title>
    @if($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2937; line-height: 1.5; }
        img { max-width: 100%; height: auto; }
        {{-- CSS já sanitizado pelo HtmlSanitizer (sem @import/expression/javascript). --}}
        {!! $css !!}
    </style>
</head>
<body>
    {{-- HTML já sanitizado pelo HtmlSanitizer no momento de salvar. --}}
    {!! $html !!}
</body>
</html>
