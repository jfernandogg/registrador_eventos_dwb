(function () {
    const form = document.getElementById('inscripcion');
    if (!form) {
        return;
    }

    const avisos = document.getElementById('avisos');
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
        elemento.classList.toggle('oculto', !visible);
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
                bloque.classList.add('oculto');
                return;
            }
            const [nombre, esperado] = bloque.dataset.si.split('=');
            const actual = valor(nombre);
            const visible = esperado.split('|').includes(actual);
            bloque.classList.toggle('oculto', !visible);
            bloque.querySelectorAll('input, select, textarea').forEach((control) => {
                control.disabled = !visible;
            });
        });

        const pago = campo('forma_pago')?.selectedOptions[0];
        const pideArchivo = pago?.dataset.pide === '1';
        document.getElementById('bloque-comprobante')?.classList.toggle('oculto', !pideArchivo);

        let dietaVisible = false;
        form.querySelectorAll('select[name="dieta"]').forEach((select) => {
            const bloque = select.closest('[data-curso]');
            const usable = bloque && !bloque.classList.contains('oculto') && !dietaVisible;
            if (usable) {
                dietaVisible = true;
            }
            select.disabled = !usable;
            select.closest('label')?.classList.toggle('oculto', !usable);
        });
    }

    function pintarTotal(datos) {
        totalCaja.classList.remove('oculto');
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
            if (control.disabled || control.closest('.oculto')) {
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
        avisos.replaceChildren();
        if (faltan.length === 0) {
            return;
        }
        evento.preventDefault();
        faltan.forEach((mensaje) => {
            const item = document.createElement('li');
            item.textContent = mensaje;
            avisos.append(item);
        });
        avisos.classList.remove('oculto');
        avisos.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    aplicar();
    if (cursosMarcados().length > 0 && valor('edad') !== '') {
        pedirTotal();
    }
})();
