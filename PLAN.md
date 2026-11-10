# Plan de Arquitectura y Modelo de Dominio — Registrador de Eventos Budismo Camino del Diamante Colombia

> **Para Hermes:** Este plan define la arquitectura OO y el modelo de dominio **antes** de cualquier implementación. Úsalo como fuente de verdad para la fase 1 (dominio puro con tests). La pila tecnológica queda como decisión abierta en §10.

---

## 1. Resumen del objetivo

Construir un sistema de **registro y gestión de eventos** para la comunidad Budismo Camino del Diamante en Colombia.

Un **evento** es un encuentro presencial (retiro, curso, práctica, trabajo voluntario) con:
- Fechas de inicio/fin, lugar, objetivo
- **Personalización de la experiencia** por participante: transporte (propio/bus orgánico con horarios), alimentación (matriz día×momento o paquete), alojamiento (cama, carpa, casa amigo), colaboración en equipos
- **Costos dinámicos** que dependen de lo elegido, edad, fecha de registro, rol, paquetes
- **Recibo final** con desglose humano y trazabilidad (qué se eligió, por qué cuesta eso)

Dos caras:
1. **Participante** — asistente guiado (wizard) que arma su inscripción, ve cotización en vivo, paga, recibe recibo
2. **Backoffice** — diseñador de eventos (catálogo, grupos, opciones, reglas, capacidades), gestor de inscripciones (listado, ajustes manuales, confirmación transferencias, asignación equipos, manifiestos)

---

## 2. Decisión arquitectónica central: **Composición + Estrategias + Especificaciones + Snapshots**

> **No usar herencia** para lo que los organizadores inventan (nuevos tipos de cama, bus, dieta, campo de formulario).
> **Sí usar herencia** solo para comportamientos estables y pocos: reglas de precio (5 estrategias), expansores de unidades (4), especificaciones compuestas, adaptadores de pasarela.

### Patrones elegidos

| Patrón | Dónde vive | Qué resuelve |
|--------|------------|--------------|
| **Aggregate Root** (DDD) | `Evento` (catálogo), `Registro` (runtime) | Consistencia transaccional, versionado, invariantes |
| **Composition over Inheritance** | `GrupoEleccion` + `Opcion` + `FranjaHoraria` + `DefinicionCampo` | Catálogo 100% configurable por datos, sin nuevas clases |
| **Strategy** | `PricingRule` (protocol) + implementaciones parametrizadas | Precio base + ajustes ordenados, auditables, testeables |
| **Specification** | `Especificacion` (protocol) + combinadores `And/Or/Not` | Reglas de elegibilidad, cupos, dependencias, edad, campos obligatorios |
| **Snapshot / Value Object inmutable** | `Cotizacion`, `Recibo` | Recibos que **no cambian** aunque renombres opciones mañana |
| **Pipeline de expansión** | `ExpansorUnidades` por tipo de oferta | Convierte selecciones → unidades facturables (noches, comidas, trayectos, sesiones) |
| **Ports & Adapters (Hexagonal)** | `PagoPort`, `RepositorioPort`, `RelojPort`, `PdfPort`, `NotificadorPort` | Dominio puro, sin HTTP/SQL/Wompi; tests rápidos |

### Anti-patrones **rechazados explícitamente**

- ❌ Una clase por tipo de alojamiento/transporte (`Cama`, `Carpa`, `BusBogota`, `BusMedellin`…)
- ❌ `Evento` con 30 columnas opcionales (`tiene_cama`, `tiene_carpas`, `bus_origen`, `es_vegetariano`…)
- ❌ Cálculo de precios en controlador / solo en frontend
- ❌ Recibos que guardan solo FKs (al renombrar "Carpa" → "Tienda de campaña" el recibo histórico cambia)
- ❌ EAV para todo el dominio (pierdes integridad, consultas, tipos)
- ❌ JSON blob sin invariantes de agregado
- ❌ Microservicios (complejidad operativa innecesaria)
- ❌ Motor de reglas propio (YAGNI; `Specification` + `Strategy` parametrizadas bastan)
- ❌ `float` para dinero (solo `Decimal`)

---

## 3. Modelo de dominio — Catálogo (configuración del evento)

### 3.1 Value Objects base

```python
# domain/value_objects.py
from dataclasses import dataclass
from decimal import Decimal
from datetime import date, time
from enum import Enum

@dataclass(frozen=True, slots=True)
class Dinero:
    """Moneda fija COP. Operaciones seguras."""
    cantidad: Decimal
    moneda: str = "COP"

    def __post_init__(self):
        if not isinstance(self.cantidad, Decimal):
            object.__setattr__(self, 'cantidad', Decimal(str(self.cantidad)))
        if self.cantidad < 0:
            raise ValueError("Dinero no puede ser negativo")

    def __add__(self, other: "Dinero") -> "Dinero":
        self._misma_moneda(other)
        return Dinero(self.cantidad + other.cantidad)

    def __sub__(self, other: "Dinero") -> "Dinero":
        self._misma_moneda(other)
        return Dinero(self.cantidad - other.cantidad)

    def __mul__(self, factor: Decimal | int) -> "Dinero":
        return Dinero(self.cantidad * Decimal(str(factor)))

    def porcentaje(self, pct: Decimal) -> "Dinero":
        return Dinero(self.cantidad * (pct / Decimal("100")))

    def redondear(self) -> "Dinero":
        return Dinero(self.cantidad.quantize(Decimal("1")))

    def _misma_moneda(self, other: "Dinero"):
        if self.moneda != other.moneda:
            raise ValueError(f"Monedas distintas: {self.moneda} vs {other.moneda}")

    def __str__(self) -> str:
        return f"{self.cantidad:,.0f} {self.moneda}".replace(",", ".")
```

```python
# domain/tipos.py
class TipoOferta(str, Enum):
    TRANSPORTE_IDA = "transporte_ida"
    TRANSPORTE_REGRESO = "transporte_regreso"
    ALOJAMIENTO = "alojamiento"
    ALIMENTACION = "alimentacion"
    SESIONES = "sesiones"
    COLABORACION = "colaboracion"

class TipoCobroBase(str, Enum):
    FIJO = "fijo"
    POR_NOCHE = "por_noche"
    POR_DIA = "por_dia"
    POR_COMIDA = "por_comida"
    POR_SESION = "por_sesion"
    POR_TRAYECTO = "por_trayecto"
    INCLUIDO = "incluido"  # precio 0, pero genera línea en recibo

class TipoCampo(str, Enum):
    TEXTO = "texto"
    TEXTO_LARGO = "texto_largo"
    ENTERO = "entero"
    DECIMAL = "decimal"
    FECHA = "fecha"
    HORA = "hora"
    BOOLEANO = "booleano"
    SELECCION = "seleccion"
    SELECCION_MULTIPLE = "seleccion_multiple"

class EstadoRegistro(str, Enum):
    BORRADOR = "borrador"
    COTIZADO = "cotizado"
    PENDIENTE_PAGO = "pendiente_pago"
    PAGADO = "pagado"
    CONFIRMADO = "confirmado"
    LISTA_ESPERA = "lista_espera"
    CANCELADO = "cancelado"
    BECA = "beca"

class ModoAsistencia(str, Enum):
    EVENTO_COMPLETO = "evento_completo"
    SESIONES_ELEGIDAS = "sesiones_elegidas"
```

---

### 3.2 Entidades del catálogo (Aggregate Root: `Evento`)

```python
# domain/catalogo.py
from dataclasses import dataclass, field
from datetime import date, time, datetime
from typing import Optional
from decimal import Decimal

@dataclass(slots=True)
class DefinicionCampo:
    """Campo dinámico que el participante debe/puede rellenar."""
    clave: str                    # p.ej. "placa_vehiculo", "dieta_especial"
    etiqueta: str                 # "Placa del vehículo", "Dieta especial"
    tipo: TipoCampo
    requerido: bool = False
    opciones: list[str] = field(default_factory=list)  # para SELECCION
    visible_si: str = ""          # expresión simple: "opcion_clave == 'bus'" o "edad < 18"
    orden: int = 0

@dataclass(slots=True)
class FranjaHoraria:
    """Unidad concreta de capacidad: una salida de bus, una cama, una carpa."""
    id: str
    etiqueta: str                 # "Bus Bogotá → Centro, 14 mar 06:00"
    # Para transporte:
    origen: Optional[str] = None
    destino: Optional[str] = None
    fecha_salida: Optional[date] = None
    hora_salida: Optional[time] = None
    fecha_llegada: Optional[date] = None
    hora_llegada: Optional[time] = None
    # Capacidad
    cupo_total: int = 0
    cupo_reservado: int = 0
    # Precio opcional que reemplaza al base de la opción
    precio_override: Optional[Dinero] = None

    @property
    def cupo_disponible(self) -> int:
        return max(0, self.cupo_total - self.cupo_reservado)

    def reservar(self, cantidad: int = 1) -> bool:
        if self.cupo_disponible >= cantidad:
            self.cupo_reservado += cantidad
            return True
        return False

    def liberar(self, cantidad: int = 1):
        self.cupo_reservado = max(0, self.cupo_reservado - cantidad)

@dataclass(slots=True)
class Opcion:
    """Una alternativa dentro de un GrupoEleccion."""
    id: str
    etiqueta: str                 # "Bus organización", "Carpa propia", "Pensión completa"
    tipo_cobro_base: TipoCobroBase
    precio_base: Dinero = field(default_factory=lambda: Dinero(Decimal("0")))
    cupo_total: Optional[int] = None      # None = ilimitado (se controla por franjas)
    campos: list[DefinicionCampo] = field(default_factory=list)
    franjas: list[FranjaHoraria] = field(default_factory=list)
    # Metadatos para UI/backoffice
    descripcion: str = ""
    orden: int = 0
    activa: bool = True

    def tiene_franjas(self) -> bool:
        return len(self.franjas) > 0

    def franjas_disponibles(self) -> list[FranjaHoraria]:
        return [f for f in self.franjas if f.cupo_disponible > 0]
```

