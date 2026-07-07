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

    protected function createPageForRevisionFlow(): Page
    {
        $page = $this->entities->newPage([
            'name' => 'ST-05 Página revisiones inicial',
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

        // BookStack procesa el HTML y agrega ids internos tipo bkmrk-*,
        // por eso validamos el contenido visible y no el HTML exacto.
        $this->assertStringContainsString(strip_tags($html), $page->html);
    }

    protected function revisionsFor(Page $page)
    {
        return PageRevision::query()
            ->where('page_id', $page->id)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function test_st_05_01_editar_pagina_tres_veces_genera_tres_revisiones_distintas(): void
    {
        $page = $this->createPageForRevisionFlow();

        $this->updatePage(
            $page,
            'ST-05 Página revisión 1',
            '<p>Contenido revisión uno</p>',
            'ST-05 edición 1'
        );

        $this->updatePage(
            $page,
            'ST-05 Página revisión 2',
            '<p>Contenido revisión dos</p>',
            'ST-05 edición 2'
        );

        $this->updatePage(
            $page,
            'ST-05 Página revisión 3',
            '<p>Contenido revisión tres</p>',
            'ST-05 edición 3'
        );

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

        $this->updatePage(
            $page,
            'ST-05 Historial revisión 1',
            '<p>Historial contenido uno</p>',
            'ST-05 historial 1'
        );

        $this->updatePage(
            $page,
            'ST-05 Historial revisión 2',
            '<p>Historial contenido dos</p>',
            'ST-05 historial 2'
        );

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

        $this->updatePage(
            $page,
            'ST-05 Restaurar versión antigua',
            '<p>Contenido antiguo para restaurar</p>',
            'ST-05 versión antigua'
        );

        $this->updatePage(
            $page,
            'ST-05 Restaurar versión actual',
            '<p>Contenido actual antes de restaurar</p>',
            'ST-05 versión actual'
        );

        $oldRevision = $this->revisionsFor($page)->first();

        $response = $this->put(
            $page->getUrl('/revisions/' . $oldRevision->id . '/restore')
        );

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

        $this->updatePage(
            $page,
            'ST-05 Nueva entrada revisión 1',
            '<p>Contenido para restaurar luego</p>',
            'ST-05 base restaurable'
        );

        $this->updatePage(
            $page,
            'ST-05 Nueva entrada revisión 2',
            '<p>Contenido posterior</p>',
            'ST-05 contenido posterior'
        );

        $revisionsBeforeRestore = $this->revisionsFor($page);

        $this->assertCount(2, $revisionsBeforeRestore);

        $revisionToRestore = $revisionsBeforeRestore->first();

        $response = $this->put(
            $page->getUrl('/revisions/' . $revisionToRestore->id . '/restore')
        );

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

        $this->updatePage(
            $page,
            'ST-05 Comparación revisión uno',
            '<p>Texto original para comparar</p>',
            'ST-05 comparación 1'
        );

        $this->updatePage(
            $page,
            'ST-05 Comparación revisión dos',
            '<p>Texto actualizado para comparar</p>',
            'ST-05 comparación 2'
        );

        $revisions = $this->revisionsFor($page);

        $this->assertCount(2, $revisions);

        $firstRevision = $revisions[0];
        $secondRevision = $revisions[1];

        $response = $this->get(
            $page->getUrl('/revisions/' . $secondRevision->id . '/changes')
        );

        $response->assertStatus(200);

        // BookStack muestra las diferencias con etiquetas HTML de diff.
        // Por eso validamos la estructura real del cambio:
        // "original" eliminado y "actualizado" insertado.
        $response->assertSee('Texto', false);
        $response->assertSee('para comparar', false);
        $response->assertSee('<del class="diffmod">original</del>', false);
        $response->assertSee('<ins class="diffmod">actualizado</ins>', false);

        $this->assertSame('ST-05 Comparación revisión uno', $firstRevision->name);
        $this->assertSame('ST-05 Comparación revisión dos', $secondRevision->name);
    }
}