<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ITM Aguilares</title>
    <x-institutional-favicon />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>* { font-family: 'DM Sans', sans-serif; }</style>
</head>
<body style="background:#0f172a; color:#fff; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;">
    {{ $slot }}
</body>
</html>
