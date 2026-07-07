<?php

namespace Tests\System;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use Tests\TestCase;

class PageRevisionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->users->admin());
    }

    protected function createPageForRevisionFlow(string $name = 'ST-05 Página revisiones inicial'): Page
    {
        $page = $this->entities->newPage([
            'name' => $name,
            'html' => '<p>Contenido inicial ST-05</p>',
        ]);

        PageRevision::query()
            ->where('page_id', $page->id)
            ->delete();

        return $page->refresh();
    }

    protected function updatePage(Page $page, string $name, string $html, string $summary): void
    {
        $response = $this->put($page->getUrl(), [
            'name' => $name,
            'html' => $html,
            'markdown' => '',
            'summary' => $summary,
        ]);

        $response->assertRedirect();

        $page->refresh();

        $this->assertSame($name, $page->name);

        // BookStack procesa el HTML y agrega ids internos tipo bkmrk-*.
        // Por eso se valida el contenido visible, no el HTML exacto.
        $this->assertStringContainsString(strip_tags($html), $page->html);
    }

    protected function revisionsFor(Page $page)
    {
        return PageRevision::query()
            ->where('page_id', $page->id)
            ->orderBy('id', 'asc')
            ->get();
    }

    protected function revisionUrl(Page $page, PageRevision $revision, string $suffix = ''): string
    {
        return $page->getUrl('/revisions/' . $revision->id . $suffix);
    }

    public function test_st_05_01_editar_pagina_tres_veces_genera_tres_revisiones_distintas(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Página revisión 1', '<p>Contenido revisión uno</p>', 'ST-05 edición 1');
        $this->updatePage($page, 'ST-05 Página revisión 2', '<p>Contenido revisión dos</p>', 'ST-05 edición 2');
        $this->updatePage($page, 'ST-05 Página revisión 3', '<p>Contenido revisión tres</p>', 'ST-05 edición 3');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(3, $revisions);

        $this->assertSame('ST-05 Página revisión 1', $revisions[0]->name);
        $this->assertSame('ST-05 Página revisión 2', $revisions[1]->name);
        $this->assertSame('ST-05 Página revisión 3', $revisions[2]->name);

        $this->assertStringContainsString('Contenido revisión uno', $revisions[0]->html);
        $this->assertStringContainsString('Contenido revisión dos', $revisions[1]->html);
        $this->assertStringContainsString('Contenido revisión tres', $revisions[2]->html);

        $this->assertNotSame($revisions[0]->id, $revisions[1]->id);
        $this->assertNotSame($revisions[1]->id, $revisions[2]->id);
    }

    public function test_st_05_02_historial_muestra_autor_y_fecha_de_cada_cambio(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Historial revisión 1', '<p>Historial contenido uno</p>', 'ST-05 historial 1');
        $this->updatePage($page, 'ST-05 Historial revisión 2', '<p>Historial contenido dos</p>', 'ST-05 historial 2');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(2, $revisions);

        foreach ($revisions as $revision) {
            $this->assertNotNull($revision->created_by);
            $this->assertNotNull($revision->created_at);
        }

        $response = $this->get($page->getUrl('/revisions'));

        $response->assertStatus(200);
        $response->assertSee('ST-05 historial 1');
        $response->assertSee('ST-05 historial 2');
        $response->assertSee($this->users->admin()->name);
    }

    public function test_st_05_03_restaurar_revision_anterior_actualiza_contenido_visible(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Restaurar versión antigua', '<p>Contenido antiguo para restaurar</p>', 'ST-05 versión antigua');
        $this->updatePage($page, 'ST-05 Restaurar versión actual', '<p>Contenido actual antes de restaurar</p>', 'ST-05 versión actual');

        $oldRevision = $this->revisionsFor($page)->first();

        $response = $this->put($this->revisionUrl($page, $oldRevision, '/restore'));

        $response->assertRedirect();

        $page->refresh();

        $this->assertSame('ST-05 Restaurar versión antigua', $page->name);
        $this->assertStringContainsString('Contenido antiguo para restaurar', $page->html);

        $this->get($page->getUrl())
            ->assertStatus(200)
            ->assertSee('Contenido antiguo para restaurar')
            ->assertDontSee('Contenido actual antes de restaurar');
    }

    public function test_st_05_04_restaurar_revision_genera_nueva_entrada_en_historial(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Nueva entrada revisión 1', '<p>Contenido para restaurar luego</p>', 'ST-05 base restaurable');
        $this->updatePage($page, 'ST-05 Nueva entrada revisión 2', '<p>Contenido posterior</p>', 'ST-05 contenido posterior');

        $revisionsBeforeRestore = $this->revisionsFor($page);

        $this->assertCount(2, $revisionsBeforeRestore);

        $revisionToRestore = $revisionsBeforeRestore->first();

        $response = $this->put($this->revisionUrl($page, $revisionToRestore, '/restore'));

        $response->assertRedirect();

        $page->refresh();

        $revisionsAfterRestore = $this->revisionsFor($page);

        $this->assertCount(3, $revisionsAfterRestore);

        $lastRevision = $revisionsAfterRestore->last();

        $this->assertSame('ST-05 Nueva entrada revisión 1', $page->name);
        $this->assertStringContainsString('Contenido para restaurar luego', $page->html);

        $this->assertSame('ST-05 Nueva entrada revisión 1', $lastRevision->name);
        $this->assertStringContainsString('Contenido para restaurar luego', $lastRevision->html);
    }

    public function test_st_05_05_comparar_dos_revisiones_muestra_diferencias(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Comparación revisión uno', '<p>Texto original para comparar</p>', 'ST-05 comparación 1');
        $this->updatePage($page, 'ST-05 Comparación revisión dos', '<p>Texto actualizado para comparar</p>', 'ST-05 comparación 2');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(2, $revisions);

        $firstRevision = $revisions[0];
        $secondRevision = $revisions[1];

        $response = $this->get($this->revisionUrl($page, $secondRevision, '/changes'));

        $response->assertStatus(200);

        $response->assertSee('Texto', false);
        $response->assertSee('para comparar', false);
        $response->assertSee('<del class="diffmod">original</del>', false);
        $response->assertSee('<ins class="diffmod">actualizado</ins>', false);

        $this->assertSame('ST-05 Comparación revisión uno', $firstRevision->name);
        $this->assertSame('ST-05 Comparación revisión dos', $secondRevision->name);
    }

    public function test_st_05_06_ver_revision_individual_muestra_contenido_historico(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Vista revisión antigua', '<p>Contenido histórico visible</p>', 'ST-05 vista antigua');
        $this->updatePage($page, 'ST-05 Vista revisión actual', '<p>Contenido actual visible</p>', 'ST-05 vista actual');

        $firstRevision = $this->revisionsFor($page)->first();

        $response = $this->get($this->revisionUrl($page, $firstRevision));

        $response->assertStatus(200);
        $response->assertSee('Contenido histórico visible');
        $response->assertSee('Revision');
        $response->assertSee('Admin');
    }

    public function test_st_05_07_revisiones_mantienen_orden_de_creacion_en_base_de_datos(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Orden 1', '<p>Orden contenido 1</p>', 'ST-05 orden primero');
        $this->updatePage($page, 'ST-05 Orden 2', '<p>Orden contenido 2</p>', 'ST-05 orden segundo');
        $this->updatePage($page, 'ST-05 Orden 3', '<p>Orden contenido 3</p>', 'ST-05 orden tercero');

        $revisions = $this->revisionsFor($page);

        $this->assertSame([
            'ST-05 orden primero',
            'ST-05 orden segundo',
            'ST-05 orden tercero',
        ], $revisions->pluck('summary')->values()->all());

        $this->assertTrue($revisions[0]->id < $revisions[1]->id);
        $this->assertTrue($revisions[1]->id < $revisions[2]->id);
    }

    public function test_st_05_08_restaurar_revision_intermedia_de_tres_crea_cuarta_revision(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Restore intermedio 1', '<p>Contenido uno intermedio</p>', 'ST-05 intermedio 1');
        $this->updatePage($page, 'ST-05 Restore intermedio 2', '<p>Contenido dos intermedio</p>', 'ST-05 intermedio 2');
        $this->updatePage($page, 'ST-05 Restore intermedio 3', '<p>Contenido tres intermedio</p>', 'ST-05 intermedio 3');

        $revisionToRestore = $this->revisionsFor($page)[1];

        $response = $this->put($this->revisionUrl($page, $revisionToRestore, '/restore'));

        $response->assertRedirect();

        $page->refresh();

        $revisions = $this->revisionsFor($page);

        $this->assertCount(4, $revisions);
        $this->assertSame('ST-05 Restore intermedio 2', $page->name);
        $this->assertStringContainsString('Contenido dos intermedio', $page->html);
        $this->assertSame('ST-05 Restore intermedio 2', $revisions->last()->name);
    }

    public function test_st_05_09_restaurar_revision_actual_mantiene_contenido_y_agrega_historial(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage(
            $page,
            'ST-05 Restore actual 1',
            '<p>Contenido restore actual uno</p>',
            'ST-05 restore actual 1'
        );

        $this->updatePage(
            $page,
            'ST-05 Restore actual 2',
            '<p>Contenido restore actual dos</p>',
            'ST-05 restore actual 2'
        );

        $revisionsBeforeRestore = $this->revisionsFor($page);

        $this->assertCount(2, $revisionsBeforeRestore);

        $latestRevision = $revisionsBeforeRestore->last();

        $response = $this->put(
            $this->revisionUrl($page, $latestRevision, '/restore')
        );

        $response->assertRedirect();

        $page->refresh();

        $revisionsAfterRestore = $this->revisionsFor($page);

        $this->assertCount(3, $revisionsAfterRestore);

        $this->assertSame('ST-05 Restore actual 2', $page->name);
        $this->assertStringContainsString('Contenido restore actual dos', $page->html);

        $this->assertSame('ST-05 Restore actual 2', $revisionsAfterRestore->last()->name);
        $this->assertStringContainsString('Contenido restore actual dos', $revisionsAfterRestore->last()->html);
    }

    public function test_st_05_10_ver_revision_inexistente_retorna_404(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Ver inexistente', '<p>Contenido ver inexistente</p>', 'ST-05 ver inexistente');

        $response = $this->get($page->getUrl('/revisions/999999999'));

        $response->assertNotFound();
    }

    public function test_st_05_11_comparar_revision_inexistente_retorna_404(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Changes inexistente', '<p>Contenido changes inexistente</p>', 'ST-05 changes inexistente');

        $response = $this->get($page->getUrl('/revisions/999999999/changes'));

        $response->assertNotFound();
    }

    public function test_st_05_12_eliminar_revision_actualiza_historial(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Delete 1', '<p>Contenido delete uno</p>', 'ST-05 delete 1');
        $this->updatePage($page, 'ST-05 Delete 2', '<p>Contenido delete dos</p>', 'ST-05 delete 2');
        $this->updatePage($page, 'ST-05 Delete 3', '<p>Contenido delete tres</p>', 'ST-05 delete 3');

        $revisionsBeforeDelete = $this->revisionsFor($page);
        $revisionToDelete = $revisionsBeforeDelete[1];

        $response = $this->delete($this->revisionUrl($page, $revisionToDelete, '/delete'));

        $response->assertRedirect();

        $revisionsAfterDelete = $this->revisionsFor($page);

        $this->assertCount(2, $revisionsAfterDelete);
        $this->assertNull(PageRevision::query()->find($revisionToDelete->id));
        $this->assertSame([
            'ST-05 delete 1',
            'ST-05 delete 3',
        ], $revisionsAfterDelete->pluck('summary')->values()->all());
    }

    public function test_st_05_13_revision_eliminada_ya_no_puede_visualizarse(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Delete view 1', '<p>Contenido delete view uno</p>', 'ST-05 delete view 1');
        $this->updatePage($page, 'ST-05 Delete view 2', '<p>Contenido delete view dos</p>', 'ST-05 delete view 2');

        $revisionToDelete = $this->revisionsFor($page)->first();

        $this->delete($this->revisionUrl($page, $revisionToDelete, '/delete'))
            ->assertRedirect();

        $this->get($this->revisionUrl($page, $revisionToDelete))
            ->assertNotFound();
    }

    public function test_st_05_14_restaurar_revision_restante_despues_de_eliminar_otra_funciona(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Delete restore 1', '<p>Contenido restaurable uno</p>', 'ST-05 delete restore 1');
        $this->updatePage($page, 'ST-05 Delete restore 2', '<p>Contenido eliminado intermedio</p>', 'ST-05 delete restore 2');
        $this->updatePage($page, 'ST-05 Delete restore 3', '<p>Contenido actual final</p>', 'ST-05 delete restore 3');

        $revisions = $this->revisionsFor($page);

        $revisionToRestore = $revisions[0];
        $revisionToDelete = $revisions[1];

        $this->delete($this->revisionUrl($page, $revisionToDelete, '/delete'))
            ->assertRedirect();

        $this->put($this->revisionUrl($page, $revisionToRestore, '/restore'))
            ->assertRedirect();

        $page->refresh();

        $this->assertSame('ST-05 Delete restore 1', $page->name);
        $this->assertStringContainsString('Contenido restaurable uno', $page->html);
        $this->assertCount(3, $this->revisionsFor($page));
    }

    public function test_st_05_15_cambio_solo_de_nombre_queda_registrado_en_revisiones(): void
    {
        $page = $this->createPageForRevisionFlow();

        $sameContent = '<p>Contenido estable con cambio de nombre</p>';

        $this->updatePage($page, 'ST-05 Nombre original', $sameContent, 'ST-05 nombre original');
        $this->updatePage($page, 'ST-05 Nombre actualizado', $sameContent, 'ST-05 nombre actualizado');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(2, $revisions);
        $this->assertSame('ST-05 Nombre original', $revisions[0]->name);
        $this->assertSame('ST-05 Nombre actualizado', $revisions[1]->name);
        $this->assertStringContainsString('Contenido estable con cambio de nombre', $revisions[0]->html);
        $this->assertStringContainsString('Contenido estable con cambio de nombre', $revisions[1]->html);
    }

    public function test_st_05_16_cambio_solo_de_contenido_queda_registrado_en_revisiones(): void
    {
        $page = $this->createPageForRevisionFlow();

        $sameName = 'ST-05 Mismo nombre';

        $this->updatePage($page, $sameName, '<p>Contenido versión alfa</p>', 'ST-05 contenido alfa');
        $this->updatePage($page, $sameName, '<p>Contenido versión beta</p>', 'ST-05 contenido beta');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(2, $revisions);
        $this->assertSame($sameName, $revisions[0]->name);
        $this->assertSame($sameName, $revisions[1]->name);
        $this->assertStringContainsString('Contenido versión alfa', $revisions[0]->html);
        $this->assertStringContainsString('Contenido versión beta', $revisions[1]->html);
    }

    public function test_st_05_17_vista_de_revision_muestra_detalles_de_creacion(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Detalles revisión', '<p>Contenido con detalles de revisión</p>', 'ST-05 detalles revisión');

        $revision = $this->revisionsFor($page)->first();

        $response = $this->get($this->revisionUrl($page, $revision));

        $response->assertStatus(200);
        $response->assertSee('Revision');
        $response->assertSee('Created');
        $response->assertSee('Admin');
        $response->assertSee('Contenido con detalles de revisión');
    }

    public function test_st_05_18_comparacion_marca_texto_eliminado_e_insertado(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Diff alfa', '<p>Alpha beta gamma</p>', 'ST-05 diff alfa');
        $this->updatePage($page, 'ST-05 Diff delta', '<p>Alpha delta gamma</p>', 'ST-05 diff delta');

        $secondRevision = $this->revisionsFor($page)[1];

        $response = $this->get($this->revisionUrl($page, $secondRevision, '/changes'));

        $response->assertStatus(200);
        $response->assertSee('Alpha', false);
        $response->assertSee('gamma', false);
        $response->assertSee('<del class="diffmod">beta</del>', false);
        $response->assertSee('<ins class="diffmod">delta</ins>', false);
    }

    public function test_st_05_19_restaurar_y_luego_editar_genera_una_revision_adicional(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage($page, 'ST-05 Restore edit 1', '<p>Contenido antes de restore edit</p>', 'ST-05 restore edit 1');
        $this->updatePage($page, 'ST-05 Restore edit 2', '<p>Contenido temporal restore edit</p>', 'ST-05 restore edit 2');

        $firstRevision = $this->revisionsFor($page)->first();

        $this->put($this->revisionUrl($page, $firstRevision, '/restore'))
            ->assertRedirect();

        $page->refresh();

        $this->assertSame('ST-05 Restore edit 1', $page->name);

        $this->updatePage($page, 'ST-05 Restore edit 3', '<p>Contenido final luego de restaurar</p>', 'ST-05 restore edit 3');

        $revisions = $this->revisionsFor($page);

        $this->assertCount(4, $revisions);
        $this->assertSame('ST-05 Restore edit 3', $revisions->last()->name);
        $this->assertStringContainsString('Contenido final luego de restaurar', $page->refresh()->html);
    }

    public function test_st_05_20_historial_de_revisiones_es_independiente_entre_paginas(): void
    {
        $pageA = $this->createPageForRevisionFlow('ST-05 Página A');
        $pageB = $this->createPageForRevisionFlow('ST-05 Página B');

        $this->updatePage($pageA, 'ST-05 Página A revisión 1', '<p>Contenido A uno</p>', 'ST-05 A 1');
        $this->updatePage($pageA, 'ST-05 Página A revisión 2', '<p>Contenido A dos</p>', 'ST-05 A 2');

        $this->updatePage($pageB, 'ST-05 Página B revisión 1', '<p>Contenido B uno</p>', 'ST-05 B 1');

        $revisionsA = $this->revisionsFor($pageA);
        $revisionsB = $this->revisionsFor($pageB);

        $this->assertCount(2, $revisionsA);
        $this->assertCount(1, $revisionsB);

        $this->assertSame(['ST-05 A 1', 'ST-05 A 2'], $revisionsA->pluck('summary')->values()->all());
        $this->assertSame(['ST-05 B 1'], $revisionsB->pluck('summary')->values()->all());

        $this->get($pageA->getUrl('/revisions'))
            ->assertStatus(200)
            ->assertSee('ST-05 A 1')
            ->assertSee('ST-05 A 2')
            ->assertDontSee('ST-05 B 1');
    }
}