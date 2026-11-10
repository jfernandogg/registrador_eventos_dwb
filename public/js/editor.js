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
})();
