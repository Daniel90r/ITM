@props(['href' => null, 'disabled' => false, 'previewHref' => null])
@if($disabled)
    <button type="button" disabled aria-disabled="true" {{ $attributes->class(['reportes-pdf']) }}>
@else
    <a href="{{ $href }}" {{ $attributes->class(['reportes-pdf']) }}>
@endif
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4" />
    </svg>
    <span>Descargar PDF</span>
@if($disabled)
    </button>
@else
    </a>
@endif
@if(!$disabled && $previewHref)
    <x-reportes-visualizar-pdf :href="$previewHref" />
@endif
