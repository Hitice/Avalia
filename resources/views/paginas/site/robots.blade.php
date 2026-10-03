User-agent: *
@foreach ($bloqueios as $caminho)
Disallow: {{ $caminho }}
@endforeach
Allow: /

Sitemap: {{ route('site.sitemap') }}
