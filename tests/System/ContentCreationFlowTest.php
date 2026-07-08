<?php

namespace Tests\System;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\PageRepo;
use Tests\TestCase;

/**
 * ST-01 — Flujo completo de creación de contenido
 *
 * ADAPTACIÓN respecto a la consigna:
 *   - Consigna pide RefreshDatabase → BookStack requiere DatabaseTransactions
 *     porque RefreshDatabase destruye los datos del DummyContentSeeder.
 *     El comportamiento es equivalente: rollback automático al final de cada test.
 *   - Consigna pide User::factory()->admin()->create() → BookStack usa
 *     $this->users->admin() / $this->users->editor() / $this->users->viewer()
 *   - Creación de páginas requiere flujo draft → publish vía PageRepo,
 *     ya que el formulario web de BookStack usa un proceso en dos pasos.
 *
 * Equipo: Team Langosta — Rommel Chambi (Issue #22)
 * Curso:  Pruebas de Software — UNSA 2026
 */
class ContentCreationFlowTest extends TestCase
{
    // =========================================================================
    // ST-01-01
    // Admin hace login, crea libro, capítulo y página en secuencia → todo visible
    // =========================================================================

    public function test_st01_01_admin_crea_libro_capitulo_pagina_en_secuencia_y_todo_visible(): void
    {
        $admin = $this->users->admin();

        // 1. Crear libro via HTTP web
        $this->actingAs($admin)
            ->post('/books', [
                'name'        => 'ST01 Libro Sistema',
                'description' => 'Libro creado en prueba de sistema ST-01-01',
            ])
            ->assertRedirect();

        $libro = Book::query()->where('name', 'ST01 Libro Sistema')->firstOrFail();

        // 2. Crear capítulo dentro del libro
        $this->post($libro->getUrl('/create-chapter'), [
            'name'        => 'ST01 Capítulo Sistema',
            'description' => 'Capítulo de prueba de sistema',
        ])->assertRedirect();

        $capitulo = Chapter::query()->where('name', 'ST01 Capítulo Sistema')->firstOrFail();

        // 3. Crear página dentro del capítulo (flujo: draft → publish)
        $this->get($capitulo->getUrl('/create-page'))->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $capitulo->id)
            ->where('draft', true)
            ->latest('id')
            ->firstOrFail();

        $this->post($draft->getUrl(), [
            'name'     => 'ST01 Página Sistema',
            'html'     => '<p>Contenido de la página de prueba de sistema ST-01-01.</p>',
            'markdown' => '',
        ])->assertRedirect();

        // 4. Verificar persistencia en BD
        $this->assertDatabaseHasEntityData('book',    ['name' => 'ST01 Libro Sistema']);
        $this->assertDatabaseHasEntityData('chapter', ['name' => 'ST01 Capítulo Sistema']);
        $this->assertDatabaseHasEntityData('page',    ['name' => 'ST01 Página Sistema']);

