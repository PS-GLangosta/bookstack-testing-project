<?php

namespace Tests\Unit\Entities;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Tools\SlugGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pruebas unitarias para SlugGenerator.
 *
 * Clase bajo prueba: BookStack\Entities\Tools\SlugGenerator
 * Ubicación: app/Entities/Tools/SlugGenerator.php
 * Issue: #14
 * Sprint: 2
 *
 * Regla aplicada:
 * - Dependencia 0 de seeds.
 * - No usar $this->entities.
 * - No usar $this->users.
 * - No usar asAdmin/asEditor.
 *
 * @group sprint-2
 * @group slug
 */
class SlugGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    protected SlugGenerator $slugGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slugGenerator = new SlugGenerator();
    }

    /** @test */
    public function genera_slug_desde_nombre_ascii_estandar(): void
    {
        $nombre = 'My Test Book';

        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', [$nombre]);

        $this->assertEquals('my-test-book', $resultado);
    }

    /** @test */
    public function translitera_caracteres_multibyte_latin(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['información básica']);

        $this->assertEquals('informacion-basica', $resultado);
    }

    /** @test */
    public function translitera_caracteres_cirilicos(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['информация']);

        $this->assertEquals('informaciia', $resultado);
    }

    /** @test */
    public function elimina_caracteres_especiales_del_slug(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['PartA / PartB / PartC']);

        $this->assertEquals('parta-partb-partc', $resultado);
    }

    /** @test */
    public function elimina_diacriticos_y_puntuacion_combinados(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['¿Qué?']);

        $this->assertEquals('que', $resultado);
    }

    /** @test */
    public function cadena_vacia_produce_fallback_md5_de_5_caracteres(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['']);

        $this->assertNotEmpty($resultado, 'El slug no debe estar vacío para entrada vacía');
        $this->assertEquals(5, strlen($resultado), 'El fallback MD5 debe tener exactamente 5 caracteres');

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{5}$/',
            $resultado,
            'El fallback debe ser un fragmento hexadecimal MD5'
        );
    }

    /** @test */
    public function simbolos_solamente_activa_fallback_md5(): void
    {
        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', ['!#$%^&*()=/?']);

        $this->assertNotEmpty($resultado);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{5}$/',
            $resultado,
            'Input de solo símbolos debe activar el mismo fallback MD5'
        );
    }

    /** @test */
    public function colision_de_slug_agrega_sufijo_aleatorio(): void
    {
        $name = 'Libro Con Slug Repetido ' . uniqid();
        $slugBase = Str::slug($name);

        Book::factory()->create([
            'name' => $name,
            'slug' => $slugBase,
        ]);

        $bookCandidate = Book::factory()->make([
            'name' => $name,
            'slug' => null,
        ]);

        $generatedSlug = $this->slugGenerator->generate($bookCandidate, $name);

        $this->assertNotEquals(
            $slugBase,
            $generatedSlug,
            'Si ya existe un slug igual, el generador debe crear uno distinto.'
        );

        $this->assertStringStartsWith(
            $slugBase,
            $generatedSlug,
            'El slug con colisión debe mantener la base original.'
        );
    }

    /** @test */
    public function bookchild_slug_aislado_por_libro_padre(): void
    {
        $bookA = Book::factory()->create([
            'name' => 'Libro A ' . uniqid(),
            'slug' => 'libro-a-' . uniqid(),
        ]);

        $bookB = Book::factory()->create([
            'name' => 'Libro B ' . uniqid(),
            'slug' => 'libro-b-' . uniqid(),
        ]);

        Chapter::factory()->create([
            'book_id' => $bookA->id,
            'name' => 'Introducción',
            'slug' => 'introduccion',
        ]);

        $chapterCandidate = Chapter::factory()->make([
            'book_id' => $bookB->id,
            'name' => 'Introducción',
            'slug' => null,
        ]);

        $generatedSlug = $this->slugGenerator->generate($chapterCandidate, 'Introducción');

        $this->assertEquals(
            'introduccion',
            $generatedSlug,
            'Capítulos de libros distintos deben poder compartir slug porque el scope usa book_id.'
        );
    }

    /** @test */
    public function modelo_persistido_no_colisiona_consigo_mismo(): void
    {
        $name = 'Libro Persistido Sin Cambio ' . uniqid();
        $slugOriginal = Str::slug($name);

        $book = Book::factory()->create([
            'name' => $name,
            'slug' => $slugOriginal,
        ]);

        $generatedSlug = $this->slugGenerator->generate($book->refresh(), $book->name);

        $this->assertEquals(
            $slugOriginal,
            $generatedSlug,
            'Un modelo persistido no debe colisionar consigo mismo.'
        );
    }

    /** @test */
    public function cadena_de_200_chars_se_evalua_para_limite(): void
    {
        $cadenaLarga = str_repeat('a', 200);

        $resultado = $this->invocarMetodoProtegido('formatNameAsSlug', [$cadenaLarga]);

        $this->assertEquals(
            200,
            strlen($resultado),
            'QA Report: El componente SlugGenerator no trunca nativamente a 191. Se descubrió que conserva los 200 chars.'
        );
    }

    private function invocarMetodoProtegido(string $nombreMetodo, array $parametros): mixed
    {
        $reflection = new \ReflectionMethod(SlugGenerator::class, $nombreMetodo);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->slugGenerator, ...$parametros);
    }
}