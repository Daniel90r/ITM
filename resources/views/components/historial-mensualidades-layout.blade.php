<x-app-layout>
    @push('styles')
    <style>
        .hm-layout{display:flex;min-height:100vh;background:#f8fafc;color:#1e293b}.hm-main{flex:1;min-width:0;padding:24px}.hm-main h1{font-size:24px;font-weight:700;margin:16px 0 4px}.hm-note{font-size:13px;color:#64748b;line-height:1.6;margin:10px 0}.hm-panel{background:white;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin:18px 0}.hm-filters{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.hm-filters label{display:flex;flex-direction:column;font-size:13px;gap:6px}.hm-filters input,.hm-filters select{border:1px solid #cbd5e1;border-radius:8px;min-height:44px;padding:8px;max-width:100%;box-sizing:border-box}.hm-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;background:#0f172a;color:white;border-radius:8px;padding:8px 14px;text-decoration:none;font-size:13px}.hm-main :is(a,button,input,select):focus-visible{outline:3px solid #2563eb;outline-offset:3px}.hm-summary{display:flex;flex-wrap:wrap;gap:12px}.hm-summary div{flex:1;min-width:160px;background:white;border:1px solid #e2e8f0;border-radius:10px;padding:16px}.hm-summary dt{font-size:12px;color:#64748b}.hm-summary dd{font-size:24px;font-weight:700;margin-top:6px}.hm-table{overflow:auto;background:white;border:1px solid #e2e8f0;border-radius:12px;margin:16px 0}.hm-table table{width:100%;border-collapse:collapse;min-width:720px;font-size:13px}.hm-table th,.hm-table td{padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.hm-table thead{background:#f1f5f9}.hm-warning{color:#92400e;background:#fffbeb;padding:8px;border-radius:6px;font-size:12px;margin-top:6px}.hm-state{font-weight:600}.hm-state[data-estado=Pagado]{color:#166534}.hm-state[data-estado=Vencido]{color:#9f1239}
        @media(max-width:640px){.hm-layout{flex-direction:column}.hm-main{padding:16px}.hm-sidebar aside{width:100%!important;box-sizing:border-box}.hm-filters label{width:100%}.hm-summary div{min-width:120px}}
    </style>
    @endpush
    <div class="hm-layout">
        <details class="hm-sidebar" open><summary>Menú de administración</summary>@include('components.admin-sidebar', ['active' => 'mensualidades'])</details>
        <main class="hm-main">{{ $slot }}</main>
    </div>
</x-app-layout>