```python
# domain/catalogo.py (continuación)
class TipoSeleccion(str, Enum):
    EXACTAMENTE_UNA = "exactamente_una"
    CERO_O_UNA = "cero_o_una"
    CERO_A_N = "cero_a_n"
    UNA_A_N = "una_a_n"

@dataclass(slots=True)
class Dependencia:
    """Regla: si se cumple condición → esta opción/grupo se requiere/oculta/habilita."""
    # Primitivas (combinables con And/Or/Not en Specification)
    tipo: str  # "opcion_seleccionada" | "edad_comparacion" | "campo_rellenado"
    # Para opcion_seleccionada:
    grupo_id: Optional[str] = None
    opcion_id: Optional[str] = None
    # Para edad_comparacion:
    operador: Optional[str] = None  # "<", "<=", ">", ">=", "=="
    valor_edad: Optional[int] = None
    # Para campo_rellenado:
    campo_clave: Optional[str] = None
    # Acción
    accion: str  # "requerir" | "ocultar" | "habilitar" | "deshabilitar"

@dataclass(slots=True)
class GrupoEleccion:
    """Un paso del wizard / una decisión del participante."""
    id: str
    etiqueta: str                 # "Transporte de ida", "Alojamiento", "Alimentación"
    tipo_oferta: TipoOferta
    tipo_seleccion: TipoSeleccion
    min_selecciones: int = 0
    max_selecciones: Optional[int] = None
    opciones: list[Opcion] = field(default_factory=list)
    dependencias: list[Dependencia] = field(default_factory=list)
    # Para alimentación modo matriz
    es_matriz: bool = False
    dias_matriz: list[date] = field(default_factory=list)
    momentos_matriz: list[str] = field(default_factory=list)  # "desayuno", "almuerzo", "cena"
    obligatorio_si_pernocta: bool = False
    orden: int = 0

    def opciones_activas(self) -> list[Opcion]:
        return [o for o in self.opciones if o.activa]
```

```python
# domain/catalogo.py (continuación)
@dataclass(slots=True)
class Sesion:
    """Bloque de programa: enseñanza, meditación, trabajo."""
    id: str
    titulo: str
    tipo: str  # "ensenanza", "meditacion", "trabajo", "libre"
    inicio: datetime
    fin: datetime
    obligatoria: bool = False
    # Qué cubre esta sesión (para derivar noches/comidas)
    cubre_alojamiento: bool = True
    cubre_alimentacion: bool = True

@dataclass(slots=True)
class PoliticaPrecios:
    """Conjunto ordenado de reglas de precio. Versión inmutable por evento publicado."""
    version: int
    reglas: list["PricingRule"] = field(default_factory=list)
    moneda: str = "COP"
    vigente_desde: datetime = field(default_factory=datetime.now)

    def agregar_regla(self, regla: "PricingRule", orden: Optional[int] = None):
        if orden is not None:
            self.reglas.insert(orden, regla)
        else:
            self.reglas.append(regla)
        # Re-asignar orden implícito
        for i, r in enumerate(self.reglas):
            r.orden = i
```

```python
# domain/catalogo.py (Aggregate Root)
@dataclass(slots=True)
class Evento:
    """Raíz del agregado Catálogo. Inmutable tras publicar (versionado)."""
    id: str
    nombre: str
    descripcion: str
    ciudad: str
    lugar: str
    inicio: datetime
    fin: datetime
    # Ventana de inscripciones
    inscripciones_abren: datetime
    inscripciones_cierran: datetime
    # Catálogo
    grupos: list[GrupoEleccion] = field(default_factory=list)
    sesiones: list[Sesion] = field(default_factory=list)
    politica_precios: Optional[PoliticaPrecios] = None
    # Versionado
    version: int = 1
    publicado: bool = False
    # Modo de asistencia
    modo_asistencia: ModoAsistencia = ModoAsistencia.EVENTO_COMPLETO

    # --- Comportamientos de consistencia ---
    def obtener_grupo(self, grupo_id: str) -> Optional[GrupoEleccion]:
        return next((g for g in self.grupos if g.id == grupo_id), None)

    def obtener_opcion(self, grupo_id: str, opcion_id: str) -> Optional[Opcion]:
        grupo = self.obtener_grupo(grupo_id)
        return next((o for g in grupo.opciones if o.id == opcion_id), None) if grupo else None

    def validar_inscripcion_abierta(self, ahora: datetime) -> bool:
        return self.publicado and self.inscripciones_abren <= ahora <= self.inscripciones_cierran

    def clonar_como_borrador(self) -> "Evento":
        """Crea nueva versión editable. La publicada queda intacta para registros en curso."""
        import copy
        nuevo = copy.deepcopy(self)
        nuevo.version += 1
        nuevo.publicado = False
        nuevo.politica_precios = None  # se reconstruye al publicar
        return nuevo

    def publicar(self, politica: PoliticaPrecios):
        self.politica_precios = politica
        self.publicado = True
        self.version += 1
```

### 3.3 Ficha del participante — esquema por evento

La ficha **no es una clase fija**. Cada evento tiene su propio `EsquemaFicha`: una lista ordenada de campos. Al crear el evento el backoffice arranca de una plantilla base y puede quitar, reordenar, marcar obligatorio o agregar.

Hay **dos sitios** donde vive un campo. No se mezclan:

| Sitio | Cuándo se ve | Ejemplos |
|---|---|---|
| `Evento.esquema_ficha` | Siempre, paso "Tus datos" | nombre, apellidos, teléfono, seguro médico, contacto de emergencia, alergias |
| `Opcion.campos` | Solo si eligió esa opción | placa si viene en carro, dirección si duerme en casa de amigo |

Los dos usan la misma `DefinicionCampo`. Cambian el contenedor y el momento del wizard.

**Biblioteca** (de la organización, no de un evento): campos reutilizables con clave estable (`alergias_alimenticias`, no un texto libre cada vez). Sirve para que los manifiestos y las exportaciones signifiquen lo mismo en todos los cursos. Un campo nuevo puede nacer en la biblioteca o solo en un evento.

**Plantilla base** al crear un evento (se puede quitar lo que no esté bloqueado):

| Clave | Etiqueta | Tipo | En la plantilla | ¿Se puede quitar? |
|---|---|---|---|---|
| `nombre` | Nombre | texto | sí, obligatorio | no: el recibo y las listas lo necesitan |
| `apellidos` | Apellidos | texto | sí, obligatorio | sí, si el evento usa un solo campo de nombre |
| `documento` | Documento | texto | sí, obligatorio | sí, con aviso: sin él no hay control de duplicados |
| `email` | Correo | texto | sí, obligatorio | sí, con aviso: el recibo solo se descarga, no se envía |
| `telefono` | Teléfono | teléfono | sí, obligatorio | sí |
| `fecha_nacimiento` | Fecha de nacimiento | fecha | sí, obligatorio | solo si el evento no tiene regla de edad ni acudiente por minoría de edad |
| `seguro_medico` | Seguro médico | texto | sí, opcional | sí |
| `contacto_emergencia` | Contacto de emergencia | grupo: nombre, teléfono, parentesco | sí, opcional | sí, se quita el grupo entero |
| `alergias_alimenticias` | Alergias alimenticias | texto largo | sí, opcional | sí; si hay grupo de alimentación, el backoffice avisa antes de quitarlo |

Agregar un campo = elegirlo de la biblioteca o crearlo (clave, etiqueta, tipo, ayuda, obligatorio, opciones si es lista). Tipos de la primera versión: texto, texto largo, teléfono, fecha, sí/no, lista, grupo. Un grupo se agrega y se quita junto (el contacto de emergencia no son tres campos sueltos que alguien pueda dejar a medias).

**Candados, no un formulario rígido.** Solo se bloquea un campo si quitarlo rompe otra cosa ya configurada:

- Regla de edad o acudiente de menor → `fecha_nacimiento` no se puede quitar hasta apagar esa regla.
- Envío de recibo por correo → `email` no se puede quitar, o se apaga el envío.
- Al menos un campo de nombre siempre queda.

Alergias **no** son la dieta del carrito. La dieta (estándar, vegetariana) es una opción y puede costar. La alergia es una nota para cocina. Si el campo existe, sale en el manifiesto de alimentación al lado de la dieta; no genera línea de precio.

**Versión y snapshot.** El esquema viaja con la versión del catálogo. Quitar "seguro médico" después de publicar no borra lo que ya contestaron: la respuesta queda en el registro, con la etiqueta copiada. Las inscripciones nuevas ya no ven ese campo. Cambiar la etiqueta no reescribe recibos viejos.

---

## 4. Modelo de dominio — Runtime (inscripción del participante)

### 4.1 Participante y edad

> El bloque de código de abajo es el mínimo estructural (identidad + edad). El resto de la ficha **no** son atributos fijos de `Participante`: vive en `Registro.valores_ficha`, validado contra `Evento.esquema_ficha`. `fecha_nacimiento` solo es obligatoria en el objeto si el esquema del evento la incluye.

```python
# domain/registro.py
from dataclasses import dataclass, field
from datetime import date, datetime
from typing import Optional
from decimal import Decimal

@dataclass(slots=True)
class Acudiente:
    """Para menores de edad."""
    nombre: str
    documento: str
    telefono: str
    email: str
    parentesco: str

@dataclass(slots=True)
class Participante:
    id: str
    nombre_completo: str
    documento: str
    fecha_nacimiento: date
    email: str
    telefono: str
    acudiente: Optional[Acudiente] = None

    def edad_en(self, fecha_referencia: date) -> int:
        """Edad cumplida en la fecha de inicio del evento."""
        años = fecha_referencia.year - self.fecha_nacimiento.year
        if (fecha_referencia.month, fecha_referencia.day) < \
           (self.fecha_nacimiento.month, self.fecha_nacimiento.day):
            años -= 1
        return años

    def es_menor_de_edad(self, fecha_referencia: date) -> bool:
        return self.edad_en(fecha_referencia) < 18
```

### 4.2 Selecciones y unidades cubiertas

