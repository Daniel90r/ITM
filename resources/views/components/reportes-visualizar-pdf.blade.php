@props(['href'])
@once
    @push('styles')
    <style>.reportes-preview{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px 16px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600}.reportes-preview:hover{background:#f1f5f9}.reportes-preview:focus-visible{outline:3px solid #2563eb;outline-offset:3px}.reportes-preview[aria-disabled=true]{opacity:.55;cursor:not-allowed}@media(max-width:640px){.reportes-preview{width:100%}}</style>
    @endpush
@endonce
<a href="{{ $href }}" {{ $attributes->class(['reportes-preview']) }}><span aria-hidden="true">▤</span> Visualizar PDF</a>
