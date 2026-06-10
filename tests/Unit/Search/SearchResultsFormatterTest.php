<?php

namespace Tests\Unit\Search;

use BookStack\Activity\Models\Tag;
use BookStack\Search\SearchResultsFormatter;
use Tests\TestCase;

/**
 * Pruebas unitarias para SearchResultsFormatter.
 *
 * Clase bajo prueba: BookStack\Search\SearchResultsFormatter
 * Ubicación: app/Search/SearchResultsFormatter.php
 * Issue: #14 (y complementos)
 * Sprint: 2
 * Responsable: Ower Frank Lopez Arela (Test Designer)
 *
 * Cobertura objetivo:
 *   - getMatchPositions(): detección de posiciones, límite de 25
 *   - sortAndMergeMatchPositions(): unificación de rangos
 *   - highlightTagsContainingTerms(): marcado de tags
 *   - formatTextUsingMatchPositions(): preview con elipsis y ventana
 *
 * @group sprint-2
 * @group search
 * @group formatter
 */
class SearchResultsFormatterTest extends TestCase
{
    protected SearchResultsFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new SearchResultsFormatter();
    }

    // =========================================================================
    // UT-SRC-013 — Detección de posición de match único
    // Técnica: Partición de equivalencia (match único en medio del texto)
    // Requisito: RF-SRC-05
    // =========================================================================

    /** @test */
    public function detecta_posicion_de_match_unico_correctamente(): void
    {
        // Arrange
        $texto = 'the quick brown fox';
        $terminos = ['quick'];

        // Act
        $posiciones = $this->invocarMetodoProtegido('getMatchPositions', [$texto, $terminos]);

        // Assert — "quick" comienza en el índice 4 y termina en el 9
        $this->assertArrayHasKey(4, $posiciones);
        $this->assertEquals(9, $posiciones[4]);
    }

    // =========================================================================
    // UT-SRC-014 — Merge de posiciones superpuestas
    // Técnica: Análisis de valores límite (rangos superpuestos y disjuntos)
    // Requisito: RF-SRC-05
    // =========================================================================

    /** @test */
    public function fusiona_posiciones_superpuestas_preservando_disjuntos(): void
    {
        // Arrange — [0,5] y [3,8] se superponen; [10,15] es disjunto
        $posiciones = [0 => 5, 3 => 8, 10 => 15];

        // Act
        $resultado = $this->invocarMetodoProtegido('sortAndMergeMatchPositions', [$posiciones]);

        // Assert
        $this->assertEquals(
            [0 => 8, 10 => 15],
            $resultado,
            'Debería fusionar los rangos que se superponen y mantener los separados'
        );
    }

    // =========================================================================
    // UT-SRC-015 — Detección case-insensitive
    // Técnica: Partición de equivalencia (entrada con mayúsculas/minúsculas)
    // Requisito: RF-SRC-05
    // =========================================================================

    /** @test */
    public function detecta_match_de_forma_case_insensitive(): void
    {
        // Arrange
        $texto = 'The QUICK Brown Fox';
        $terminos = ['quick'];

        // Act
        $posiciones = $this->invocarMetodoProtegido('getMatchPositions', [$texto, $terminos]);

        // Assert
        $this->assertArrayHasKey(
            4,
            $posiciones,
            'Debe encontrar el término ignorando diferencias de mayúsculas/minúsculas'
        );
    }

    // =========================================================================
    // UT-SRC-016 — Highlighting de tags que contienen términos
    // Técnica: Tabla de decisión (coincidencia en valor vs no coincidencia en nombre)
    // Requisito: RF-SRC-05
    // =========================================================================

    /** @test */
    public function resalta_valores_de_tags_que_contienen_terminos(): void
    {
        // Arrange
        $tag = new Tag(['name' => 'Category', 'value' => 'PHPUnit']);
        $terminos = ['php'];

        // Act
        $this->invocarMetodoProtegido('highlightTagsContainingTerms', [[$tag], $terminos]);

        // Assert — "PHPUnit" contiene "php", pero "Category" no
        $this->assertTrue($tag->getAttribute('highlight_value'));
        $this->assertNull($tag->getAttribute('highlight_name'));
    }

    // =========================================================================
    // UT-SRC-017 — Preview con ventana de contexto
    // Técnica: Caja blanca (cálculo de ventana alrededor del match)
    // Requisito: RF-SRC-06
    // =========================================================================

    /** @test */
    public function genera_preview_con_etiqueta_strong_y_ventana_de_contexto(): void
    {
        // Arrange
        $texto = str_repeat('a', 60) . 'TERMINO' . str_repeat('b', 60);
        $posiciones = [60 => 67];

        // Act
        $resultado = $this->invocarMetodoProtegido(
            'formatTextUsingMatchPositions',
            [$posiciones, $texto, 260]
        );

        // Assert
        $this->assertStringContainsString(
            '<strong>TERMINO</strong>',
            $resultado,
            'Debe envolver el término coincidente con etiquetas <strong>'
        );
    }

    // =========================================================================
    // UT-SRC-018 — Sin matches retorna texto truncado
    // Técnica: Análisis de valores límite (0 matches)
    // Requisito: RF-SRC-06
    // =========================================================================

    /** @test */
    public function sin_matches_retorna_texto_plano_truncado_con_elipsis(): void
    {
        // Arrange
        $texto = str_repeat('texto ', 50); // 300 chars
        $posicionesVacias = [];

        // Act
        $resultado = $this->invocarMetodoProtegido(
            'formatTextUsingMatchPositions',
            [$posicionesVacias, $texto, 260]
        );

        // Assert
        $this->assertStringEndsWith('...', $resultado);
        $this->assertStringNotContainsString('<strong>', $resultado);
    }

    // =========================================================================
    // UT-SRC-019 — Matches disjuntos separados por elipsis
    // Técnica: Partición de equivalencia (matches separados por distancia mayor a ventana)
    // Requisito: RF-SRC-06
    // =========================================================================

    /** @test */
    public function matches_distantes_se_separan_con_elipsis(): void
    {
        // Arrange
        $texto = str_repeat('a', 50) . 'MATCH_UNO' . str_repeat('b', 100) . 'MATCH_DOS' . str_repeat('c', 50);
        $posiciones = [50 => 59, 159 => 168];

        // Act
        $resultado = $this->invocarMetodoProtegido(
            'formatTextUsingMatchPositions',
            [$posiciones, $texto, 260]
        );

        // Assert
        $this->assertStringContainsString('<strong>MATCH_UNO</strong>', $resultado);
        $this->assertStringContainsString('...', $resultado);
        $this->assertStringContainsString('<strong>MATCH_DOS</strong>', $resultado);
    }

    // =========================================================================
    // UT-SRC-020 — Límite de 25 posiciones por rendimiento
    // Técnica: Análisis de valores límite (límite duro interno = 25)
    // Requisito: RF-SRC-05
    // =========================================================================

    /** @test */
    public function limite_de_deteccion_de_posiciones_en_25_ocurrencias(): void
    {
        // Arrange
        $texto = str_repeat('cat ', 100);
        $terminos = ['cat'];

        // Act
        $posiciones = $this->invocarMetodoProtegido('getMatchPositions', [$texto, $terminos]);

        // Assert
        $this->assertLessThanOrEqual(
            25,
            count($posiciones),
            'No debe detectar más de 25 coincidencias por motivos de rendimiento'
        );
    }

    // =========================================================================
    // Helper: Invocar método protegido vía reflexión
    // =========================================================================

    private function invocarMetodoProtegido(string $nombreMetodo, array $parametros): mixed
    {
        $reflection = new \ReflectionMethod(SearchResultsFormatter::class, $nombreMetodo);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->formatter, ...$parametros);
    }
}
