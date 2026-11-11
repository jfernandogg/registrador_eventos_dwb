(function () {
    document.querySelectorAll('[data-agregar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const destino = document.getElementById(boton.dataset.agregar);
            const plantilla = document.getElementById('tpl-' + boton.dataset.agregar);
            if (!destino || !plantilla) {
                return;
            }
            const fila = plantilla.content.firstElementChild.cloneNode(true);
            destino.appendChild(fila);
            fila.querySelector('input, select, textarea')?.focus();
            marcarSucio();
        });
    });

    document.addEventListener('click', (evento) => {
        const quitar = evento.target.closest('[data-quitar]');
        if (!quitar) {
            return;
        }
        quitar.closest('tr')?.remove();
        marcarSucio();
    });

    const form = document.getElementById('editor');
    const aviso = document.getElementById('sin-guardar');
    let sucio = false;

    function marcarSucio() {
        sucio = true;
        aviso?.removeAttribute('hidden');
    }

    form?.addEventListener('input', marcarSucio);
    form?.addEventListener('change', marcarSucio);
    form?.addEventListener('submit', () => {
        sucio = false;
    });
    window.addEventListener('beforeunload', (evento) => {
        if (sucio) {
            evento.preventDefault();
        }
    });

    // Scroll-spy en el índice lateral
    const indice = document.querySelector('.indice');
    if (indice) {
        const enlaces = [...indice.querySelectorAll('a[href^=\"#\"]')];
        const secciones = enlaces.map((a) => document.getElementById(a.getAttribute('href')?.slice(1))).filter(Boolean);
        const alturaBarra = document.querySelector('.barra')?.offsetHeight || 50;

        function actualizarActivo() {
            let activo = null;
            for (let i = secciones.length - 1; i >= 0; i--) {
                const seccion = secciones[i];
                if (seccion && seccion.getBoundingClientRect().top <= alturaBarra + 60) {
                    activo = i;
                    break;
                }
            }
            enlaces.forEach((enlace, j) => enlace.classList.toggle('activo-indice', j === activo));
        }

        window.addEventListener('scroll', actualizarActivo, { passive: true });
        actualizarActivo();
    }
})();
