<?php
# Pruebas en PageRepo no PageService 
namespace Tests\Unit;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Exports\ExportFormatter;
use Tests\TestCase;
use Throwable;

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

    /**
     * UT-PG-06
     * Restaurar revisión inexistente no modifica la página.
     *
     * Este caso valida un escenario negativo:
     * si se intenta restaurar una revisión que no existe,
     * el contenido actual de la página no debe quedar alterado.
     */
    public function test_ut_pg_06_restaurar_revision_inexistente_no_modifica_la_pagina(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página protegida',
            'html' => '<p>Contenido vigente</p>',
        ]);

        $page->refresh();

        $originalName = $page->name;
        $originalText = $page->text;
        $originalRevisionCount = $page->revision_count;

        $revisionInexistente = 99999999;

        try {
            $pageRepo->restoreRevision($page, $revisionInexistente);

            $this->fail('Se esperaba una excepción al intentar restaurar una revisión inexistente.');
        } catch (Throwable $exception) {
            $page->refresh();

            $this->assertSame($originalName, $page->name);
            $this->assertSame($originalText, $page->text);
            $this->assertSame($originalRevisionCount, $page->revision_count);

            $this->assertDatabaseMissing('page_revisions', [
                'page_id' => $page->id,
                'revision_number' => $originalRevisionCount + 1,
            ]);
        }
    }

    /**
     * UT-PG-07
     * Exportar página con contenido potencialmente inseguro.
     *
     * Este caso valida que la exportación a texto plano no incluya
     * etiquetas HTML ni scripts ejecutables.
     */
    public function test_ut_pg_07_exportar_pagina_con_contenido_inseguro_no_expone_script_ejecutable(): void
    {
        $this->asEditor();

        $page = $this->entities->newPage([
            'name' => 'Página con contenido inseguro',
            'html' => '<p>Texto válido</p><script>alert(1)</script>',
        ]);

        $plainText = app(ExportFormatter::class)->pageToPlainText($page->refresh());

        $this->assertStringContainsString('Página con contenido inseguro', $plainText);
        $this->assertStringContainsString('Texto válido', $plainText);

        $this->assertStringNotContainsString('<script>', $plainText);
        $this->assertStringNotContainsString('</script>', $plainText);
        $this->assertStringNotContainsString('alert(1)', $plainText);
    }

    /**
     * UT-PG-08
     * Página publicada aparece como contenido publicado.
     * UT-PG-08 valida el flujo positivo: una página publicada sí aparece.
     */
    public function test_ut_pg_08_pagina_publicada_aparece_como_contenido_publicado(): void
    {
        $this->asEditor();

        $book = $this->entities->book();
        $pageRepo = app(PageRepo::class);

        $draft = $pageRepo->getNewDraftPage($book);

        $page = $pageRepo->publishDraft($draft, [
            'name' => 'Página publicada visible',
            'html' => '<p>Contenido publicado</p>',
        ]);

        $page->refresh();

        $publishedPages = $book->pages()
            ->where('draft', false)
            ->pluck('id')
            ->all();

        $this->assertFalse($page->draft);
        $this->assertSame($book->id, $page->book_id);
        $this->assertContains($page->id, $publishedPages);

        $this->assertDatabaseHas('entity_page_data', [
            'page_id' => $page->id,
            'draft' => false,
            'text' => 'Contenido publicado',
        ]);
    }
     /**
     * UT-PG-09
     * Borrador de un usuario no visible como página publicada para otro usuario.
     */
    public function test_ut_pg_09_borrador_de_un_usuario_no_visible_como_pagina_publicada_para_otro_usuario(): void
    {
        $creator = $this->users->editor();
        $otherUser = $this->users->editor();

        $this->actingAs($creator);

        $book = $this->entities->book();
        $pageRepo = app(PageRepo::class);

        $draft = $pageRepo->getNewDraftPage($book);
        $draft->name = 'Borrador privado de usuario';
        $draft->save();
        $draft->refresh();

        $publishedPageIdsForBook = $book->pages()
            ->where('draft', false)
            ->pluck('id')
            ->all();

        $this->actingAs($otherUser);

        $otherUserPublishedPageIdsForBook = $book->pages()
            ->where('draft', false)
            ->pluck('id')
            ->all();

        $this->assertTrue($draft->draft);
        $this->assertSame($book->id, $draft->book_id);
        $this->assertSame($creator->id, $draft->created_by);

        $this->assertNotContains($draft->id, $publishedPageIdsForBook);
        $this->assertNotContains($draft->id, $otherUserPublishedPageIdsForBook);

        $this->assertDatabaseHas('entity_page_data', [
            'page_id' => $draft->id,
            'draft' => true,
        ]);
    }

    /**
     * UT-PG-10
     * Actualizar página con contenido HTML vacío válido.
     * Riesgo cubierto:
     * El sistema podría fallar o conservar contenido antiguo cuando el usuario limpia
     * completamente el contenido de una página.
     */
    public function test_ut_pg_10_actualizar_pagina_con_html_vacio_valido(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con contenido para limpiar',
            'html' => '<p>Contenido que será eliminado</p>',
        ]);

        $page->refresh();

        $this->assertSame('Contenido que será eliminado', $page->text);
        $this->assertSame(1, $page->revision_count);

        $page = $pageRepo->update($page, [
            'name' => 'Página con contenido vacío',
            'html' => '',
            'summary' => 'Se limpia el contenido de la página',
        ]);

        $page->refresh();

        $this->assertSame('Página con contenido vacío', $page->name);
        $this->assertSame('', $page->html);
        $this->assertSame('', $page->text);
        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'name' => 'Página con contenido vacío',
            'text' => '',
            'summary' => 'Se limpia el contenido de la página',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }

    /**
     * UT-PG-11
     * Actualizar página con caracteres especiales.
     * Riesgo cubierto:
     * Pérdida, corrupción o escape incorrecto de caracteres especiales.
     */
    public function test_ut_pg_11_actualizar_pagina_con_caracteres_especiales(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con contenido normal',
            'html' => '<p>Contenido inicial</p>',
        ]);

        $htmlEspecial = '<p>Texto con áéíóú ñ Ñ &amp; &lt; &gt; &quot; &#039;</p>';

        $page = $pageRepo->update($page, [
            'name' => 'Página con caracteres especiales',
            'html' => $htmlEspecial,
            'summary' => 'Actualización con caracteres especiales',
        ]);

        $page->refresh();

        $this->assertSame('Página con caracteres especiales', $page->name);

        $this->assertStringContainsString('Texto con áéíóú ñ Ñ', $page->html);
        $this->assertStringContainsString('&amp;', $page->html);
        $this->assertStringContainsString('&lt;', $page->html);
        $this->assertStringContainsString('&gt;', $page->html);

        $this->assertStringContainsString('Texto con áéíóú ñ Ñ', $page->text);
        $this->assertStringContainsString('&', $page->text);
        $this->assertStringContainsString('<', $page->text);
        $this->assertStringContainsString('>', $page->text);

        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'name' => 'Página con caracteres especiales',
            'summary' => 'Actualización con caracteres especiales',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }
        /**
     * UT-PG-12
     * Actualizar página con HTML vacío válido.
     */
    public function test_ut_pg_12_actualizar_pagina_con_html_vacio_valido(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con contenido para limpiar',
            'html' => '<p>Contenido que será eliminado</p>',
        ]);

        $page->refresh();

        $this->assertSame('Contenido que será eliminado', $page->text);
        $this->assertSame(1, $page->revision_count);

        $page = $pageRepo->update($page, [
            'name' => 'Página con contenido vacío',
            'html' => '',
            'summary' => 'Se limpia el contenido de la página',
        ]);

        $page->refresh();

        $this->assertSame('Página con contenido vacío', $page->name);
        $this->assertSame('', $page->html);
        $this->assertSame('', $page->text);
        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'name' => 'Página con contenido vacío',
            'text' => '',
            'summary' => 'Se limpia el contenido de la página',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }

    /**
     * UT-PG-13
     * Usuario sin permisos no debe actualizar página.
     */
    public function test_ut_pg_13_usuario_sin_permisos_no_debe_actualizar_pagina(): void
    {
        $admin = $this->users->admin();
        $viewer = $this->users->viewer();

        $this->actingAs($admin);

        $page = $this->entities->newPage([
            'name' => 'Página protegida',
            'html' => '<p>Contenido protegido</p>',
        ]);

        $page->refresh();

        $originalName = $page->name;
        $originalText = $page->text;
        $originalRevisionCount = $page->revision_count;

        $this->actingAs($viewer);

        $canUpdate = userCan('page-update', $page);

        if ($canUpdate) {
            $this->fail('El usuario viewer no debería tener permiso para actualizar la página.');
        }

        $page->refresh();

        $this->assertFalse($canUpdate);
        $this->assertSame($originalName, $page->name);
        $this->assertSame($originalText, $page->text);
        $this->assertSame($originalRevisionCount, $page->revision_count);

        $this->assertDatabaseMissing('page_revisions', [
            'page_id' => $page->id,
            'revision_number' => $originalRevisionCount + 1,
        ]);
    }

        /**
     * UT-PG-14
     * Publicar actualización elimina borradores temporales.
     */
    public function test_ut_pg_14_publicar_actualizacion_elimina_borradores_temporales(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con borrador temporal',
            'html' => '<p>Contenido original</p>',
        ]);

        $page->refresh();

        $draftRevision = $pageRepo->updatePageDraft($page, [
            'name' => 'Borrador temporal de edición',
            'html' => '<p>Contenido temporal</p>',
        ]);

        $draftRevision->refresh();

        $this->assertInstanceOf(PageRevision::class, $draftRevision);
        $this->assertSame($page->id, $draftRevision->page_id);
        $this->assertSame('update_draft', $draftRevision->type);
        $this->assertSame('Borrador temporal de edición', $draftRevision->name);
        $this->assertSame('<p>Contenido temporal</p>', $draftRevision->html);

        $this->assertDatabaseHas('page_revisions', [
            'id' => $draftRevision->id,
            'page_id' => $page->id,
            'type' => 'update_draft',
            'name' => 'Borrador temporal de edición',
            'html' => '<p>Contenido temporal</p>',
        ]);

        $page = $pageRepo->update($page, [
            'name' => 'Página actualizada oficialmente',
            'html' => '<p>Contenido oficial</p>',
            'summary' => 'Actualización oficial',
        ]);

        $page->refresh();

        $this->assertSame('Página actualizada oficialmente', $page->name);
        $this->assertSame('Contenido oficial', $page->text);
        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseMissing('page_revisions', [
            'id' => $draftRevision->id,
            'type' => 'update_draft',
        ]);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'name' => 'Página actualizada oficialmente',
            'text' => 'Contenido oficial',
            'summary' => 'Actualización oficial',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }

    /**
     * UT-PG-15
     * Actualización con resumen registra trazabilidad.
     */
    public function test_ut_pg_15_actualizacion_con_resumen_registra_trazabilidad(): void
    {
        $this->asEditor();

        $pageRepo = app(PageRepo::class);

        $page = $this->entities->newPage([
            'name' => 'Página con resumen',
            'html' => '<p>Contenido inicial</p>',
        ]);

        $summary = 'Resumen de cambio para trazabilidad';

        $page = $pageRepo->update($page, [
            'name' => 'Página con resumen actualizado',
            'html' => '<p>Contenido actualizado con resumen</p>',
            'summary' => $summary,
        ]);

        $page->refresh();

        $this->assertSame('Página con resumen actualizado', $page->name);
        $this->assertSame('Contenido actualizado con resumen', $page->text);
        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'summary' => $summary,
            'text' => 'Contenido actualizado con resumen',
        ]);

        $this->assertActivityExists(ActivityType::PAGE_UPDATE, $page);
    }
}