(function () {
    document.querySelectorAll('[data-agregar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const tabla = boton.previousElementSibling;
            const fila = tabla?.querySelector('tr:last-child');
            if (!fila) {
                return;
            }
            const copia = fila.cloneNode(true);
            copia.querySelectorAll('input, textarea').forEach((campo) => {
                campo.value = '';
            });
            copia.querySelectorAll('select').forEach((campo) => {
                campo.selectedIndex = 0;
            });
            tabla.appendChild(copia);
        });
    });
})();
