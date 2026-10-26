@php
    $staffResponsive = auth()->check() && in_array(auth()->user()->role, ['admin', 'docente'], true) && request()->routeIs('admin.*', 'docente.*') && !request()->routeIs('docente.asistencia.reporte');
    $adminLayout = $staffResponsive && auth()->user()->role === 'admin';
@endphp
@if($staffResponsive && auth()->user()->role === 'admin')
    @include('components.filtros-autocompletado')
@endif
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if($adminLayout) class="itm-admin-document" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ITM Aguilares</title>
    <x-institutional-favicon />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        .fade-in { animation: fadeIn .25s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .campo-secreto {
            -webkit-text-security: disc;
            text-security: disc;
        }
    </style>
    @if($staffResponsive)
        <style>{!! file_get_contents(resource_path('css/staff/responsive.css')) !!}</style>
    @endif
    @if($adminLayout)
        <style>{!! file_get_contents(resource_path('css/admin/layout.css')) !!}</style>
    @endif
</head>
<body class="font-sans{{ $staffResponsive ? ' itm-staff' : '' }}{{ $adminLayout ? ' itm-admin' : '' }}" style="background:{{ $adminLayout ? '#f8fafc' : '#0f172a' }}; margin:0;">
    @if($staffResponsive)
        @include('components.staff-mobile-bar')
    @endif
    {{ $slot }}
    @stack('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar.sidebar-scroll') || document.querySelector('aside[style*="overflow-y:auto"]');
            if (sidebar) {
                const scrollKey = 'sidebarScrollPosition';
                const savedScroll = localStorage.getItem(scrollKey);
                if (savedScroll) {
                    sidebar.scrollTop = parseInt(savedScroll);
                }
                sidebar.addEventListener('scroll', function() {
                    localStorage.setItem(scrollKey, this.scrollTop);
                });
            }

            if (typeof window.mostrarNotificacion === 'function') {
                @if(session('success'))
                window.mostrarNotificacion("{{ session('success') }}", "exito");
                @endif
                @if(session('error'))
                window.mostrarNotificacion("{{ session('error') }}", "peligro");
                @endif
            }
        });
    </script>
    @if($staffResponsive)
        <script>{!! file_get_contents(resource_path('js/staff/responsive.js')) !!}</script>
    @endif
</body>
</html>
<style>
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        font-size: 13px;
        text-decoration: none;
        transition: all .15s;
        cursor: pointer;
        color: #6b7280;
        font-weight: 400;
    }
    .sidebar-link:hover {
        background: #f3f4f6;
        color: #111827;
    }
    .sidebar-link.active {
        background: #111827 !important;
        color: #ffffff !important;
        font-weight: 600;
    }
</style>