```python
# domain/registro.py (continuación)
@dataclass(slots=True)
class UnidadCubierta:
    """Una unidad facturable expandida desde una selección."""
    # Clave de dominio: "noche", "comida", "dia", "sesion", "trayecto"
    tipo_unidad: str
    cantidad: int
    # Contexto para recibo humano
    descripcion: str              # "Noche en carpa", "Almuerzo vegetariano 15 mar"
    fecha: Optional[date] = None
    momento: Optional[str] = None # "desayuno", "almuerzo", "cena", "ida", "regreso"
    opcion_id: Optional[str] = None
    franja_id: Optional[str] = None

@dataclass(slots=True)
class Seleccion:
    """Una elección del participante dentro de un GrupoEleccion."""
    grupo_id: str
    opcion_id: str
    franja_id: Optional[str] = None          # si la opción tiene franjas (bus, cama)
    unidades_cubiertas: list[UnidadCubierta] = field(default_factory=list)
    valores_campos: dict[str, str] = field(default_factory=dict)  # clave → valor serializado
    # Etiquetas congeladas al cotizar (para recibo inmutable)
    etiqueta_grupo_congelada: str = ""
    etiqueta_opcion_congelada: str = ""
    etiqueta_franja_congelada: str = ""
```

### 4.3 Cotización y Recibo (Value Objects inmutables — **Snapshots**)

```python
# domain/registro.py (continuación)
@dataclass(frozen=True, slots=True)
class LineaCotizacion:
    """Una línea del desglose. Inmutable."""
    descripcion: str              # "Bus Bogotá → Centro, 14 mar 06:00"
    base: Dinero                  # precio unitario base
    cantidad: int
    subtotal: Dinero              # base * cantidad
    opcion_id: str
    opcion_etiqueta: str          # copiada al congelar
    tipo_unidad: str              # "trayecto", "noche", "comida"...
    # Para ajustes posteriores
    es_ajuste: bool = False
    motivo_ajuste: str = ""

@dataclass(frozen=True, slots=True)
class Cotizacion:
    """Snapshot inmutable. Se genera UNA vez al salir de BORRADOR."""
    version_catalogo: int
    version_politica: int
    lineas_base: tuple[LineaCotizacion, ...]
    ajustes: tuple[LineaCotizacion, ...]
    total: Dinero
    calculada_en: datetime
    moneda: str = "COP"

    @property
    def subtotal_base(self) -> Dinero:
        if not self.lineas_base:
            return Dinero(Decimal("0"))
        return sum((l.subtotal for l in self.lineas_base), Dinero(Decimal("0")))

    @property
    def total_ajustes(self) -> Dinero:
        if not self.ajustes:
            return Dinero(Decimal("0"))
        return sum((l.subtotal for l in self.ajustes), Dinero(Decimal("0")))

    def lineas_para_recibo(self) -> list[LineaCotizacion]:
        """Orden: base (agrupadas por tipo) → ajustes (orden de regla)."""
        return list(self.lineas_base) + list(self.ajustes)
```

```python
# domain/registro.py (continuación)
@dataclass(frozen=True, slots=True)
class Recibo:
    """Documento final entregado al participante. Inmutable."""
    numero: str                   # p.ej. "RCD-2026-000123"
    registro_id: str
    cotizacion: Cotizacion
    # Snapshots para que el recibo nunca cambie
    participante_snapshot: dict   # nombre, documento, email, telefono, edad_en_evento
    evento_snapshot: dict         # nombre, ciudad, lugar, inicio, fin
    emitido_en: datetime
    metodo_pago: str              # "Wompi", "Transferencia", "Efectivo", "Beca"
    referencia_pago: str
    observaciones: str = ""

    def total_formateado(self) -> str:
        return str(self.cotizacion.total)
```

### 4.4 Agregado Registro (Aggregate Root runtime)

```python
# domain/registro.py (Aggregate Root)
@dataclass(slots=True)
class Registro:
    """Raíz del agregado de inscripción. Gestiona ciclo de vida y consistencia."""
    id: str
    evento_id: str
    version_catalogo: int         # anclado a la versión del evento al crear borrador
    participante: Participante
    selecciones: list[Seleccion] = field(default_factory=list)
    estado: EstadoRegistro = EstadoRegistro.BORRADOR
    cotizacion: Optional[Cotizacion] = None
    pago_id: Optional[str] = None
    creado_en: datetime = field(default_factory=datetime.now)
    actualizado_en: datetime = field(default_factory=datetime.now)

    # --- Invariantes y comportamiento ---
    def puede_modificar(self) -> bool:
        return self.estado in (EstadoRegistro.BORRADOR, EstadoRegistro.COTIZADO)

    def agregar_seleccion(self, seleccion: Seleccion) -> bool:
        if not self.puede_modificar():
            return False
        # Reemplazar si ya existe selección en mismo grupo (tipo EXACTAMENTE_UNA / CERO_O_UNA)
        existente = next((i for i, s in enumerate(self.selecciones) if s.grupo_id == seleccion.grupo_id), None)
        if existente is not None:
            self.selecciones[existente] = seleccion
        else:
            self.selecciones.append(seleccion)
        self.actualizado_en = datetime.now()
        # Volver a borrador si estaba cotizado
        if self.estado == EstadoRegistro.COTIZADO:
            self.estado = EstadoRegistro.BORRADOR
            self.cotizacion = None
        return True

    def congelar_cotizacion(self, cotizacion: Cotizacion):
        """Transición BORRADOR/COTIZADO → COTIZADO. Inmutable desde aquí."""
        if self.estado not in (EstadoRegistro.BORRADOR, EstadoRegistro.COTIZADO):
            raise ValueError(f"No se puede cotizar desde estado {self.estado}")
        self.cotizacion = cotizacion
        self.estado = EstadoRegistro.COTIZADO
        self.actualizado_en = datetime.now()

    def marcar_pendiente_pago(self, pago_id: str):
        if self.estado != EstadoRegistro.COTIZADO:
            raise ValueError("Solo se puede ir a pago desde COTIZADO")
        self.pago_id = pago_id
        self.estado = EstadoRegistro.PENDIENTE_PAGO
        self.actualizado_en = datetime.now()

    def confirmar_pago(self, referencia: str, metodo: str):
        if self.estado != EstadoRegistro.PENDIENTE_PAGO:
            raise ValueError("Solo se confirma pago desde PENDIENTE_PAGO")
        # La validación de monto exacto la hace el caso de uso Cobrador
        self.estado = EstadoRegistro.PAGADO
        self.actualizado_en = datetime.now()

    def obtener_seleccion(self, grupo_id: str) -> Optional[Seleccion]:
        return next((s for s in self.selecciones if s.grupo_id == grupo_id), None)
```

---

## 5. Reglas de precio — Strategy parametrizada

