<?php

namespace Tests\Unit\Search;

use BookStack\Search\SearchOptions;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pruebas unitarias para SearchOptions — Parseo de cadenas de búsqueda.
 *
 * Clase bajo prueba: BookStack\Search\SearchOptions
 * Ubicación: app/Search/SearchOptions.php
 *
 * Regla aplicada:
 * - Dependencia 0 de usuarios seed.
 * - No usar $this->users.
 * - No usar $this->entities.
 * - No usar $this->asEditor().
 *
 * @group sprint-2
 * @group search
 * @group search-options
 */
class SearchOptionsParsingTest extends TestCase
{
    use DatabaseTransactions;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'Search Options ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'search-options-' . $roleName . '-' . uniqid() . '@example.com',
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function actAsRole(string $roleName): User
    {
        $user = $this->userWithRole($roleName);

        $this->actingAs($user);

        return $user;
    }

    /**
     * UT-SRC-007
     * Parseo de términos estándar separados por espacio.
     */
    public function test_parsea_terminos_estandar_separados_por_espacio(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('laravel php testing');

        $this->assertSame(
            ['laravel', 'php', 'testing'],
            $opciones->searches->toValueArray()
        );
    }

    /**
     * UT-SRC-008
     * Parseo de coincidencia exacta con comillas dobles.
     */
    public function test_parsea_coincidencia_exacta_entre_comillas(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('cat "dog house" bird');

        $this->assertSame(['cat', 'bird'], $opciones->searches->toValueArray());
        $this->assertSame(['dog house'], $opciones->exacts->toValueArray());
    }

    /**
     * UT-SRC-009
     * Parseo de tags con corchetes.
     */
    public function test_parsea_sintaxis_de_tags_con_corchetes(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('test [category=php] [priority]');

        $this->assertSame(['test'], $opciones->searches->toValueArray());

        $this->assertSame(
            ['category=php', 'priority'],
            $opciones->tags->toValueArray()
        );
    }

    /**
     * UT-SRC-010
     * Parseo de filtros con llaves.
     */
    public function test_parsea_filtros_clave_valor_entre_llaves(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('{is_template} {created_by:admin} {sort_by:last_commented}');

        $filtros = $opciones->filters->toValueMap();

        $this->assertArrayHasKey('is_template', $filtros);
        $this->assertArrayHasKey('created_by', $filtros);
        $this->assertArrayHasKey('sort_by', $filtros);

        $this->assertSame('', $filtros['is_template']);
        $this->assertSame('admin', $filtros['created_by']);
        $this->assertSame('last_commented', $filtros['sort_by']);
    }

    /**
     * UT-SRC-011
     * Prefijo de negación en exacts, tags y filtros.
     */
    public function test_prefijo_negacion_establece_flag_en_opciones(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('cat -"dog" -[bad_tag] -{is_template}');

        $this->assertCount(1, $opciones->searches->all());
        $this->assertSame(['cat'], $opciones->searches->toValueArray());

        $this->assertCount(1, $opciones->exacts->all());
        $this->assertCount(1, $opciones->tags->all());
        $this->assertCount(1, $opciones->filters->all());

        $this->assertTrue(
            $opciones->exacts->all()[0]->negated,
            'Exact con prefijo - debe estar negado.'
        );

        $this->assertTrue(
            $opciones->tags->all()[0]->negated,
            'Tag con prefijo - debe estar negado.'
        );

        $this->assertTrue(
            $opciones->filters->all()[0]->negated,
            'Filtro con prefijo - debe estar negado.'
        );
    }

    /**
     * UT-SRC-012
     * Rate limiting: invitado vs autenticado.
     */
    public function test_rate_limiting_diferente_para_guest_vs_autenticado(): void
    {
        $muchosTerminos = implode(' ', array_map(
            fn (int $index) => 'termino' . $index,
            range(1, 15)
        ));

        auth()->logout();

        $opcionesGuest = SearchOptions::fromString($muchosTerminos);

        $this->assertCount(
            5,
            $opcionesGuest->searches->all(),
            'Usuarios invitados deben estar limitados a 5 términos.'
        );

        $this->assertSame(
            ['termino1', 'termino2', 'termino3', 'termino4', 'termino5'],
            $opcionesGuest->searches->toValueArray()
        );

        $this->actAsRole('editor');

        $opcionesAuth = SearchOptions::fromString($muchosTerminos);

        $this->assertCount(
            10,
            $opcionesAuth->searches->all(),
            'Usuarios autenticados deben estar limitados a 10 términos.'
        );

        $this->assertSame(
            [
                'termino1',
                'termino2',
                'termino3',
                'termino4',
                'termino5',
                'termino6',
                'termino7',
                'termino8',
                'termino9',
                'termino10',
            ],
            $opcionesAuth->searches->toValueArray()
        );
    }

    /**
     * UT-SRC-013
     * Serialización inversa de opciones a string.
     */
    public function test_to_string_serializa_busqueda_con_terminos_exactos_tags_y_filtros(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('cat "dog house" [category=php] {sort_by:name}');

        $resultado = $opciones->toString();

        $this->assertStringContainsString('cat', $resultado);
        $this->assertStringContainsString('"dog house"', $resultado);
        $this->assertStringContainsString('[category=php]', $resultado);
        $this->assertStringContainsString('{sort_by:name}', $resultado);
    }

    /**
     * UT-SRC-014
     * Serialización conserva negaciones.
     */
    public function test_to_string_conserva_negaciones_en_exactos_tags_y_filtros(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('-"privado" -[estado=borrador] -{is_template}');

        $resultado = $opciones->toString();

        $this->assertStringContainsString('-"privado"', $resultado);
        $this->assertStringContainsString('-[estado=borrador]', $resultado);
        $this->assertStringContainsString('-{is_template}', $resultado);
    }

    /**
     * UT-SRC-015
     * Entrada vacía no genera opciones de búsqueda.
     */
    public function test_entrada_vacia_no_genera_opciones_de_busqueda(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('');

        $this->assertCount(0, $opciones->searches->all());
        $this->assertCount(0, $opciones->exacts->all());
        $this->assertCount(0, $opciones->tags->all());
        $this->assertCount(0, $opciones->filters->all());
    }

    /**
     * UT-SRC-016
     * Espacios repetidos son ignorados.
     */
    public function test_espacios_repetidos_son_ignorados_al_parsear_terminos(): void
    {
        $this->actAsRole('editor');

        $opciones = SearchOptions::fromString('   laravel    php     testing   ');

        $this->assertSame(
            ['laravel', 'php', 'testing'],
            $opciones->searches->toValueArray()
        );
    }
}