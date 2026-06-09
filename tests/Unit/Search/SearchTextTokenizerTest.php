<?php

namespace Tests\Unit\Search;

use BookStack\Search\SearchIndex;
use BookStack\Search\SearchTextTokenizer;
use Tests\TestCase;

/**
 * Pruebas unitarias para SearchTextTokenizer.
 *
 * Clase bajo prueba: BookStack\Search\SearchTextTokenizer
 * Ubicación: app/Search/SearchTextTokenizer.php
 * Issue: #14 (y complementos)
 * Sprint: 2
 * Responsable: Ower Frank Lopez Arela (Test Designer)
 *
 * Cobertura objetivo:
 *   - next(): producción de tokens, retorno de false al finalizar
 *   - currentDelimiter() / previousDelimiter(): rastreo de estado
 *   - Comportamiento con delimitadores duros vs blandos
 *   - Manejo de entradas vacías y edge cases
 *
 * @group sprint-2
 * @group search
 * @group tokenizer
 */
class SearchTextTokenizerTest extends TestCase
{
    // =========================================================================
    // UT-SRC-001 — Tokenización básica con espacio como delimitador
    // Técnica: Partición de equivalencia (clase válida: palabras separadas)
    // Requisito: RF-SRC-01
    // =========================================================================

    /** @test */
    public function tokenizacion_basica_con_espacio(): void
    {
        // Arrange
        $tokenizer = new SearchTextTokenizer('hello world test', ' ');

        // Act
        $tokens = $this->consumirTodosLosTokens($tokenizer);

        // Assert
        $this->assertEquals(['hello', 'world', 'test'], $tokens);
    }

    // =========================================================================
    // UT-SRC-002 — Delimitadores duros con rastreo de estado
    // Técnica: Prueba de transición de estado (iterador con estado interno)
    // Requisito: RF-SRC-01
    // =========================================================================

    /** @test */
    public function delimitadores_duros_con_rastreo_de_estado(): void
    {
        // Arrange
        $tokenizer = new SearchTextTokenizer('hello,world!test', ',!');

        // Act & Assert — Token 1
        $this->assertEquals('hello', $tokenizer->next());
        $this->assertEquals(',', $tokenizer->currentDelimiter());

        // Token 2 — verificar previousDelimiter se actualiza
        $this->assertEquals('world', $tokenizer->next());
        $this->assertEquals(',', $tokenizer->previousDelimiter());
        $this->assertEquals('!', $tokenizer->currentDelimiter());

        // Token 3
        $this->assertEquals('test', $tokenizer->next());

        // Fin del stream
        $this->assertFalse($tokenizer->next());
    }

    // =========================================================================
    // UT-SRC-003 — Token vacío entre delimitadores consecutivos
    // Técnica: Análisis de valores límite (longitud de token = 0)
    // Requisito: RF-SRC-01
    // Nota: Comportamiento intencional documentado en docblock de la clase
    // =========================================================================

    /** @test */
    public function token_vacio_entre_delimitadores_consecutivos(): void
    {
        // Arrange
        $tokenizer = new SearchTextTokenizer('a,,b', ',');

        // Act
        $tokens = $this->consumirTodosLosTokens($tokenizer);

        // Assert — El token vacío entre comas se PRESERVA (no se omite)
        $this->assertEquals(['a', '', 'b'], $tokens);
    }

    // =========================================================================
    // UT-SRC-004 — Entrada vacía retorna false inmediatamente
    // Técnica: Análisis de valores límite (entrada de longitud 0)
    // Requisito: RF-SRC-01
    // =========================================================================

    /** @test */
    public function entrada_vacia_retorna_false_inmediatamente(): void
    {
        // Arrange
        $tokenizer = new SearchTextTokenizer('', ' ');

        // Act & Assert — Comparación estricta: false !== ''
        $this->assertFalse($tokenizer->next());
    }

    // =========================================================================
    // UT-SRC-005 — Entrada de un solo carácter produce un único token
    // Técnica: Análisis de valores límite (longitud mínima no vacía = 1)
    // Requisito: RF-SRC-01
    // =========================================================================

    /** @test */
    public function un_solo_caracter_produce_un_token(): void
    {
        // Arrange
        $tokenizer = new SearchTextTokenizer('x', ' ');

        // Act & Assert
        $this->assertEquals('x', $tokenizer->next());
        $this->assertFalse($tokenizer->next());
    }

    // =========================================================================
    // UT-SRC-006 — Set completo de delimitadores de producción
    // Técnica: Tabla de decisión (múltiples delimitadores en una sola pasada)
    // Requisito: RF-SRC-01
    // =========================================================================

    /** @test */
    public function delimitadores_produccion_completos(): void
    {
        // Arrange — Usar el set real de delimitadores de SearchIndex
        $texto = "hello world\ttest,file!end";
        $tokenizer = new SearchTextTokenizer($texto, SearchIndex::$delimiters);

        // Act
        $tokens = $this->consumirTodosLosTokens($tokenizer);

        // Assert — Filtrar tokens vacíos y verificar las palabras reales
        $tokensNoVacios = array_values(array_filter($tokens, fn($t) => $t !== ''));
        $this->assertEquals(
            ['hello', 'world', 'test', 'file', 'end'],
            $tokensNoVacios,
            'El tokenizer con delimitadores de producción debe separar correctamente'
        );
    }

    // =========================================================================
    // Helper: Consumir todos los tokens hasta que next() retorne false
    // =========================================================================

    private function consumirTodosLosTokens(SearchTextTokenizer $tokenizer): array
    {
        $tokens = [];
        while (($token = $tokenizer->next()) !== false) {
            $tokens[] = $token;
        }
        return $tokens;
    }
}
