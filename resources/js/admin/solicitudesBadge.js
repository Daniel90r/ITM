const INTERVALO_MS = 30000;

function pintar(badge, count) {
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }

    badge.dataset.count = count;
    badge.title = `${count} solicitud(es) pendiente(s)`;
    badge.setAttribute('aria-label', `${count} solicitud(es) pendiente(s)`);
}

async function actualizar(badge) {
    try {
        const respuesta = await fetch(badge.dataset.url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!respuesta.ok) {
            return;
        }

        const data = await respuesta.json();
        const count = Number(data.count);

        if (Number.isFinite(count)) {
            pintar(badge, count);
        }
    } catch (e) {
        // Fallo silencioso: se conserva el último valor conocido.
    }
}

function iniciarBadgeSolicitudes() {
    const badge = document.querySelector('[data-solicitudes-badge]');

    if (!badge) {
        return;
    }

    pintar(badge, Number(badge.dataset.count) || 0);

    if (window.__solicitudesBadgeInterval) {
        clearInterval(window.__solicitudesBadgeInterval);
    }

    window.__solicitudesBadgeInterval = setInterval(() => actualizar(badge), INTERVALO_MS);
}

document.addEventListener('DOMContentLoaded', iniciarBadgeSolicitudes);
