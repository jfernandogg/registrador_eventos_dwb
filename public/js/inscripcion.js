(function () {
    const form = document.getElementById('inscripcion');
    if (!form) {
        return;
    }

    const avisos = document.getElementById('avisos');
    const avisosLista = document.getElementById('avisos-lista');
    const totalCaja = document.getElementById('total');
    const totalMonto = document.getElementById('total-monto');
    const totalLineas = document.getElementById('total-lineas');
    const totalNotas = document.getElementById('total-notas');
    let pedido = 0;

    const campo = (nombre) => form.querySelector(`[name="${nombre}"]`);
    const valor = (nombre) => campo(nombre)?.value.trim() ?? '';

    function cursosMarcados() {
        return [...form.querySelectorAll('input[name="cursos[]"]:checked')].map((input) => input.value);
    }

    function mostrar(elemento, visible) {
        elemento.classList.toggle('si-oculto', !visible);
        elemento.querySelectorAll('input, select, textarea').forEach((control) => {
            if (control.closest('[data-si], [data-curso]') !== elemento && control !== elemento) {
                return;
            }
            control.disabled = !visible;
        });
    }

    function aplicar() {
        form.querySelectorAll('[data-curso]').forEach((bloque) => {
            const activo = cursosMarcados().includes(bloque.dataset.curso);
            bloque.classList.toggle('oculto', !activo);
            bloque.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !activo;
            });
        });

        form.querySelectorAll('[data-si]').forEach((bloque) => {
            if (bloque.closest('[data-curso]')?.classList.contains('oculto')) {
                bloque.classList.add('si-oculto');
                return;
            }
            const [nombre, esperado] = bloque.dataset.si.split('=');
            const actual = valor(nombre);
            const visible = esperado.split('|').includes(actual);
            bloque.classList.toggle('si-oculto', !visible);
            bloque.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !visible;
            });
        });

        const pago = campo('forma_pago')?.selectedOptions[0];
        const pideArchivo = pago?.dataset.pide === '1';
        document.getElementById('bloque-comprobante')?.classList.toggle('si-oculto', !pideArchivo);

        const hayComidas = [...form.querySelectorAll('input[name="comidas[]"]:checked')].length > 0;
        form.querySelectorAll('select[name="dieta"]').forEach((select) => {
            const bloque = select.closest('[data-curso]');
            const cursoActivo = bloque && !bloque.classList.contains('oculto');
            const mostrarDieta = cursoActivo && hayComidas;
            select.disabled = !mostrarDieta;
            select.closest('label')?.classList.toggle('si-oculto', !mostrarDieta);
        });

        // Toggle sesiones: mostrar checkboxes solo si modo "sesiones"
        form.querySelectorAll('.modo-curso:checked').forEach((radio) => {
            const codigo = radio.dataset.curso;
            const bloque = document.querySelector(`[data-curso-sesiones="${codigo}"]`);
            if (bloque) {
                const visible = radio.value === 'sesiones';
                bloque.classList.toggle('si-oculto', !visible);
                bloque.querySelectorAll('input, select').forEach((c) => { c.disabled = !visible; });
            }
        });

        // Aviso de sesiones + comidas: coincidencia de fechas
        const aviso = document.getElementById('aviso-sesiones-comidas');
        if (aviso) {
            const haySesiones = [...form.querySelectorAll('.modo-curso:checked')].some((r) => r.value === 'sesiones');
            const hayComidas = [...form.querySelectorAll('input[name="comidas[]"]:checked')].length > 0;
            aviso.classList.toggle('si-oculto', !(haySesiones && hayComidas));
        }
    }

    function mostrarSpinner() {
        totalMonto.textContent = '';
        const spinner = document.createElement('div');
        spinner.className = 'total-spinner';
        for (let i = 0; i < 3; i++) {
            const punto = document.createElement('span');
            spinner.append(punto);
        }
        totalMonto.append(spinner);
    }

    function pintarTotal(datos) {
        totalMonto.textContent = datos.total_formato === null ? '—' : `$ ${datos.total_formato}`;
        totalLineas.replaceChildren(...(datos.lineas || []).map((linea) => {
            const item = document.createElement('li');
            item.textContent = `${linea.descripcion}: $ ${linea.subtotal}`;
            return item;
        }));
        totalNotas.replaceChildren(...(datos.notas || []).map((nota) => {
            const parrafo = document.createElement('p');
            parrafo.textContent = nota;
            return parrafo;
        }));
    }

    async function pedirTotal() {
        const actual = ++pedido;
        if (actual === 1) {
            mostrarSpinner();
        }
        const datos = new FormData(form);
        datos.set('accion', 'actualizar');
        datos.delete('comprobante');
        try {
            const respuesta = await fetch(form.dataset.cotizar, {
                method: 'POST',
                body: datos,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!respuesta.ok || actual !== pedido) {
                return;
            }
            pintarTotal(await respuesta.json());
        } catch (error) {
            if (actual === pedido) {
                totalMonto.textContent = '—';
                totalNotas.textContent = 'No se pudo actualizar el total. Revisa la conexión.';
            }
        }
    }

    function validar() {
        const faltan = [];
        const exigir = (condicion, mensaje) => {
            if (!condicion) {
                faltan.push(mensaje);
            }
        };
        form.querySelectorAll('[data-requerido="1"]').forEach((control) => {
            if (control.disabled || control.closest('.oculto') || control.closest('.si-oculto')) {
                return;
            }
            const mensaje = control.dataset.mensaje || 'Completa este campo.';
            if (control.type === 'checkbox') {
                exigir(control.checked, mensaje);
            } else if (control.type === 'email') {
                exigir(control.value.includes('@'), mensaje);
            } else {
                exigir(control.value.trim() !== '', mensaje);
            }
        });
        exigir(cursosMarcados().length > 0, 'Elige al menos un curso.');

        cursosMarcados().forEach((curso) => {
            const bloque = form.querySelector(`[data-curso="${curso}"]`);
            const nombre = bloque?.querySelector('legend')?.textContent ?? curso;
            exigir(valor(`transporte_ida[${curso}]`) !== '', `Elige la llegada de ${nombre}.`);
            exigir(valor(`transporte_regreso[${curso}]`) !== '', `Elige la salida de ${nombre}.`);
            if (valor(`transporte_ida[${curso}]`) === 'curso') {
                exigir(valor('franja_ida') !== '', 'Elige la franja de llegada.');
            }
            if (valor(`transporte_regreso[${curso}]`) === 'curso') {
                exigir(valor('franja_regreso') !== '', 'Elige la franja de salida.');
            }
            if (bloque?.querySelector('input[name="comidas[]"]:checked')) {
                exigir(valor('dieta') !== '', 'Elige vegetariano o carne.');
            }
            const alojamiento = bloque?.querySelector('[data-alojamiento]');
            if (alojamiento) {
                exigir(valor(`alojamiento_${curso}`) !== '', `Elige el alojamiento de ${nombre}.`);
                exigir(valor(`alojamiento_${curso}`) !== 'cama' || valor(`condicion_cama_${curso}`) !== '', 'Describe la condición de la cama.');
                if (alojamiento.querySelector(`[name="pernocta_extra_${curso}"]`)) {
                    exigir(valor(`pernocta_extra_${curso}`) !== '', 'Indica si pernoctas fuera de las fechas.');
                    exigir(valor(`pernocta_extra_${curso}`) !== 'si' || valor(`apoyo_montaje_${curso}`) !== '', 'Indica si apoyas montaje o desmontaje.');
                }
            }
        });

        const pago = campo('forma_pago');
        exigir(valor('forma_pago') !== '', 'Elige la forma de pago.');
        if (pago?.selectedOptions[0]?.dataset.pide === '1') {
            const archivo = campo('comprobante')?.files?.length > 0;
            const guardado = valor('comprobante_guardado') !== '';
            exigir(archivo || guardado, 'Sube el comprobante para terminar.');
        }
        return faltan;
    }

    form.addEventListener('change', () => {
        aplicar();
        pedirTotal();
    });
    form.addEventListener('input', (evento) => {
        if (evento.target.name === 'comprobante') {
            return;
        }
        aplicar();
        pedirTotal();
    });

    form.querySelector('[value="actualizar"]')?.addEventListener('click', (evento) => {
        evento.preventDefault();
        aplicar();
        pedirTotal();
        totalCaja.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    form.querySelector('[value="confirmar"]')?.addEventListener('click', (evento) => {
        aplicar();
        const faltan = validar();
        avisos.classList.add('si-oculto');
        if (avisosLista) {
            avisosLista.replaceChildren();
        }
        if (faltan.length > 0) {
            evento.preventDefault();
            faltan.forEach((mensaje) => {
                const item = document.createElement('li');
                item.textContent = mensaje;
                avisosLista?.append(item);
            });
            avisos.classList.remove('si-oculto');
            avisos.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        // Sin diálogo nativo: al pasar la validación el envío procede. El total
        // y el destino se muestran en la propia pantalla; el servidor confirma
        // (recibo) o devuelve el formulario con los campos en rojo.
    });

    const archivoInput = document.getElementById('input-comprobante');
    const nombreArchivo = document.getElementById('nombre-archivo');
    const nombreArchivoTexto = document.getElementById('nombre-archivo-texto');
    const quitarArchivo = document.getElementById('quitar-archivo');

    archivoInput?.addEventListener('change', () => {
        const archivo = archivoInput.files[0];
        if (!archivo) {
            nombreArchivo?.classList.add('si-oculto');
            return;
        }
        if (nombreArchivoTexto) {
            nombreArchivoTexto.textContent = archivo.name;
        }
        nombreArchivo?.classList.remove('si-oculto');
    });

    quitarArchivo?.addEventListener('click', () => {
        archivoInput.value = '';
        nombreArchivo?.classList.add('si-oculto');
    });

    aplicar();
    if (cursosMarcados().length > 0 && valor('edad') !== '') {
        pedirTotal();
    }
})();