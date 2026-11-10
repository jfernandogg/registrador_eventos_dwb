<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\ArchivoPrograma;
use App\Domain\EditorEvento;
use App\Domain\Tour365;
use PHPUnit\Framework\TestCase;

final class EditorEventoTest extends TestCase
{
    public function test_un_evento_vacio_se_puede_publicar(): void
    {
        $datos = EditorEvento::vacio('Retiro de prueba', 'retiro-prueba');
        $programa = ArchivoPrograma::importar($datos);

        $this->assertSame('Retiro de prueba', $programa->nombre);
        $this->assertSame('curso', $programa->curso('curso')?->codigo);
        $this->assertNotNull($programa->claveConRol('documento'));
    }

    public function test_el_formulario_no_pierde_el_ejemplo(): void
    {
        $original = ArchivoPrograma::exportar(Tour365::programa());
        $resultado = EditorEvento::desdeFormulario(EditorEvento::comoFormulario($original), $original);

        $this->assertSame([], $resultado['errores']);
        $republicado = ArchivoPrograma::exportar(ArchivoPrograma::importar($resultado['datos']));
        $this->assertSame($original['cursos'], $republicado['cursos']);
        $this->assertSame($original['comidas'], $republicado['comidas']);
        $this->assertSame($original['franjas'], $republicado['franjas']);
        $this->assertSame($original['ficha'], $republicado['ficha']);
        $this->assertSame($original['alojamientos'], $republicado['alojamientos']);
    }

    public function test_rechaza_un_evento_sin_curso(): void
    {
        $resultado = EditorEvento::desdeFormulario([
            'nombre' => 'Sin cursos',
            'precio_comida' => '0',
            'precio_franja' => '0',
            'pago_codigo' => ['efectivo'],
            'pago_etiqueta' => ['Pago en sitio'],
            'pago_cobra' => ['0'],
            'pago_comprobante' => ['0'],
            'campo_clave' => ['nombre'],
            'campo_etiqueta' => ['Nombre'],
            'campo_tipo' => ['texto'],
            'campo_requerido' => ['1'],
            'campo_opciones' => [''],
            'campo_visible' => [''],
            'campo_rol' => ['nombre'],
            'campo_mensaje' => [''],
            'campo_padre' => [''],
        ], []);

        $this->assertNotEmpty($resultado['errores']);
    }
}
