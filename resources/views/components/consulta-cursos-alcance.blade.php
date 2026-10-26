@props(['filtros' => []])
<div data-consulta-cursos-alcance>
<label style="display:flex;align-items:center;gap:8px;font-size:13px;flex-wrap:wrap">
    <input type="hidden" name="incluir_historicos" value="0">
    <input type="checkbox" name="incluir_historicos" value="1" @checked(!empty($filtros['incluir_historicos']))
        style="width:18px;height:18px;min-height:18px;flex-shrink:0"
        onchange="const curso=this.form.querySelector('select[name=curso_id]');if(!this.checked &amp;&amp; curso?.selectedOptions[0]?.dataset.estadoCurso==='FINALIZADO'){this.form.querySelectorAll('[name=curso_id],[name=detalle_curso_id]').forEach(el=>el.value='');}this.form.requestSubmit()">
    Incluir cursos históricos
</label>
<p style="font-size:12px;color:#64748b;margin:4px 0">{{ empty($filtros['incluir_historicos']) ? 'Consulta limitada a cursos activos. Activa esta opción para consultar cursos finalizados.' : 'Consulta de cursos activos y finalizados. Los cursos próximos no se incluyen.' }}</p>
</div>
