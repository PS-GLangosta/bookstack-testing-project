<?php

namespace Tests\Unit\Search;

use BookStack\Search\SearchOptions;
use Tests\TestCase;

/**
 * Pruebas unitarias para SearchOptions — Parseo de cadenas de búsqueda.
 *
 * Clase bajo prueba: BookStack\Search\SearchOptions
 * Ubicación: app/Search/SearchOptions.php
 * Issue: #14 (y complementos)
 * Sprint: 2
 * Responsable: Ower Frank Lopez Arela (Test Designer)
 *
 * Cobertura objetivo:
 *   - fromString(): parseo de términos, exacts, tags, filtros
 *   - Manejo de negación con prefijo '-'
 *   - limitOptions(): rate limiting por nivel de autenticación
 *   - toString(): serialización inversa
 *
 * @group sprint-2
 * @group search
 * @group search-options
 */
class SearchOptionsParsingTest extends TestCase
{
    // =========================================================================
    // UT-SRC-007 — Parseo de términos estándar separados por espacio
    // Técnica: Partición de equivalencia (clase válida: términos simples)
    // Requisito: RF-SRC-02
    // =========================================================================

    /** @test */
    public function parsea_terminos_estandar_separados_por_espacio(): void
    {
        // Arrange & Act
        $this->asEditor();
        $opciones = SearchOptions::fromString('laravel php testing');

        // Assert
        $this->assertEquals(
            ['laravel', 'php', 'testing'],
            $opciones->searches->toValueArray()
        );
    }

    // =========================================================================
    // UT-SRC-008 — Parseo de coincidencia exacta (comillas dobles)
    // Técnica: Partición de equivalencia (entrada mixta: términos + exacts)
    // Requisito: RF-SRC-02
    // =========================================================================

    /** @test */
    public function parsea_coincidencia_exacta_entre_comillas(): void
    {
        $this->asEditor();
        $opciones = SearchOptions::fromString('cat "dog house" bird');

        // Términos estándar
        $this->assertEquals(['cat', 'bird'], $opciones->searches->toValueArray());
        // Coincidencia exacta
        $this->assertEquals(['dog house'], $opciones->exacts->toValueArray());
    }

    // =========================================================================
    // UT-SRC-009 — Parseo de sintaxis de tags (corchetes)
    // Técnica: Partición de equivalencia (sintaxis de tag con y sin valor)
    // Requisito: RF-SRC-02
    // =========================================================================

    /** @test */
    public function parsea_sintaxis_de_tags_con_corchetes(): void
    {
        $this->asEditor();
        $opciones = SearchOptions::fromString('test [category=php] [priority]');

        $this->assertEquals(['test'], $opciones->searches->toValueArray());
        $this->assertEquals(
            ['category=php', 'priority'],
            $opciones->tags->toValueArray()
        );
    }

    // =========================================================================
    // UT-SRC-010 — Parseo de filtros (llaves con clave:valor)
    // Técnica: Tabla de decisión (flag booleano, clave:valor)
    // Requisito: RF-SRC-02
    // =========================================================================

    /** @test */
    public function parsea_filtros_clave_valor_entre_llaves(): void
    {
        $this->asEditor();
        $opciones = SearchOptions::fromString('{is_template} {created_by:admin} {sort_by:last_commented}');

        $filtros = $opciones->filters->toValueMap();

        $this->assertEquals('', $filtros['is_template']);
        $this->assertEquals('admin', $filtros['created_by']);
        $this->assertEquals('last_commented', $filtros['sort_by']);
    }

    // =========================================================================
    // UT-SRC-011 — Prefijo de negación (-) en exacts, tags y filtros
    // Técnica: Partición de equivalencia (negado vs no negado)
    // Requisito: RF-SRC-03
    // =========================================================================

    /** @test */
    public function prefijo_negacion_establece_flag_en_opciones(): void
    {
        $this->asEditor();
        $opciones = SearchOptions::fromString('cat -"dog" -[bad_tag] -{is_template}');

        // Término estándar 'cat' NO es negable
        $this->assertCount(1, $opciones->searches->all());

        // Exacts, tags y filtros con '-' deben tener negated=true
        $this->assertTrue(
            $opciones->exacts->all()[0]->negated,
            'Exact con prefijo - debe estar negado'
        );
        $this->assertTrue(
            $opciones->tags->all()[0]->negated,
            'Tag con prefijo - debe estar negado'
        );
        $this->assertTrue(
            $opciones->filters->all()[0]->negated,
            'Filter con prefijo - debe estar negado'
        );
    }

    // =========================================================================
    // UT-SRC-012 — Rate limiting: invitado (5) vs autenticado (10)
    // Técnica: Análisis de valores límite (en y por encima del límite)
    // Requisito: RF-SRC-04
    // Código fuente: limitOptions() líneas 156-168
    // =========================================================================

    /** @test */
    public function rate_limiting_diferente_para_guest_vs_autenticado(): void
    {
        // Arrange — Cadena con 15 términos
        $muchos_terminos = implode(' ', array_fill(0, 15, 'termino'));

        // Act & Assert — Sin autenticación: máximo 5 términos
        $opcionesGuest = SearchOptions::fromString($muchos_terminos);
        $this->assertCount(
            5,
            $opcionesGuest->searches->all(),
            'Usuarios invitados deben estar limitados a 5 términos'
        );

        // Act & Assert — Autenticado: máximo 10 términos
        $this->asEditor();
        $opcionesAuth = SearchOptions::fromString($muchos_terminos);
        $this->assertCount(
            10,
            $opcionesAuth->searches->all(),
            'Usuarios autenticados deben estar limitados a 10 términos'
        );
    }
}
