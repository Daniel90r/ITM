<x-app-layout>
    @push('styles')
    <style>
        /* El body compartido es azul: Reportes ocupa el viewport, sin footer propio. */
        .reportes-layout{position:fixed;inset:0;display:flex;height:100vh;height:100dvh;overflow:hidden;background:#f8fafc;color:#111827}
        .reportes-sidebar{flex:0 0 220px;height:100vh;height:100dvh;min-height:0;overflow:hidden}
        /* details puede dejar indefinida la altura porcentual de su contenido. */
        .reportes-sidebar aside{height:100vh;height:100dvh;min-height:0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain}
        .reportes-sidebar aside > *{flex-shrink:0}
        .reportes-main{flex:1;min-width:0;min-height:0;overflow-y:auto;overflow-x:hidden;padding:24px;max-width:1600px}
        .reportes-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:20px 0}
        .reportes-card{display:block;border:1px solid #e5e7eb;border-radius:12px;background:white;padding:16px;color:#374151;text-decoration:none}
        .reportes-card:hover,.reportes-card[aria-current=page]{border-color:#111827;background:#f1f5f9}
        .reportes-filters{display:flex;flex-wrap:wrap;align-items:end;gap:12px;padding:20px;background:white;border:1px solid #e5e7eb;border-radius:12px}
        .reportes-filters label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:600;max-width:100%}
        .reportes-filters select,.reportes-filters input{border:1px solid #d1d5db;border-radius:8px;font-size:13px;max-width:100%;min-height:40px}
        .reportes-filters button{background:#111827;color:white;padding:10px 18px;border-radius:8px;font-size:13px}
        .reportes-actions{display:flex;align-items:center;flex-wrap:wrap;gap:12px;width:100%;grid-column:1/-1;border-top:1px solid #eef2f6;padding-top:16px}
        .reportes-main .reportes-pdf{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;margin-left:auto;padding:10px 16px;border:1px solid transparent;border-radius:8px;background:#0f172a;color:#fff;font-size:13px;font-weight:600;line-height:20px;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background-color .15s,border-color .15s}
        .reportes-main .reportes-pdf:hover{background:#1e3a5f}
        .reportes-main .reportes-pdf:focus-visible{outline:3px solid #2563eb;outline-offset:3px}
        .reportes-main .reportes-pdf[aria-disabled=true]{background:#e2e8f0;color:#475569;border-color:#cbd5e1;cursor:not-allowed}
        .reportes-pdf svg{flex-shrink:0}
        @media(max-width:640px){.reportes-main .reportes-pdf{width:100%;margin-left:0;white-space:normal}}
        @media(prefers-reduced-motion:reduce){.reportes-main .reportes-pdf{transition:none}}
        .reportes-summary{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0}
        .reportes-summary div{background:white;border:1px solid #e5e7eb;border-radius:12px;padding:16px;min-width:150px}
        .reportes-summary dt{color:#6b7280;font-size:12px}.reportes-summary dd{font-size:24px;font-weight:700;margin-top:4px}
        .reportes-table{max-width:100%;overflow:auto;scrollbar-color:#94a3b8 #f1f5f9;background:white;border:1px solid #e5e7eb;border-radius:12px;margin:16px 0}
        .reportes-table::-webkit-scrollbar-track{background:#f1f5f9}
        .reportes-table::-webkit-scrollbar-thumb{background:#94a3b8}
        .reportes-table table{width:100%;border-collapse:collapse;font-size:13px;text-align:left}
        .reportes-table th{background:#f1f5f9;font-size:12px;white-space:nowrap}
        .reportes-table th,.reportes-table td{padding:14px 16px;border-bottom:1px solid #e5e7eb;vertical-align:top}
        .reportes-table tbody tr:hover{background:#f8fafc}
        .reportes-sidebar summary{display:none}
        @media(max-width:900px){.reportes-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.reportes-layout{position:static;inset:auto;flex-direction:column;height:auto;min-height:100vh;min-height:100dvh;overflow:visible}.reportes-sidebar{flex:0 0 auto;height:auto;overflow:visible}.reportes-main{padding:16px;overflow:visible}.reportes-sidebar summary{display:block;padding:12px 16px;background:white;cursor:pointer;border-bottom:1px solid #e5e7eb}.reportes-sidebar aside{width:100%!important;height:auto;max-height:55vh}.reportes-cards{grid-template-columns:1fr}.reportes-filters label{width:100%}.reportes-summary div{flex:1}}
    </style>
    @endpush
    <div class="reportes-layout">
        <details class="reportes-sidebar" open>
            <summary>Menú de administración</summary>
            @include('components.admin-sidebar', ['active' => 'reportes'])
        </details>
        <main class="reportes-main">
            <header>
                {{ $encabezado }}
                <p class="text-sm text-gray-500 mt-1">Consulta académica y administrativa · Solo lectura</p>
            </header>
            {{ $slot }}
        </main>
    </div>
</x-app-layout>