```python
# domain/precios.py
from dataclasses import dataclass
from abc import ABC, abstractmethod
from typing import Protocol
from decimal import Decimal
from datetime import date, datetime

class ContextoPrecio:
    """Entrada inmutable para evaluar reglas."""
    def __init__(self,
                 participante: Participante,
                 evento: Evento,
                 selecciones: list[Seleccion],
                 edad_en_evento: int,
                 ahora: datetime):
        self.participante = participante
        self.evento = evento
        self.selecciones = selecciones
        self.edad = edad_en_evento
        self.ahora = ahora

class AjustePrecio:
    """Resultado de una regla: una línea de ajuste (positiva o negativa)."""
    def __init__(self,
                 descripcion: str,
                 monto: Dinero,
                 motivo: str,
                 regla_nombre: str,
                 aplica_a_opciones: list[str] = None):
        self.descripcion = descripcion
        self.monto = monto
        self.motivo = motivo
        self.regla_nombre = regla_nombre
        self.aplica_a_opciones = aplica_a_opciones or []

class PricingRule(Protocol):
    """Protocolo: cada regla sabe evaluarse y emitir ajustes."""
    nombre: str
    orden: int

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]: ...

# --- Implementaciones concretas parametrizadas (data-driven) ---

@dataclass(slots=True)
class ReglaBandaEdad:
    """Bandas de edad con descuento/recargo porcentual, fijo o reemplazo."""
    nombre: str = "banda_edad"
    orden: int = 10
    # Lista de bandas: (edad_min, edad_max_inclusive, modo, valor, opciones_afectadas[])
    # modo: "porcentual" | "fijo" | "reemplazo"
    bandas: list[tuple[int, int, str, Decimal, list[str]]] = field(default_factory=list)

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]:
        ajustes = []
        for edad_min, edad_max, modo, valor, ops_afectadas in self.bandas:
            if edad_min <= ctx.edad <= edad_max:
                for sel in ctx.selecciones:
                    if not ops_afectadas or sel.opcion_id in ops_afectadas:
                        base = sel.opcion.precio_base  # requiere acceso a catálogo
                        if modo == "porcentual":
                            monto = -(base * (valor / Decimal("100")) * sel.unidades_total())
                        elif modo == "fijo":
                            monto = -Dinero(valor) * sel.unidades_total()
                        elif modo == "reemplazo":
                            monto = (Dinero(valor) - base) * sel.unidades_total()
                        else:
                            continue
                        ajustes.append(AjustePrecio(
                            descripcion=f"Ajuste edad {edad_min}-{edad_max} ({modo} {valor}%)",
                            monto=monto,
                            motivo=f"Banda de edad {edad_min}-{edad_max} años",
                            regla_nombre=self.nombre,
                            aplica_a_opciones=ops_afectadas
                        ))
        return ajustes

@dataclass(slots=True)
class ReglaVentanaFecha:
    """Descuento por registro temprano (early bird)."""
    nombre: str = "early_bird"
    orden: int = 20
    # (fecha_limite_inclusive, modo, valor, opciones_afectadas[])
    ventanas: list[tuple[date, str, Decimal, list[str]]] = field(default_factory=list)

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]:
        ajustes = []
        for fecha_lim, modo, valor, ops in self.ventanas:
            if ctx.ahora.date() <= fecha_lim:
                for sel in ctx.selecciones:
                    if not ops or sel.opcion_id in ops:
                        base = sel.opcion.precio_base
                        if modo == "porcentual":
                            monto = -(base * (valor / Decimal("100")) * sel.unidades_total())
                        elif modo == "fijo":
                            monto = -Dinero(valor) * sel.unidades_total()
                        else:
                            continue
                        ajustes.append(AjustePrecio(
                            descripcion=f"Early bird hasta {fecha_lim} ({modo} {valor}%)",
                            monto=monto,
                            motivo=f"Registro anticipado antes de {fecha_lim}",
                            regla_nombre=self.nombre
                        ))
        return ajustes

@dataclass(slots=True)
class ReglaRol:
    """Descuento por rol/equipo (colaborador cocina, limpieza, etc.)."""
    nombre: str = "rol_equipo"
    orden: int = 30
    # (rol_id, modo, valor, opciones_afectadas[])
    roles: list[tuple[str, str, Decimal, list[str]]] = field(default_factory=list)

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]:
        # El participante indica preferencia en grupo COLABORACION; backoffice asigna rol real
        ajustes = []
        for rol_id, modo, valor, ops in self.roles:
            if self._tiene_rol(ctx, rol_id):
                for sel in ctx.selecciones:
                    if not ops or sel.opcion_id in ops:
                        base = sel.opcion.precio_base
                        if modo == "porcentual":
                            monto = -(base * (valor / Decimal("100")) * sel.unidades_total())
                        elif modo == "fijo":
                            monto = -Dinero(valor) * sel.unidades_total()
                        else:
                            continue
                        ajustes.append(AjustePrecio(
                            descripcion=f"Descuento rol {rol_id} ({modo} {valor}%)",
                            monto=monto,
                            motivo=f"Colaboración en equipo {rol_id}",
                            regla_nombre=self.nombre
                        ))
        return ajustes

    def _tiene_rol(self, ctx: ContextoPrecio, rol_id: str) -> bool:
        # Buscar en selecciones de grupo COLABORACION o en asignación backoffice
        pass

@dataclass(slots=True)
class ReglaPaquete:
    """Reemplaza líneas base por un precio de paquete (pensión completa, todo incluido)."""
    nombre: str = "paquete"
    orden: int = 40
    # (paquete_opcion_id, precio_paquete, opciones_que_reemplaza[])
    paquetes: list[tuple[str, Dinero, list[str]]] = field(default_factory=list)

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]:
        ajustes = []
        for pkg_opcion_id, precio_pkg, ops_reemplaza in self.paquetes:
            if any(s.opcion_id == pkg_opcion_id for s in ctx.selecciones):
                # Quitar líneas base de las opciones reemplazadas (se hace en pipeline)
                # Aquí solo emitimos el ajuste neto
                total_reemplazado = sum(
                    s.opcion.precio_base * s.unidades_total()
                    for s in ctx.selecciones if s.opcion_id in ops_reemplaza
                )
                monto = precio_pkg - total_reemplazado
                ajustes.append(AjustePrecio(
                    descripcion=f"Paquete {pkg_opcion_id}",
                    monto=monto,
                    motivo=f"Precio de paquete reemplaza opciones individuales",
                    regla_nombre=self.nombre
                ))
        return ajustes

@dataclass(slots=True)
class AjusteManual:
    """Backoffice: ajuste arbitrario con auditoría."""
    nombre: str = "ajuste_manual"
    orden: int = 90
    ajustes: list[tuple[str, Dinero, str, list[str]]] = field(default_factory=list)
    # (descripcion, monto, motivo, opciones_afectadas)

    def evaluar(self, ctx: ContextoPrecio) -> list[AjustePrecio]:
        return [AjustePrecio(d, m, mot, self.nombre, ops) for d, m, mot, ops in self.ajustes]
```

---

## 6. Especificaciones (validaciones de elegibilidad y cupos)

```python
# domain/especificaciones.py
from abc import ABC, abstractmethod
from dataclasses import dataclass
from typing import Protocol

class ResultadoSpec:
    def __init__(self, ok: bool, mensaje: str = "", codigo: str = ""):
        self.ok = ok
        self.mensaje = mensaje
        self.codigo = codigo

    @staticmethod
    def ok() -> "ResultadoSpec":
        return ResultadoSpec(True)

    @staticmethod
    def fallo(mensaje: str, codigo: str = "") -> "ResultadoSpec":
        return ResultadoSpec(False, mensaje, codigo)

class Especificacion(Protocol):
    def evaluar(self, ctx: "ContextoValidacion") -> ResultadoSpec: ...

@dataclass(slots=True)
class ContextoValidacion:
    participante: Participante
    evento: Evento
    selecciones: list[Seleccion]
    edad: int
    ahora: datetime

# --- Primitivas combinables ---

@dataclass(slots=True)
class SpecCupoDisponible:
    """La opción/franja elegida tiene cupo."""
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for sel in ctx.selecciones:
            opcion = ctx.evento.obtener_opcion(sel.grupo_id, sel.opcion_id)
            if not opcion:
                return ResultadoSpec.fallo(f"Opción {sel.opcion_id} no existe", "OPCION_INEXISTENTE")
            if opcion.cupo_total is not None:
                # Contar cuántos registros confirmados ya la usan (requiere repo)
                pass
            if sel.franja_id:
                franja = next((f for f in opcion.franjas if f.id == sel.franja_id), None)
                if not franja or franja.cupo_disponible <= 0:
                    return ResultadoSpec.fallo(f"Sin cupo en {franja.etiqueta if franja else 'franja'}", "SIN_CUPO")
        return ResultadoSpec.ok()

@dataclass(slots=True)
class SpecGrupoRequeridoCompleto:
    """Todos los grupos obligatorios tienen selección válida."""
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for grupo in ctx.evento.grupos:
            if grupo.tipo_seleccion in (TipoSeleccion.EXACTAMENTE_UNA, TipoSeleccion.UNA_A_N):
                sel = next((s for s in ctx.selecciones if s.grupo_id == grupo.id), None)
                if not sel:
                    return ResultadoSpec.fallo(f"Falta selección obligatoria: {grupo.etiqueta}", "GRUPO_REQUERIDO")
                if grupo.tipo_seleccion == TipoSeleccion.UNA_A_N and len(sel.unidades_cubiertas) < grupo.min_selecciones:
                    return ResultadoSpec.fallo(f"Mínimo {grupo.min_selecciones} en {grupo.etiqueta}", "MIN_SELECCIONES")
        return ResultadoSpec.ok()

@dataclass(slots=True)
class SpecEdadHabilitada:
    """La edad del participante permite la opción."""
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for sel in ctx.selecciones:
            opcion = ctx.evento.obtener_opcion(sel.grupo_id, sel.opcion_id)
            if opcion and hasattr(opcion, 'edad_minima') and ctx.edad < opcion.edad_minima:
                return ResultadoSpec.fallo(f"Edad mínima {opcion.edad_minima} para {opcion.etiqueta}", "EDAD_MINIMA")
            if opcion and hasattr(opcion, 'edad_maxima') and ctx.edad > opcion.edad_maxima:
                return ResultadoSpec.fallo(f"Edad máxima {opcion.edad_maxima} para {opcion.etiqueta}", "EDAD_MAXIMA")
        return ResultadoSpec.ok()

@dataclass(slots=True)
class SpecCamposRequeridos:
    """Los campos requeridos de la opción están rellenos."""
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for sel in ctx.selecciones:
            opcion = ctx.evento.obtener_opcion(sel.grupo_id, sel.opcion_id)
            if opcion:
                for campo in opcion.campos:
                    if campo.requerido and not sel.valores_campos.get(campo.clave):
                        return ResultadoSpec.fallo(f"Campo requerido: {campo.etiqueta}", "CAMPO_REQUERIDO")
        return ResultadoSpec.ok()

@dataclass(slots=True)
class SpecDependencias:
    """Reglas: si A → requerir/ocultar B."""
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for grupo in ctx.evento.grupos:
            for dep in grupo.dependencias:
                if self._condicion_cumplida(dep, ctx):
                    if dep.accion == "requerir":
                        sel = next((s for s in ctx.selecciones if s.grupo_id == dep.grupo_id and s.opcion_id == dep.opcion_id), None)
                        if not sel:
                            return ResultadoSpec.fallo(f"Se requiere {dep.opcion_id} por {dep.tipo}", "DEPENDENCIA_REQUERIDA")
                    elif dep.accion == "deshabilitar":
                        sel = next((s for s in ctx.selecciones if s.grupo_id == dep.grupo_id and s.opcion_id == dep.opcion_id), None)
                        if sel:
                            return ResultadoSpec.fallo(f"Opción {dep.opcion_id} incompatible", "DEPENDENCIA_INCOMPATIBLE")
        return ResultadoSpec.ok()

    def _condicion_cumplida(self, dep: Dependencia, ctx: ContextoValidacion) -> bool:
        if dep.tipo == "opcion_seleccionada":
            return any(s.grupo_id == dep.grupo_id and s.opcion_id == dep.opcion_id for s in ctx.selecciones)
        if dep.tipo == "edad_comparacion":
            ops = {"<": lambda a,b: a<b, "<=": lambda a,b: a<=b, ">": lambda a,b: a>b, ">=": lambda a,b: a>=b, "==": lambda a,b: a==b}
            return ops.get(dep.operador, lambda a,b: False)(ctx.edad, dep.valor_edad)
        if dep.tipo == "campo_rellenado":
            for sel in ctx.selecciones:
                if sel.valores_campos.get(dep.campo_clave):
                    return True
        return False

# --- Combinadores ---
class AndSpec:
    def __init__(self, *specs: Especificacion):
        self.specs = specs
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for s in self.specs:
            r = s.evaluar(ctx)
            if not r.ok:
                return r
        return ResultadoSpec.ok()

class OrSpec:
    def __init__(self, *specs: Especificacion):
        self.specs = specs
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        for s in self.specs:
            if s.evaluar(ctx).ok:
                return ResultadoSpec.ok()
        return ResultadoSpec.fallo("Ninguna condición OR cumplida")

class NotSpec:
    def __init__(self, spec: Especificacion):
        self.spec = spec
    def evaluar(self, ctx: ContextoValidacion) -> ResultadoSpec:
        r = self.spec.evaluar(ctx)
        return ResultadoSpec.ok() if not r.ok else ResultadoSpec.fallo(r.mensaje)
```