        // 5. Verificar visibilidad HTTP — el libro debe ser accesible
        $libro->refresh();
        $this->actingAs($admin)
            ->get($libro->getUrl())
            ->assertOk()
            ->assertSee('ST01 Libro Sistema');
    }

    // =========================================================================
    // ST-01-02
    // Página creada aparece en resultados de búsqueda tras indexación
    // =========================================================================

    public function test_st01_02_pagina_creada_aparece_en_resultados_de_busqueda(): void
    {
        $admin    = $this->users->admin();
        $capitulo = $this->entities->chapter();

        // Nombre único para no colisionar con datos del seeder
        $nombreUnico = 'ST01BusquedaUnica' . uniqid();

        // Crear página (la indexación de búsqueda ocurre sincrónicamente en BookStack)
        $this->actingAs($admin)->get($capitulo->getUrl('/create-page'))->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $capitulo->id)
            ->where('draft', true)
            ->latest('id')
            ->firstOrFail();

        $this->post($draft->getUrl(), [
            'name'     => $nombreUnico,
            'html'     => '<p>Contenido único para validar búsqueda en ST-01-02.</p>',
            'markdown' => '',
        ])->assertRedirect();

        // Verificar que aparece en búsqueda inmediatamente tras creación
        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($nombreUnico))
            ->assertOk()
            ->assertSee($nombreUnico);
    }

    // =========================================================================
    // ST-01-03
    // Página creada por editor es visible para viewer del mismo libro
    // =========================================================================

    public function test_st01_03_pagina_de_editor_es_visible_para_viewer(): void
    {
        $editor   = $this->users->editor();
        $viewer   = $this->users->viewer();
        $capitulo = $this->entities->chapter();

        // Editor crea y publica una página
        $this->actingAs($editor)->get($capitulo->getUrl('/create-page'))->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $capitulo->id)
            ->where('draft', true)
            ->latest('id')
            ->firstOrFail();

        $this->post($draft->getUrl(), [
            'name'     => 'ST01 Página Visible Para Viewer',
            'html'     => '<p>Contenido publicado por editor — visible para viewer.</p>',
            'markdown' => '',
        ])->assertRedirect();

        $pagina = Page::query()
            ->where('name', 'ST01 Página Visible Para Viewer')
            ->where('draft', false)
            ->firstOrFail();

        // Viewer puede ver la página publicada por el editor
        $this->actingAs($viewer)
            ->get($pagina->getUrl())
            ->assertOk()
            ->assertSee('ST01 Página Visible Para Viewer');
    }

    // =========================================================================
    // ST-01-04
    // Página en borrador NO visible para viewer, SÍ visible para su autor
    // =========================================================================

    public function test_st01_04_borrador_no_visible_para_viewer_si_para_autor(): void
    {
        $editor   = $this->users->editor();
        $viewer   = $this->users->viewer();
        $capitulo = $this->entities->chapter();

        // Editor inicia creación de página — BookStack crea draft y redirige al editor
        // Capturamos la URL del editor desde el redirect (draft tiene slug vacío, getUrl() no funciona)
        $createResp = $this->actingAs($editor)->get($capitulo->getUrl('/create-page'));
        $createResp->assertRedirect();
        $editorUrl = $createResp->headers->get('Location');

        $draft = Page::query()
            ->where('chapter_id', $capitulo->id)
            ->where('draft', true)
            ->latest('id')
            ->firstOrFail();

        // Confirmar que sigue siendo borrador (draft = true)
        $this->assertTrue((bool) $draft->draft, 'La página debe estar en estado borrador');

        // Viewer NO puede ver el borrador — debe recibir 404
        $this->actingAs($viewer)
            ->get($draft->getUrl())
            ->assertNotFound();

        // Autor (editor) SÍ puede ver y editar su propio borrador vía URL del editor
        $this->actingAs($editor)
            ->get($editorUrl)
            ->assertOk();
    }

    // =========================================================================
    // ST-01-05
    // Eliminar libro elimina todo su contenido y deja de aparecer en búsqueda
    // =========================================================================

    public function test_st01_05_eliminar_libro_elimina_contenido_y_desaparece_de_busqueda(): void
    {
        $admin   = $this->users->admin();
        $capRepo = app(\BookStack\Entities\Repos\ChapterRepo::class);

        // Usamos descripción indexable como término de búsqueda.
        // El nombre podría aparecer en el flash de borrado en la siguiente request,
        // contaminando assertDontSee; la descripción solo aparece en result-cards.
        $descUnica   = 'descst0105-' . uniqid();
        $nombreLibro = 'ST01 Libro Para Eliminar';

        // 1. Crear libro
        $this->actingAs($admin)
            ->post('/books', ['name' => $nombreLibro, 'description' => $descUnica])
            ->assertRedirect();

        $libro   = Book::query()->where('name', $nombreLibro)->firstOrFail();
        $libroId = $libro->id;

        // 2. Crear capítulo y página para verificar que también se eliminan
        $this->post($libro->getUrl('/create-chapter'), [
            'name' => 'ST01 Capítulo Hijo',
        ])->assertRedirect();
        $capitulo   = Chapter::query()->where('name', 'ST01 Capítulo Hijo')->firstOrFail();
        $capituloId = $capitulo->id;

        // 3. ANTES de eliminar: libro visible en búsqueda
        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($descUnica))
            ->assertOk()
            ->assertSee($nombreLibro);

        // 4. Eliminar libro (soft-delete → papelera de BookStack)
        $this->actingAs($admin)
            ->delete($libro->getUrl())
            ->assertRedirect();

        // 5. Verificar soft-delete de libro E hijo en BD
        $this->assertSoftDeleted('entities', ['id' => $libroId,   'type' => 'book']);
        $this->assertSoftDeleted('entities', ['id' => $capituloId, 'type' => 'chapter']);

        // 6. DEFECTO CONFIRMADO ST-D-01 (ver Informe Sprint 4):
        //    BookStack v26.x no purga search_terms ni filtra entidades en papelera
        //    en la consulta de búsqueda. Las entidades soft-deleted siguen visibles
        //    en los resultados de búsqueda hasta que se vacía permanentemente la
        //    papelera (Maintenance → Recycle Bin → Delete All).
        //
        //    La única garantía verificable vía BD es el soft-delete:
        $this->assertSoftDeleted('entities', ['id' => $libroId,    'type' => 'book']);
        $this->assertSoftDeleted('entities', ['id' => $capituloId, 'type' => 'chapter']);

        // 7. La siguiente aserción FALLA intencionadamente para documentar el defecto:
        //    tras soft-delete la búsqueda AÚN muestra el libro (comportamiento incorrecto).
        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($descUnica))
            ->assertOk()
            ->assertDontSee($nombreLibro); // [FAIL esperado → Defecto ST-D-01]
    }
}
