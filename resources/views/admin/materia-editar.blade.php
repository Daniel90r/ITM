<x-app-layout>
@push('styles')
<style>
    .materia-editar{display:flex;position:fixed;inset:0;height:100dvh;overflow:hidden;background:#f8fafc}
    .materia-editar main{flex:1;min-width:0;min-height:0;overflow:auto;padding:24px}
    .materia-editar .me-card{max-width:640px;background:white;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-top:20px}
    .materia-editar label{display:block;font-size:13px;font-weight:600;margin-bottom:8px;color:#334155}
    .materia-editar input,.materia-editar textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:9px;padding:12px;background:#f8fafc;color:#0f172a;font:inherit}
    .materia-editar :is(input,textarea,a,button):focus-visible{outline:3px solid #60a5fa;outline-offset:3px}
    .materia-editar .me-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
    .materia-editar .me-actions a,.materia-editar button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border:1px solid #cbd5e1;border-radius:9px;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
    .materia-editar button{background:#111827;color:white}
    .materia-editar button:hover{background:#334155}
    .materia-editar .me-error{font-size:13px;color:#b91c1c;margin-top:6px}
    @media(max-width:1023px){.materia-editar main{padding:16px}.materia-editar .me-card{padding:18px}}
</style>
@endpush
<div class="materia-editar">
    @include('components.admin-sidebar', ['active' => 'materias'])
    <main>
        <x-boton-regreso :href="route('admin.materias')">Volver a Materias</x-boton-regreso>
        <section class="me-card" aria-labelledby="editar-materia-title">
            <h1 id="editar-materia-title" style="font-size:22px;font-weight:700;margin:0 0 8px">Editar materia</h1>
            <p style="color:#64748b;overflow-wrap:anywhere">{{ $materia->codigo }} · {{ $materia->activa ? 'Activa' : 'Inactiva' }} · {{ $materia->es_opcional ? 'Opcional' : 'Obligatoria' }}</p>
            <form method="POST" action="{{ route('admin.materias.update', $materia) }}">
                @csrf
                @method('PUT')
                <div style="margin-top:24px">
                    <label for="editar-nombre">Nombre de la materia</label>
                    <input id="editar-nombre" name="nombre" value="{{ old('nombre', $materia->nombre) }}" required maxlength="100" @error('nombre') aria-invalid="true" aria-describedby="editar-nombre-error" @enderror>
                    @error('nombre')<p id="editar-nombre-error" class="me-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <div style="margin-top:20px">
                    <label for="editar-descripcion">Descripción (opcional)</label>
                    <textarea id="editar-descripcion" name="descripcion" rows="5" maxlength="5000" @error('descripcion') aria-invalid="true" aria-describedby="editar-descripcion-error" @enderror>{{ old('descripcion', $materia->descripcion) }}</textarea>
                    @error('descripcion')<p id="editar-descripcion-error" class="me-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <p style="color:#64748b;font-size:13px;margin-top:16px">El código, la condición opcional y las relaciones académicas se conservan. El estado se administra desde el listado.</p>
                <div class="me-actions">
                    <a href="{{ route('admin.materias') }}" style="background:#f1f5f9;color:#334155">Cancelar</a>
                    <button type="submit">Guardar cambios</button>
                </div>
            </form>
        </section>
    </main>
</div>
<x-logout-modal />
</x-app-layout>
