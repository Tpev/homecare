<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
        <lastmod>{{ $today }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>{{ str_contains($url, 'raleigh-home-care') || str_ends_with($url, '/') ? '1.0' : '0.8' }}</priority>
    </url>
@endforeach
</urlset>