---

## 7. Pipeline de expansión y cotización (caso de uso `CotizarRegistro`)

```python
# application/cotizar.py
from dataclasses import dataclass
from typing import Protocol
from decimal import Decimal

@dataclass(slots=True)
class ResultadoCotizacion:
    cotizacion: Cotizacion
    avisos: list[str] = field(default_factory=list)  # warnings no bloqueantes

class Cotizador:
    """Pipeline: expandir → validar → precio base → reglas → total → snapshot."""
    def __init__(self,
                 expansores: dict[str, "ExpansorUnidades"],
                 validador: "ValidadorRegistro",
                 politica: PoliticaPrecios):
        self.expansores = expansores
        self.validador = validador
        self.politica = politica

    def cotizar(self, registro: Registro, evento: Evento, ahora: datetime) -> ResultadoCotizacion:
        # 1. Validar restricciones duras
        ctx_val = ContextoValidacion(registro.participante, evento, registro.selecciones,
                                     registro.participante.edad_en(evento.inicio.date()), ahora)
        res_valid = self.validador.validar(ctx_val)
        if not res_valid.ok:
            raise ValueError(f"Validación falló: {res_valid.mensaje} [{res_valid.codigo}]")

        # 2. Expandir selecciones → unidades facturables
        todas_unidades: list[UnidadCubierta] = []
        for sel in registro.selecciones:
            grupo = evento.obtener_grupo(sel.grupo_id)
            opcion = evento.obtener_opcion(sel.grupo_id, sel.opcion_id)
            if not grupo or not opcion:
                continue
            expansor = self.expansores.get(grupo.tipo_oferta.value)
            if expansor:
                unidades = expansor.expandir(sel, opcion, grupo, evento, registro.participante)
                sel.unidades_cubiertas = unidades
                todas_unidades.extend(unidades)
            # Congelar etiquetas para recibo
            sel.etiqueta_grupo_congelada = grupo.etiqueta
            sel.etiqueta_opcion_congelada = opcion.etiqueta
            if sel.franja_id:
                franja = next((f for f in opcion.franjas if f.id == sel.franja_id), None)
                if franja:
                    sel.etiqueta_franja_congelada = franja.etiqueta

        # 3. Líneas base
        lineas_base = []
        for unidad in todas_unidades:
            opcion = evento.obtener_opcion(unidad.opcion_id or "", "")  # buscar por contexto
            precio_unit = opcion.precio_base if opcion else Dinero(Decimal("0"))
            # Override por franja
            if unidad.franja_id and opcion:
                franja = next((f for f in opcion.franjas if f.id == unidad.franja_id), None)
                if franja and franja.precio_override:
                    precio_unit = franja.precio_override
            lineas_base.append(LineaCotizacion(
                descripcion=unidad.descripcion,
                base=precio_unit,
                cantidad=unidad.cantidad,
                subtotal=precio_unit * unidad.cantidad,
                opcion_id=unidad.opcion_id or "",
                opcion_etiqueta=unidad.descripcion.split(" ")[0],  # simplificado
                tipo_unidad=unidad.tipo_unidad
            ))

        # 4. Aplicar reglas de precio en orden
        ctx_precio = ContextoPrecio(registro.participante, evento, registro.selecciones,
                                    registro.participante.edad_en(evento.inicio.date()), ahora)
        ajustes = []
        for regla in self.politica.reglas:
            for aj in regla.evaluar(ctx_precio):
                ajustes.append(LineaCotizacion(
                    descripcion=aj.descripcion,
                    base=Dinero(Decimal("0")),
                    cantidad=1,
                    subtotal=aj.monto,
                    opcion_id="",
                    opcion_etiqueta="",
                    tipo_unidad="ajuste",
                    es_ajuste=True,
                    motivo_ajuste=aj.motivo
                ))

        # 5. Total y snapshot
        subtotal_base = sum((l.subtotal for l in lineas_base), Dinero(Decimal("0")))
        total_ajustes = sum((l.subtotal for l in ajustes), Dinero(Decimal("0")))
        total = subtotal_base + total_ajustes

        cotizacion = Cotizacion(
            version_catalogo=evento.version,
            version_politica=self.politica.version,
            lineas_base=tuple(lineas_base),
            ajustes=tuple(ajustes),
            total=total.redondear(),
            calculada_en=ahora,
            moneda=self.politica.moneda
        )
        return ResultadoCotizacion(cotizacion=cotizacion)
```

---

### 7.1 Expansores por tipo de oferta (4 en total)

```python
# domain/expansores.py
class ExpansorUnidades(Protocol):
    def expandir(self,
                 seleccion: Seleccion,
                 opcion: Opcion,
                 grupo: GrupoEleccion,
                 evento: Evento,
                 participante: Participante) -> list[UnidadCubierta]: ...

# --- Implementaciones ---

class ExpansorTransporte:
    """Cada franja = un trayecto. Si 'propio' → 0 trayectos (solo campos)."""
    def expandir(self, seleccion, opcion, grupo, evento, participante):
        unidades = []
        if opcion.tipo_cobro_base == TipoCobroBase.POR_TRAYECTO:
            if seleccion.franja_id:
                franja = next(f for f in opcion.franjas if f.id == seleccion.franja_id)
                unidades.append(UnidadCubierta(
                    tipo_unidad="trayecto", cantidad=1,
                    descripcion=franja.etiqueta,
                    fecha=franja.fecha_salida, momento="ida" if grupo.tipo_oferta == TipoOferta.TRANSPORTE_IDA else "regreso",
                    opcion_id=opcion.id, franja_id=franja.id
                ))
        # 'propio' no genera unidades facturables (precio 0)
        return unidades

class ExpansorAlojamiento:
    """Noches = (fecha_salida - fecha_llegada) en días, salvo override manual."""
    def expandir(self, seleccion, opcion, grupo, evento, participante):
        unidades = []
        noches = self._calcular_noches(seleccion, opcion, evento)
        if noches > 0:
            unidades.append(UnidadCubierta(
                tipo_unidad="noche", cantidad=noches,
                descripcion=f"Noche en {opcion.etiqueta}",
                fecha=evento.inicio.date(),  # referencia
                opcion_id=opcion.id
            ))
        return unidades

    def _calcular_noches(self, seleccion, opcion, evento):
        # Si hay franja (cama asignada), usar fechas de la franja
        # Si modo SESIONES_ELEGIDAS, derivar de sesiones seleccionadas
        # Por defecto: (fin - inicio).days
        return (evento.fin.date() - evento.inicio.date()).days

class ExpansorAlimentacion:
    """Dos modos: MATRIZ (celda día×momento) o PAQUETE (reemplaza todo)."""
    def expandir(self, seleccion, opcion, grupo, evento, participante):
        if grupo.es_matriz:
            return self._expandir_matriz(seleccion, opcion, grupo, evento)
        else:
            return self._expandir_paquete(seleccion, opcion, grupo, evento)

    def _expandir_matriz(self, seleccion, opcion, grupo, evento):
        unidades = []
        # seleccion.valores_campos contiene claves como "2026-03-15_almuerzo" → "vegetariano"
        for dia in grupo.dias_matriz:
            for momento in grupo.momentos_matriz:
                clave = f"{dia.isoformat()}_{momento}"
                dieta = seleccion.valores_campos.get(clave)
                if dieta:
                    unidades.append(UnidadCubierta(
                        tipo_unidad="comida", cantidad=1,
                        descripcion=f"{momento.capitalize()} {dieta} {dia.strftime('%d/%m')}",
                        fecha=dia, momento=momento,
                        opcion_id=opcion.id
                    ))
        return unidades

    def _expandir_paquete(self, seleccion, opcion, grupo, evento):
        # El paquete no expande aquí; ReglaPaquete reemplaza en pipeline
        return []

class ExpansorSesiones:
    """Si modo SESIONES_ELEGIDAS, cada sesión elegida genera unidad."""
    def expandir(self, seleccion, opcion, grupo, evento, participante):
        unidades = []
        for sesion_id in seleccion.valores_campos.get("sesiones_elegidas", []):
            sesion = next((s for s in evento.sesiones if s.id == sesion_id), None)
            if sesion:
                unidades.append(UnidadCubierta(
                    tipo_unidad="sesion", cantidad=1,
                    descripcion=f"Sesión: {sesion.titulo}",
                    fecha=sesion.inicio.date(),
                    opcion_id=opcion.id
                ))
        return unidades
```

---

## 8. Casos de uso de aplicación (Application Services)

| Caso de uso | Responsabilidad | Entrada | Salida |
|-------------|-----------------|---------|--------|
| `DisenadorDeEvento` | CRUD catálogo, versionar, publicar | comandos backoffice | `Evento` versionado |
| `AsistenteDeRegistro` | Wizard paso a paso, validar, previsualizar cotización | `PasoWizardRequest` | `PasoWizardResponse` (opciones, campos, preview) |
| `CotizarRegistro` | Pipeline completo §7 | `Registro` borrador | `Cotizacion` (snapshot) |
| `Cobrador` | Crear `PaymentIntent`, adaptador pasarela, confirmar manual | `Cotizacion` + método | `PaymentIntent` / `Pago` confirmado |
| `EmisorRecibo` | Render `Recibo` → PDF / HTML | `Registro` PAGADO | `Recibo` + bytes PDF |
| `EscritorioRegistros` | Listar, filtrar, ajustes manuales, asignar equipos, exportar manifiestos | filtros | vistas/exportaciones |

**Regla de oro:** El servidor es la **única fuente de verdad de precios**. El frontend solo previsualiza; al confirmar, el backend recalcula y congela.

---

## 9. Puertos y Adaptadores (Hexagonal)

