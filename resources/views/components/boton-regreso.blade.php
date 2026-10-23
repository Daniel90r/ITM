@props(['href' => null])
@once
    @push('styles')
    <style>
        .itm-back{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;max-width:100%;padding:10px 14px;flex-shrink:0;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#1e293b;font-size:13px;font-weight:600;line-height:20px;text-align:center;text-decoration:none;cursor:pointer;transition:background-color .15s,border-color .15s}
        .itm-back:hover{background:#e2e8f0;border-color:#94a3b8;color:#0f172a;text-decoration:none}
        .itm-back:focus-visible{outline:3px solid #2563eb;outline-offset:3px}
        .itm-back svg{flex-shrink:0}
        @media(max-width:640px){.itm-back{white-space:normal}}
        @media(prefers-reduced-motion:reduce){.itm-back{transition:none}}
    </style>
    @endpush
@endonce
@if($href)
    <a href="{{ $href }}" {{ $attributes->class(['itm-back']) }}>
@else
    <button type="button" {{ $attributes->class(['itm-back']) }}>
@endif
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m10 5-7 7 7 7M3 12h18" /></svg>
    <span>{{ $slot }}</span>
@if($href)
    </a>
@else
    </button>
@endif
