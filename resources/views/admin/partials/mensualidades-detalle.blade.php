<p class="mp-detail-note">Ciclo {{ request('anio', date('Y')) }} · {{ $mensualidades->total() }} mensualidades · Mostrando {{ $mensualidades->firstItem() ?? 0 }}–{{ $mensualidades->lastItem() ?? 0 }}</p>
<ol class="mp-month-list">
    @forelse($mensualidades as $m)
        <li class="mp-month" data-mensualidad-id="{{ $m->id }}">
            <div>
                @if($m->estado === 'Pendiente')
                    <input type="checkbox" class="chk-mensualidad" form="formLote" name="ids[]" value="{{ $m->id }}" data-monto="{{ $m->monto }}" data-curso="{{ $curso->id }}" aria-label="Seleccionar {{ $m->nombre_mes }} de {{ $alumno->nombre_completo }}">
                @endif
            </div>
            <div><h4 class="mp-month-name">{{ $m->nombre_mes }}</h4><p class="mp-muted">Vence: {{ $m->fecha_vencimiento ? \Carbon\Carbon::parse($m->fecha_vencimiento)->format('d/m/Y') : '—' }}</p></div>
            <div class="mp-month-amount">${{ number_format($m->monto, 2) }}<span class="mp-badge {{ $m->estado === 'Pagado' ? 'pagado' : 'pendiente' }}">{{ $m->estado }}</span></div>
            @if($m->estado === 'Pendiente')
                <form method="POST" action="{{ route('admin.mensualidades.pagar', $m->id) }}" data-confirm="¿Registrar pago de ${{ number_format($m->monto, 2) }} para {{ $alumno->nombre_completo }}?" data-confirm-titulo="Registrar pago" data-confirm-boton="Sí, cobrar" data-confirm-tipo="exito">
                    @csrf
                    <button type="submit" class="mp-btn mp-btn-cobrar">✓ Cobrar</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.mensualidades.revertir', $m->id) }}" data-confirm="¿Revertir el pago de {{ $alumno->nombre_completo }} ({{ $m->mes }})? Volverá a estado Pendiente." data-confirm-titulo="Revertir pago" data-confirm-boton="Sí, revertir" data-confirm-tipo="peligro">
                    @csrf
                    <button type="submit" class="mp-btn mp-btn-revertir">Revertir</button>
                </form>
            @endif
        </li>
    @empty<li class="mp-empty">No hay mensualidades para esta página.</li>@endforelse
</ol>
@if($mensualidades->hasPages())<div class="mp-pagination" data-mensualidad-paginacion>{{ $mensualidades->links() }}</div>@endif