```python
# ports/
class RepositorioEventos(Protocol):
    def guardar(self, evento: Evento): ...
    def obtener(self, id: str) -> Optional[Evento]: ...
    def obtener_publicado(self, id: str) -> Optional[Evento]: ...
    def listar(self, filtros: dict) -> list[Evento]: ...

class RepositorioRegistros(Protocol):
    def guardar(self, registro: Registro): ...
    def obtener(self, id: str) -> Optional[Registro]: ...
    def listar(self, filtros: dict) -> list[Registro]: ...

class PagoPort(Protocol):
    def crear_intent(self, monto: Dinero, referencia: str, metadata: dict) -> "PaymentIntent": ...
    def verificar_estado(self, intent_id: str) -> "EstadoPago": ...
    def reembolsar(self, intent_id: str, monto: Dinero) -> bool: ...

class RelojPort(Protocol):
    def ahora(self) -> datetime: ...

class PdfPort(Protocol):
    def render_recibo(self, recibo: Recibo) -> bytes: ...

class NotificadorPort(Protocol):
    def enviar_email(self, to: str, subject: str, html: str, adjuntos: list[bytes] = None): ...
    def enviar_whatsapp(self, to: str, mensaje: str): ...
```

Adaptadores concretos (solo en capa `adapters/`):
- `SqlAlchemyRepositorioEventos`, `SqlAlchemyRepositorioRegistros`
- `WompiAdapter`, `MercadoPagoAdapter`, `TransferenciaManualAdapter` (implementan `PagoPort`)
- `RelojSistema`, `WeasyPrintPdfAdapter`, `SmtpNotificador`, `TwilioNotificador`

---

## 10. Pila tecnológica — decidida por el hosting

Producción es GoDaddy Web Hosting (cPanel), no un contenedor. Eso descarta Python, FastAPI y Node como aplicación.

Hecho verificado en la ayuda de GoDaddy (tabla de Web Hosting cPanel): PHP 8.1 queda en Extended Support desde el 1 de enero de 2026; el mismo plan ofrece 8.2, 8.3 (predeterminado) y 8.4. PHP 8.1 dejó de recibir parches el 31 de diciembre de 2025. CodeIgniter 4.7 exige PHP 8.2 o superior. Laravel 10, el último que aceptaba 8.1, está fuera de soporte. Un producto aparte, GoDaddy Node.js Hosting, está en beta y no es este cPanel: ahí no corren procesos largos de Node ni de Python.

| Capa | Decisión | Por qué |
|---|---|---|
| Lenguaje | PHP 8.3 | Es el predeterminado de GoDaddy y lo acepta CodeIgniter 4.7. No desarrollar en 8.1 |
| Framework | CodeIgniter 4.7 | Ligero para hosting compartido, ya lo usas, y el dominio puede vivir fuera del framework |
| Dominio | PHP plano en `app/Domain/` | Cotización, reglas y ficha se prueban con PHPUnit sin levantar HTTP |
| Base de datos | MariaDB | La tienes en GoDaddy. Sin PostgreSQL. `JSON`, no `JSONB`. Dinero en `DECIMAL` |
| Sesión | Tabla en MariaDB | En cPanel la sesión en archivo es frágil |
| Pantallas | Vistas de CodeIgniter | Sin SPA y sin paso de build. El wizard lo pinta el servidor |
| PDF | Dompdf o el HTML del recibo impreso | WeasyPrint no corre en este hosting |
| Pagos | Enlace Bold, transferencia, Ruta, efectivo | Solo tarjeta sola y transferencia sola cobran en el registro |
| Correo | El correo del propio hosting, o un API HTTP | GoDaddy suele bloquear SMTP saliente arbitrario |
| Pruebas | PHPUnit | El mismo contenedor que imita producción |
| Desarrollo | Podman Compose | PHP 8.3 + MariaDB de la misma línea que producción. Sin Node |

La tabla pública de GoDaddy lista MariaDB 10.6. En la cuenta viste 10.11.19. El modelo no usa nada que 10.6 no tenga. El contenedor se clava a la versión que devuelva `SELECT VERSION()` en esa base.

Desarrollo, en WSL, con el mismo hábito que el proyecto valet:

- `app`: imagen `php:8.3-apache`, document root en `public/`, extensiones `intl`, `mbstring`, `pdo_mysql`, `gd`, `zip`
- `db`: `mariadb:10.11` hasta confirmar la cuenta; si es 10.6, se cambia el tag y nada más
- `mailpit`: para ver los correos sin salir a internet
- `php.ini` con el mismo tope de subida que GoDaddy, porque el comprobante es un archivo
- PHPUnit dentro de ese contenedor, no contra el PHP 8.1 de Windows ni el 3.14 del sistema

Producción no lleva Compose. Se sube el resultado de `composer install --no-dev`, con `vendor/` incluido si el plan no tiene Composer. `writable/` tiene que poder escribir. Los comprobantes no van a una carpeta pública: los sirve un controlador, y solo tesorería.

---

## 11. Persistencia — Esquema relacional (MariaDB)

```sql
-- Tablas principales (DDL conceptual)
CREATE TABLE eventos (
    id UUID PRIMARY KEY,
    version INT NOT NULL,
    publicado BOOLEAN NOT NULL DEFAULT FALSE,
    nombre VARCHAR(200), descripcion TEXT, ciudad VARCHAR(100), lugar VARCHAR(200),
    inicio TIMESTAMPTZ, fin TIMESTAMPTZ,
    inscripciones_abren TIMESTAMPTZ, inscripciones_cierran TIMESTAMPTZ,
    modo_asistencia VARCHAR(30),
    catalogo_json JSONB NOT NULL,           -- grupos, opciones, franjas, campos, sesiones
    politica_precios_json JSONB,            -- reglas parametrizadas
    creado_en TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE participantes (
    id UUID PRIMARY KEY,
    nombre_completo VARCHAR(200), documento VARCHAR(30) UNIQUE,
    fecha_nacimiento DATE, email VARCHAR(200), telefono VARCHAR(30),
    acudiente_json JSONB
);

CREATE TABLE registros (
    id UUID PRIMARY KEY,
    evento_id UUID REFERENCES eventos(id),
    version_catalogo INT NOT NULL,
    participante_id UUID REFERENCES participantes(id),
    estado VARCHAR(30) NOT NULL,
    cotizacion_json JSONB,                  -- snapshot Cotizacion
    pago_id UUID,
    creado_en TIMESTAMPTZ DEFAULT NOW(),
    actualizado_en TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE selecciones (
    id UUID PRIMARY KEY,
    registro_id UUID REFERENCES registros(id) ON DELETE CASCADE,
    grupo_id VARCHAR(50), opcion_id VARCHAR(50), franja_id VARCHAR(50),
    unidades_json JSONB NOT NULL,           -- array UnidadCubierta
    valores_campos JSONB NOT NULL,
    etiquetas_congeladas_json JSONB
);

CREATE TABLE pagos (
    id UUID PRIMARY KEY,
    registro_id UUID REFERENCES registros(id),
    monto NUMERIC(18,0) NOT NULL,           -- en centavos COP
    moneda VARCHAR(3) DEFAULT 'COP',
    metodo VARCHAR(30),                     -- wompi, transferencia, efectivo, beca
    estado VARCHAR(30),                     -- pendiente, confirmado, fallido, reembolsado
    referencia_externa VARCHAR(100),
    intent_json JSONB,                      -- respuesta pasarela
    confirmado_por UUID,                    -- usuario backoffice
    confirmado_en TIMESTAMPTZ,
    creado_en TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE reservas_cupo (
    id UUID PRIMARY KEY,
    evento_id UUID, opcion_id VARCHAR(50), franja_id VARCHAR(50),
    registro_id UUID, cantidad INT DEFAULT 1,
    estado VARCHAR(20),                     -- hold, confirmed, released
    expira_en TIMESTAMPTZ,
    UNIQUE (evento_id, opcion_id, franja_id, registro_id)
);
```

**Concurrencia en cupos:** `reservas_cupo` con `estado='hold'` (15 min) durante pago → `confirmed` al confirmar pago → `released` si expira o cancela. Transacción `SELECT ... FOR UPDATE` en franja/opción.

---

## 12. Flujo de publicación y versionado

1. Backoffice edita **borrador** (clona versión publicada → `version+1`, `publicado=false`)
2. Al **publicar**: se valida catálogo completo, se adjunta `PoliticaPrecios`, `publicado=true`, `version+1`
3. **Registros en curso** (BORRADOR/COTIZADO) quedan anclados a `version_catalogo` anterior
   - Si participante vuelve al wizard y el catálogo cambió → aviso "El evento se actualizó; revisa tus opciones"
   - Al recotizar, se usa **nueva versión** si el participante acepta; la cotización vieja se descarta
4. **Registros PAGADOS/CONFIRMADOS** nunca cambian; su `cotizacion_json` es inmutable

---

## 13. Operaciones (manifiestos) — Consultas derivadas, no entidades

El backoffice exporta:
- **Manifiesto transporte**: `SELECT ... FROM selecciones WHERE grupo.tipo_oferta IN ('transporte_ida','transporte_regreso')` → lista por franja (bus/placa), hora, origen/destino
- **Manifiesto alojamiento**: noches por opción, por participante
- **Manifiesto alimentación**: por fecha, momento, dieta (matriz)

Estas son **vistas SQL / consultas de aplicación**, no nuevas entidades de dominio.

---

## 13.1 Acceso al backoffice — decidido

El backoffice no es una URL abierta. Toda pantalla y toda API de gestión exigen un usuario autenticado. La cara del participante sigue siendo un enlace público del evento: inscribirse no crea ni pide cuenta de backoffice.

**No hay registro público de organizadores.** Un administrador invita por correo. La persona abre un enlace de un solo uso, define su contraseña y entra. Desactivar un usuario no lo borra: conserva quién confirmó un pago o quién exportó alergias.

### Usuarios y roles

Un usuario es de la organización, no un participante. Tiene nombre, correo, contraseña (solo el hash), estado (invitado, activo, desactivado) y uno o más roles.

El login solo demuestra identidad. Lo que puede hacer lo decide un permiso. Los roles son paquetes de permisos, para no preguntar "¿es admin?" en cada pantalla.

