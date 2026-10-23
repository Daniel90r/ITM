<td>{{ $fila['curso'] }}</td>
<td><span class="hm-state" data-estado="{{ $fila['estado'] }}">{{ $fila['estado'] }}</span></td>
<td>${{ number_format($fila['centavos'] / 100, 2) }}</td>
<td>{{ $fila['vencimiento'] ?? '—' }}</td>
<td>{{ $fila['fechas_pago'] ?: '—' }}
    <span class="hm-note">Obligación #{{ $fila['registro']->id }}</span>
    @foreach($fila['advertencias'] as $aviso)<p class="hm-warning">{{ $aviso }}</p>@endforeach
</td>
