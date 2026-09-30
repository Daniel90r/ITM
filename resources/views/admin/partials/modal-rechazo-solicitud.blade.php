{{-- Aqui es el rechazo de la solisitud de la inscripcion  --}}
<div id="modalRechazo" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#ffffff; border-radius:16px; padding:28px; width:100%; max-width:440px; box-shadow:0 8px 32px rgba(0,0,0,0.12); margin:20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:4px; height:16px; background:#dc2626; border-radius:2px;"></div>
                <p style="font-size:15px; font-weight:700; color:#111827; margin:0;">Rechazar solicitud</p>
            </div>
            <button onclick="cerrarRechazo()"
                style="background:#f3f4f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer; color:#6b7280;"
                onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">✕</button>
        </div>
        <p id="textoRechazo" style="font-size:13px; color:#6b7280; margin:0 0 16px;"></p>
        <form id="formRechazo" method="POST" autocomplete="off">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:6px;">
                    Motivo del rechazo <span style="color:#dc2626;">*</span>
                </label>
                <textarea name="motivo_rechazo" rows="3" required placeholder="Ej: Documentación incompleta, datos incorrectos..."
                    style="width:100%; padding:10px 14px; font-size:13px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; color:#111827; outline:none; box-sizing:border-box; resize:none; font-family:inherit;"
                    onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="cerrarRechazo()"
                    style="flex:1; padding:10px; background:#f3f4f6; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#374151; cursor:pointer;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancelar
                </button>
                <button type="submit"
                    style="flex:1; padding:10px; background:#dc2626; border:none; border-radius:10px; font-size:13px; font-weight:600; color:#fff; cursor:pointer;"
                    onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                    Confirmar rechazo
                </button>
            </div>
        </form>
    </div>
</div>