| Permiso | Administrador | Editor de eventos | Tesorería | Logística | Consulta |
|---|---|---|---|---|---|
| Entrar al backoffice | sí | sí | sí | sí | sí |
| Invitar y desactivar usuarios | sí | no | no | no | no |
| Crear y publicar eventos, ficha, precios | sí | sí | no | no | no |
| Ver inscripciones (sin salud ni documento completo) | sí | sí | sí | sí | sí |
| Ver ficha de salud (alergias, seguro, emergencia) | sí | no | no | sí | no |
| Confirmar transferencia, beca, ajuste manual de precio | sí | no | sí | no | no |
| Asignar equipos, cupos, manifiestos | sí | no | no | sí | no |
| Exportar listas | sí | no | sí | sí | no |

Consulta existe para que alguien mire el estado del curso sin llevarse datos de salud ni tocar dinero. Un voluntario de puerta, si hace falta, es Logística o un rol posterior de check-in; no se le da Tesorería "para que pueda entrar".

### Qué protege el login

- Crear o cambiar el catálogo, la ficha y las reglas de precio.
- Ver o exportar documentos, teléfonos, alergias, seguros y contactos de emergencia.
- Confirmar pagos, emitir recibos, aplicar becas o ajustes manuales.
- Asignar buses, camas y equipos.

Ocultar el botón no basta. El caso de uso rechaza la acción si el usuario no tiene el permiso, aunque alguien llame la API directo.

### Reglas de sesión

- Contraseña con hash Argon2id. Nunca se guarda ni se escribe en logs.
- Sesión en cookie `HttpOnly`, `Secure`, `SameSite`. Cierra sola tras inactividad (propuesta: 8 horas, o al cerrar el navegador si el usuario no marcó "recordarme").
- Cinco intentos fallidos bloquean ese correo 15 minutos. No se dice si el correo existe.
- Cada acción sensible deja rastro: quién, cuándo, qué registro o evento, y el motivo si es un ajuste de precio o una beca.
- El enlace de invitación y el de restablecer contraseña vencen (propuesta: 24 horas) y sirven una sola vez.

Doble factor no entra en la primera versión. El puerto de autenticación queda listo para agregarlo primero a Administrador y Tesorería, que son quienes mueven dinero y usuarios.

### Multi-centro, sin cerrarlo aún

El usuario puede quedar limitado a un centro (`centro_id` opcional). Mientras la sangha opere un solo backoffice, el campo va vacío y el usuario ve todos los eventos. No se diseña un segundo sistema de login por ciudad.

---

## 14. Decisiones abiertas (para resolver antes de Fase 1)

| # | Decisión | Impacto arquitectónico |
|---|----------|------------------------|
| 1 | **Multi-centro / multi-ciudad** (eventos simultáneos en Bogotá, Medellín, Cali) | `Evento.centro_id` → aislamiento de catálogo; ¿backoffice único o por centro? |
| 2 | **Cuenta participante** (login, historial) vs **invitado con documento** | `Participante` agrega `usuario_id` opcional; ¿recuperar borradores entre dispositivos? |
| 3 | **Registro familiar** (un pagador, varios participantes) | Nuevo agregado `Pedido` que agrupa `Registro`s; pago único, recibos individuales |
| 4 | **Becas y lista de espera** | Estados `BECA`, `LISTA_ESPERA`; reglas de prioridad; auditoría backoffice |
| 5 | **Política de cancelación / reembolso** | Reglas de precio inversas; `PagoPort.reembolsar` |
| 6 | **Moneda única COP** vs multi-moneda | `Dinero.moneda` fija COP simplifica todo; confirmar |
| 7 | **Ficha por evento** (decidido, §3.3): plantilla base + agregar/quitar. Sigue abierto qué campos entran en la plantilla de la sangha y cuáles exigen consentimiento explícito (salud, alergias, seguro) | Ley 1581/2012: no pedir de más; no loguear documento ni datos de salud |
| 8 | **Acceso backoffice** (decidido, §13.1): login obligatorio, invitación, roles con permisos. Sigue abierto si Consulta debe ver el teléfono | Sin cuenta de participante en la cara pública |
| 9 | **Check-in presencial** (QR, lista impresa) | Módulo posterior; comparte `Registro.id` |
|10| **Integración contable** (exportar a Siigo/Alegra) | `EscritorioRegistros.exportar_contable()` |

---

## 15. Fase 1 — **Dominio puro con tests** (entregable validable sin HTTP/BD)

> **Objetivo:** Probar que la arquitectura aguanta el escenario real del retiro de meditación (§16) **antes** de tocar FastAPI/SQLAlchemy.

### Estructura de carpetas (Python)

```
src/
├── domain/
│   ├── __init__.py
│   ├── value_objects.py      # Dinero
│   ├── tipos.py              # Enums
│   ├── catalogo.py           # Evento, GrupoEleccion, Opcion, FranjaHoraria, Sesion, PoliticaPrecios, DefinicionCampo, Dependencia
│   ├── registro.py           # Participante, Seleccion, UnidadCubierta, Cotizacion, Recibo, Registro (AR)
│   ├── precios.py            # PricingRule protocol, ReglaBandaEdad, ReglaVentanaFecha, ReglaRol, ReglaPaquete, AjusteManual, ContextoPrecio, AjustePrecio
│   ├── especificaciones.py   # Especificacion protocol, SpecCupoDisponible, SpecGrupoRequeridoCompleto, SpecEdadHabilitada, SpecCamposRequeridos, SpecDependencias, And/Or/Not
│   ├── expansores.py         # ExpansorUnidades protocol, ExpansorTransporte, ExpansorAlojamiento, ExpansorAlimentacion, ExpansorSesiones
│   └── eventos.py            # Eventos de dominio (opcional: RegistroCreado, CotizacionGenerada, PagoConfirmado)
├── application/
│   ├── __init__.py
│   ├── cotizar.py            # Cotizador (pipeline §7)
│   ├── asistente_registro.py # AsistenteDeRegistro (wizard steps)
│   ├── cobrador.py           # Cobrador (PaymentIntent, confirmar manual)
│   ├── emisor_recibo.py      # EmisorRecibo (Recibo → PDF via port)
│   ├── disenador_evento.py   # DisenadorDeEvento (CRUD catálogo, publicar)
│   └── escritorio_registros.py # EscritorioRegistros (listar, ajustes, manifiestos)
├── ports/
│   ├── __init__.py
│   ├── repositorio.py
│   ├── pago.py
│   ├── reloj.py
│   ├── pdf.py
│   └── notificador.py
└── adapters/
    ├── __init__.py
    ├── fakes.py              # FakeRepositorio, FakePago, FakeReloj, FakePdf, FakeNotificador (para tests)
    └── (adaptadores reales en fases posteriores)
tests/
├── domain/
│   ├── test_dinero.py
│   ├── test_catalogo.py
│   ├── test_registro.py
│   ├── test_precios.py
│   ├── test_especificaciones.py
│   └── test_expansores.py
├── application/
│   ├── test_cotizador.py
│   └── test_asistente_registro.py
└── conftest.py               # Fixtures: evento_retiro_primavera, participante_menor, participante_adulto
```

### Tests obligatorios Fase 1 (escenarios del §16)

| Test | Qué valida |
|------|------------|
| `test_dinero_operaciones_seguras` | Suma, resta, %, redondeo, monedas distintas fallan |
| `test_evento_versionado_publicar_clonar` | Publicar fija versión; clonar crea borrador independiente |
| `test_grupo_eleccion_tipos_seleccion` | EXACTAMENTE_UNA, CERO_O_UNA, CERO_A_N, UNA_A_N |
| `test_franja_horaria_cupo_reservar_liberar` | Cupo decrece/crece; disponible no negativo |
| `test_participante_edad_en_evento` | Edad correcta en límite de cumpleaños |
| `test_expansor_transporte_franja_genera_trayecto` | Franja → UnidadCubierta(trayecto) con etiqueta legible |
| `test_expansor_alojamiento_noches_desde_fechas` | Noches = (fin-inicio).days; override por franja |
| `test_expansor_alimentacion_matriz_celdas` | Matriz día×momento → múltiples UnidadCubierta(comida) |
| `test_expansor_alimentacion_paquete_no_expande` | Paquete devuelve [] (ReglaPaquete reemplaza) |
| `test_regla_banda_edad_porcentual_descuento` | Menor 12 → -50% sobre alojamiento+alimentación |
| `test_regla_early_bird_fecha_limite` | Registro antes de fecha → descuento |
| `test_regla_paquete_reemplaza_opciones` | Pensión completa reemplaza líneas base individuales |
| `test_spec_cupo_disponible_falla_si_agotado` | Franja cupo 0 → fallo |
| `test_spec_grupo_requerido_completo` | Grupo EXACTAMENTE_UNA sin selección → fallo |
| `test_spec_edad_habilitada_minima` | Opción con edad_minima 18 + participante 16 → fallo |
| `test_spec_dependencias_opcion_requerida` | Si elige bus → requiere franja_horaria |
| `test_cotizador_pipeline_completo_retiro_primavera` | **Escenario integrado §16 → total 147.500 COP** |
| `test_cotizacion_snapshot_inmutable` | Modificar catálogo tras cotizar no cambia cotización |
| `test_recibo_contiene_snapshots` | Recibo tiene participante_snapshot, evento_snapshot, líneas legibles |
| `test_registro_estados_transiciones_validas` | BORRADOR→COTIZADO→PENDIENTE_PAGO→PAGADO; inválidas fallan |

### Comando de verificación Fase 1

```bash
cd C:/src/registrador_eventos_dwb
python -m pytest tests/ -v --tb=short -x
# Esperado: 20+ tests PASSED, 0 FAILED
```

---

## 16. Escenario de validación integrado — "Retiro Meditación de Primavera"

**Evento:** 14–17 mar 2026 (3 noches), Centro de Retiro, Bogotá
- **Transporte ida**: Grupo `transporte_ida` (EXACTAMENTE_UNA)
  - `propio` (INCLUIDO, campos: hora_llegada_estimada, placa, necesita_parqueo)
  - `bus_bogota` (POR_TRAYECTO $80.000, franjas: "14 mar 06:00 Bogotá→Centro cupo 40", "14 mar 08:00 Bogotá→Centro cupo 30")
  - `bus_medellin` (POR_TRAYECTO $120.000, franja: "13 mar 20:00 Medellín→Centro cupo 20")
