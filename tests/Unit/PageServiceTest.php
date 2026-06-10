<?php
# Pruebas en PageRepo no PageService 
namespace Tests\Unit;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Exports\ExportFormatter;
use Tests\TestCase;

class PageServiceTest extends TestCase
{
    /**
     * UT-PG-01
     * Crear página con contenido HTML válido genera revisión inicial.
     */
    public function test_ut_pg_01_crear_pagina_con_html_valido_genera_revision_inicial(): void
    {
        $this->asEditor();

        $chapter = $this->entities->chapter();
        $pageRepo = app(PageRepo::class);

        $draft = $pageRepo->getNewDraftPage($chapter);

        $this->assertTrue($draft->draft);
        $this->assertSame($chapter->id, $draft->chapter_id);
        $this->assertSame($chapter->book_id, $draft->book_id);

        $page = $pageRepo->publishDraft($draft, [
            'name' => 'Página de prueba unitaria',
            'html' => '<p>Contenido inicial</p>',
        ]);

        $page->refresh();

        $this->assertFalse($page->draft);
        $this->assertSame('Página de prueba unitaria', $page->name);

        // BookStack procesa el HTML y agrega IDs internos tipo bkmrk-...
        // Por eso no comparamos el HTML exacto.
        $this->assertStringContainsString('Contenido inicial', $page->html);
        $this->assertStringContainsString('bkmrk-contenido-inicial', $page->html);

        $this->assertSame('Contenido inicial', $page->text);
        $this->assertSame(1, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 1,
            'text' => 'Contenido inicial',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_CREATE, $page);
    }

    /**
     * UT-PG-02
     * Actualizar página crea revisión histórica.
     */
    public function test_ut_pg_02_actualizar_pagina_crea_revision_historica(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con versión inicial',
            'html' => '<p>Versión 1</p>',
        ]);

        $this->assertSame(1, $page->revision_count);

        $page = $pageRepo->update($page, [
            'name' => 'Página actualizada',
            'html' => '<p>Versión 2</p>',
            'summary' => 'Actualización de contenido',
        ]);

        $page->refresh();

        $this->assertSame('Página actualizada', $page->name);

        // BookStack normaliza el HTML y agrega IDs internos.
        $this->assertStringContainsString('Versión 2', $page->html);
        $this->assertStringContainsString('bkmrk-versi%C3%B3n-2', $page->html);

        $this->assertSame('Versión 2', $page->text);
        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 1,
            'name' => 'Página con versión inicial',
            'text' => 'Versión 1',
        ]);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'name' => 'Página actualizada',
            'text' => 'Versión 2',
            'summary' => 'Actualización de contenido',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }

    /**
     * UT-PG-03
     * Restaurar revisión anterior vuelve al estado previo.
     */
    public function test_ut_pg_03_restaurar_revision_anterior_vuelve_al_estado_previo(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página original',
            'html' => '<p>Contenido original</p>',
        ]);

        $page = $pageRepo->update($page, [
            'name' => 'Página modificada',
            'html' => '<p>Contenido modificado</p>',
            'summary' => 'Cambio temporal',
        ]);

        $page->refresh();

        $revisionOriginal = PageRevision::query()
            ->where('page_id', $page->id)
            ->where('revision_number', 1)
            ->firstOrFail();

        $pageRepo->restoreRevision($page, $revisionOriginal->id);

        $page->refresh();

        $this->assertSame('Página original', $page->name);

        $this->assertStringContainsString('Contenido original', $page->html);
        $this->assertStringContainsString('bkmrk-contenido-original', $page->html);

        $this->assertSame('Contenido original', $page->text);
        $this->assertSame(3, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 3,
            'text' => 'Contenido original',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_RESTORE, $page);
    }

    /**
     * UT-PG-04
     * Exportar página a texto plano elimina etiquetas HTML.
     */
    public function test_ut_pg_04_exportar_pagina_a_texto_plano_elimina_etiquetas_html(): void
    {
        $this->asEditor();

        $page = $this->entities->newPage([
            'name' => 'Página para exportar',
            'html' => '<p>Hola <strong>mundo</strong></p>',
        ]);

        $plainText = app(ExportFormatter::class)->pageToPlainText($page->refresh());

        $this->assertStringContainsString('Página para exportar', $plainText);
        $this->assertStringContainsString('Hola mundo', $plainText);

        $this->assertStringNotContainsString('<p>', $plainText);
        $this->assertStringNotContainsString('</p>', $plainText);
        $this->assertStringNotContainsString('<strong>', $plainText);
        $this->assertStringNotContainsString('</strong>', $plainText);
    }

    /**
     * UT-PG-05
     * Página en borrador no visible como página publicada.
     */
    public function test_ut_pg_05_pagina_en_borrador_no_visible_como_pagina_publicada(): void
    {
        $this->asEditor();

        $book = $this->entities->book();
        $pageRepo = app(PageRepo::class);

        $draft = $pageRepo->getNewDraftPage($book);
        $draft->refresh();

        $this->assertTrue($draft->draft);
        $this->assertSame($book->id, $draft->book_id);

        $publishedPages = $book->pages()
            ->where('draft', false)
            ->pluck('id')
            ->all();

        $this->assertNotContains($draft->id, $publishedPages);

        $this->assertDatabaseHas('entity_page_data', [
            'page_id' => $draft->id,
            'draft' => true,
        ]);
    }
}