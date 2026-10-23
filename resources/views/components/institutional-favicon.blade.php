@php($faviconVersion = substr(hash_file('sha256', public_path('favicon.ico')), 0, 12))
<link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/itm-32.png') }}?v={{ $faviconVersion }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicons/itm-192.png') }}?v={{ $faviconVersion }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicons/itm-180.png') }}?v={{ $faviconVersion }}">