- **Transporte regreso**: Grupo `transporte_regreso` (EXACTAMENTE_UNA) — mismo patrón
- **Alojamiento**: Grupo `alojamiento` (EXACTAMENTE_UNA)
  - `cama_compartida` (POR_NOCHE $20.000, franjas: 40 camas en dormitorio)
  - `carpa_propia` (POR_NOCHE $15.000, franjas: 30 parcelas)
  - `casa_amigo` (INCLUIDO, campos: direccion_anfitriona)
- **Alimentación**: Grupo `alimentacion` (EXACTAMENTE_UNA, `es_matriz=true`)
  - Días: 14, 15, 16, 17 | Momentos: desayuno, almuerzo, cena
  - Opciones por celda: `vegetariano` (POR_COMIDA $15.000), `vegano` (POR_COMIDA $18.000), `omnivoro` (POR_COMIDA $12.000)
  - Paquete alternativo: `pension_completa` (PAQUETE $90.000 reemplaza 12 comidas)
- **Colaboración**: Grupo `colaboracion` (CERO_A_N, max 2)
  - `equipo_cocina`, `equipo_limpieza`, `equipo_logistica` (INCLUIDO)
- **Política de precios v1**:
  1. `ReglaBandaEdad`: [(0, 11, "porcentual", 50, ["alojamiento","alimentacion"]), (12, 17, "porcentual", 25, ["alojamiento"])]
  2. `ReglaVentanaFecha`: [(2026-02-01, "porcentual", 10, [])]  # early bird 10% todo
  3. `ReglaPaquete`: [("pension_completa", $90.000, ["vegetariano","vegano","omnivoro"])]
  4. `ReglaRol`: [("equipo_cocina", "porcentual", 20, ["alojamiento"])]
  5. `AjusteManual`: []

**Participante caso prueba:** Juan Pérez, 10 años (nac 2016-05-20), elige:
- Ida: `bus_bogota` franja "14 mar 06:00"
- Regreso: `propio` (campos: hora 14:00, placa ABC123)
- Alojamiento: `carpa_propia` (3 noches)
- Alimentación: `pension_completa` (paquete)
- Colaboración: `equipo_cocina`

**Cotización esperada (ahora = 2026-01-15, antes de early bird):**
| Línea | Descripción | Base | Cant | Subtotal |
|-------|-------------|------|------|----------|
| Base | Bus Bogotá→Centro 14 mar 06:00 | 80.000 | 1 | 80.000 |
| Base | Noche en carpa propia | 15.000 | 3 | 45.000 |
| Base | Pensión completa (paquete) | 90.000 | 1 | 90.000 |
| **Subtotal base** | | | | **215.000** |
| Ajuste | Banda edad 0-11: -50% alojamiento+alimentación | -67.500 | 1 | -67.500 |
| Ajuste | Rol equipo_cocina: -20% alojamiento | -9.000 | 1 | -9.000 |
| **Total ajustes** | | | | **-76.500** |
| **TOTAL** | | | | **138.500** |

> Nota: Early bird no aplica (15 ene > 1 feb). Si fuera 20 ene, -10% sobre 215.000 = -21.500 → total 117.000.

**Recibo debe mostrar líneas legibles en español:**
- "Bus Bogotá → Centro de retiro, salida 14 mar 06:00 — $80.000"
- "Alojamiento en carpa propia, 3 noches × $15.000 — $45.000"
- "Pensión completa (paquete) — $90.000"
- "Ajuste por edad (menor de 12): −50% alojamiento y alimentación — −$67.500"
- "Ajuste por colaboración en equipo de cocina: −20% alojamiento — −$9.000"
- **TOTAL: $138.500 COP**

---

## 17. Fases posteriores (secuencia, sin desglose fino hasta confirmar stack)

| Fase | Entregable | Comentario |
|------|------------|------------|
| **1** | Dominio puro + tests (§15) | **Este plan cubre solo Fase 1** |
| 2 | Persistencia SQLAlchemy + repositorios fakes → reales | Migración Alembic; transacciones cupos |
| 3 | Wizard participante (HTMX) + AsistenteDeRegistro | Pasos dinámicos desde catálogo; preview cotización en vivo |
| 4 | Backoffice: login + DiseñadorEvento + EscritorioRegistros | Sin sesión no hay pantallas de gestión. Roles de §13.1 desde el primer pantallazo, no después |
| 5 | Pagos: Cobrador + adaptador Wompi + Transferencia manual + EmisorRecibo (PDF) | PaymentIntent monto exacto = cotización.total; confirmación manual backoffice |
| 6 | Operaciones: Manifiestos transporte/alojamiento/alimentación + Check-in QR | Vistas SQL exportables a Excel/PDF |
| 7 | Cancelaciones, becas, lista de espera, multi-centro, doble factor | El login y los roles ya no esperan a esta fase |

---

## 18. Archivos a crear en Fase 1 (lista exacta)

```
C:/src/registrador_eventos_dwb/
├── src/
│   ├── domain/
│   │   ├── __init__.py
│   │   ├── value_objects.py
│   │   ├── tipos.py
│   │   ├── catalogo.py
│   │   ├── registro.py
│   │   ├── precios.py
│   │   ├── especificaciones.py
│   │   ├── expansores.py
│   │   └── eventos.py
│   ├── application/
│   │   ├── __init__.py
│   │   ├── cotizar.py
│   │   ├── asistente_registro.py
│   │   ├── cobrador.py
│   │   ├── emisor_recibo.py
│   │   ├── disenador_evento.py
│   │   └── escritorio_registros.py
│   ├── ports/
│   │   ├── __init__.py
│   │   ├── repositorio.py
│   │   ├── pago.py
│   │   ├── reloj.py
│   │   ├── pdf.py
│   │   └── notificador.py
│   └── adapters/
│       ├── __init__.py
│       └── fakes.py
├── tests/
│   ├── conftest.py
│   ├── domain/
│   │   ├── test_dinero.py
│   │   ├── test_catalogo.py
│   │   ├── test_registro.py
│   │   ├── test_precios.py
│   │   ├── test_especificaciones.py
│   │   └── test_expansores.py
│   └── application/
│       ├── test_cotizador.py
│       └── test_asistente_registro.py
├── pyproject.toml           # deps: pytest, hypothesis, pydantic, python-dateutil
├── pytest.ini
└── README.md                # Cómo correr tests Fase 1
```

---

## 19. Próximos pasos

1. **Confirmar/responder dudas §10 y §14** (stack, multi-centro, cuenta participante, registro familiar, becas, moneda)
2. **Aprobar este PLAN.md** como base para Fase 1
3. **Ejecutar Fase 1** con `subagent-driven-development` (una sub-tarea por test/archivo, revisión en dos etapas)
4. Validar `pytest tests/ -v` → todo verde → pasar a Fase 2

---

**Guardado en:** `C:/src/registrador_eventos_dwb/PLAN.md` y `.hermes/plans/2026-11-09_160319-registrador-eventos-dwb-arquitectura.md`

---

*Fin del plan de arquitectura. El modelo usa composición configurable, estrategias de precio parametrizadas, especificaciones combinables, snapshots inmutables para recibos, y un pipeline de expansión que trata transporte/alojamiento/alimentación/sesiones como ofertas genéricas — sin una clase por cada invento de los organizadores.*

---

## 20. Decisiones que fijó la simulación del Tour 365

Precios y reglas cerrados para este evento (noviembre 2026, salvo que se indique otro año):

- Charla Bogotá: sin tarifa cargada, se trata como 0 hasta que digan lo contrario. Bucaramanga: 80.000. KPL: 150.000.
- Menor de 7: tarifa del curso en 0. De 7 a 14: 15 % solo sobre la tarifa del curso. Comida (8.000) y bus (100.000 por franja) no tienen descuento.
- El bus de las 9:00 es salida durante el curso: quien lo toma no completa el resto. No es un bus del día siguiente.
- Alojamiento no se cobra ni se cupa aquí. En KPL igual se pregunta carpa, cama por condición y pernocta extra, porque logística lo necesita. El valor queda fuera del total.
- No hay control de cupos. Las franjas sirven para la lista, no para bloquear.
- Documento: CC, TI, CE, pasaporte.
- Solo se cobra y se pide comprobante si la forma es transferencia o tarjeta, solas. Si la forma incluye Ruta budista, o es pago en sitio, el registro no cobra ni pide soporte.

Reglas de modelo que el caso obligó a cerrar:

- Un tour es **un** evento. Cada curso es una opción del grupo `cursos` (mínimo una). Transporte, comidas y equipos de ese curso solo aparecen si el curso fue elegido.
- La edad de este evento es un campo lista (banda), no fecha de nacimiento. La regla de precio lee ese valor.
- La dieta no cambia el precio. Se cobra cada comida marcada, al mismo valor. El calendario lo define el backoffice.
- La forma de pago es un grupo de exactamente una opción. Cuenta, Bre-b y enlace Bold son parámetros. El enlace Bold solo se genera en tarjeta sola, por el total de esa inscripción. El comprobante es del pago, no de la ficha.

---

## 21. Listo para implementar — defaults del primer corte

No falta una decisión de arquitectura. Estos defaults se usan salvo corrección:

- Una inscripción es una persona. Una familia son varios registros.
- Mismo documento no abre dos inscripciones activas al mismo evento.
- El participante no edita después de terminar. El backoffice sí.
- Zona horaria `America/Bogota`. Montos COP sin decimales.
- La ficha base incluye un consentimiento obligatorio de tratamiento de datos. Sin eso no se publica.
- Primer corte: dominio con la cotización del Tour 365, wizard público sembrado con ese evento, login y listado de inscritos. El diseñador visual de eventos es el corte siguiente.
- Textos de cuenta, Bre-b, enlace Bold, tarifa de la charla y el calendario fino de comidas son datos. No bloquean el código.


