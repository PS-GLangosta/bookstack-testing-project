<?php

namespace Tests\System;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ST-01 — Flujo completo de creación de contenido
 */
class ContentCreationFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'ST01 ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'st01-' . $roleName . '-' . uniqid() . '@example.com',
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function createBookViaHttp(User $user, ?string $name = null, string $description = ''): Book
    {
        $name = $name ?: 'ST01 Libro ' . uniqid();

        $this->actingAs($user)
            ->post('/books', [
                'name' => $name,
                'description' => $description,
            ])
            ->assertRedirect();

        $book = Book::query()
            ->where('name', $name)
            ->latest('id')
            ->firstOrFail();

        $book->refresh();
        $book->rebuildPermissions();
        $book->indexForSearch();

        return $book->refresh();
    }

    protected function createChapterViaHttp(
        User $user,
        Book $book,
        ?string $name = null,
        string $description = ''
    ): Chapter {
        $name = $name ?: 'ST01 Capítulo ' . uniqid();

        $this->actingAs($user)
            ->post($book->getUrl('/create-chapter'), [
                'name' => $name,
                'description' => $description,
            ])
            ->assertRedirect();

        $chapter = Chapter::query()
            ->where('book_id', $book->id)
            ->where('name', $name)
            ->latest('id')
            ->firstOrFail();

        $book->refresh();
        $book->rebuildPermissions();

        $chapter->refresh();
        $chapter->indexForSearch();

        return $chapter->refresh();
    }

    protected function createPublishedPageViaHttp(
        User $user,
        Chapter $chapter,
        ?string $name = null,
        string $html = '<p>Contenido ST01</p>'
    ): Page {
        $name = $name ?: 'ST01 Página ' . uniqid();

        $this->actingAs($user)
            ->get($chapter->getUrl('/create-page'))
            ->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', true)
            ->where('created_by', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)
            ->post($draft->getUrl(), [
                'name' => $name,
                'html' => $html,
                'markdown' => '',
            ])
            ->assertRedirect();

        $page = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('name', $name)
            ->where('draft', false)
            ->latest('id')
            ->firstOrFail();

        $page->refresh();
        $page->indexForSearch();

        return $page->refresh();
    }

    protected function createDraftPageViaHttp(User $user, Chapter $chapter): array
    {
        $createResp = $this->actingAs($user)
            ->get($chapter->getUrl('/create-page'));

        $createResp->assertRedirect();

        $editorUrl = $createResp->headers->get('Location');

        $draft = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', true)
            ->where('created_by', $user->id)
            ->latest('id')
            ->firstOrFail();

        return [$draft->refresh(), $editorUrl];
    }

    public function test_st01_01_admin_crea_libro_capitulo_pagina_en_secuencia_y_todo_visible(): void
    {
        $admin = $this->userWithRole('admin');

        $suffix = uniqid();

        $libro = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Sistema ' . $suffix,
            'Libro creado en prueba de sistema ST-01-01'
        );

        $capitulo = $this->createChapterViaHttp(
            $admin,
            $libro,
            'ST01 Capítulo Sistema ' . $suffix,
            'Capítulo de prueba de sistema'
        );

        $pagina = $this->createPublishedPageViaHttp(
            $admin,
            $capitulo,
            'ST01 Página Sistema ' . $suffix,
            '<p>Contenido de la página de prueba de sistema ST-01-01.</p>'
        );

        $this->assertDatabaseHasEntityData('book', [
            'name' => $libro->name,
        ]);

        $this->assertDatabaseHasEntityData('chapter', [
            'name' => $capitulo->name,
        ]);

        $this->assertDatabaseHasEntityData('page', [
            'name' => $pagina->name,
        ]);

        $this->actingAs($admin)
            ->get($libro->getUrl())
            ->assertOk()
            ->assertSee($libro->name);
    }

    public function test_st01_02_pagina_creada_aparece_en_resultados_de_busqueda(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $nombreUnico = 'ST01BusquedaUnica' . uniqid();

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            $nombreUnico,
            '<p>Contenido único para validar búsqueda en ST-01-02.</p>'
        );

        $page->indexForSearch();

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($nombreUnico))
            ->assertOk()
            ->assertSee($nombreUnico);
    }

    public function test_st01_03_pagina_de_editor_es_visible_para_viewer(): void
    {
        $editor = $this->userWithRole('editor');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp($editor);
        $chapter = $this->createChapterViaHttp($editor, $book);

        $pagina = $this->createPublishedPageViaHttp(
            $editor,
            $chapter,
            'ST01 Página Visible Para Viewer ' . uniqid(),
            '<p>Contenido publicado por editor visible para viewer.</p>'
        );

        $this->actingAs($viewer)
            ->get($pagina->getUrl())
            ->assertOk()
            ->assertSee($pagina->name);
    }

    public function test_st01_04_borrador_no_visible_para_viewer_si_para_autor(): void
    {
        $editor = $this->userWithRole('editor');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp($editor);
        $chapter = $this->createChapterViaHttp($editor, $book);

        [$draft, $editorUrl] = $this->createDraftPageViaHttp($editor, $chapter);

        $this->assertTrue((bool) $draft->draft, 'La página debe estar en estado borrador');

        $this->actingAs($viewer)
            ->get($draft->getUrl())
            ->assertNotFound();

        $this->actingAs($editor)
            ->get($editorUrl)
            ->assertOk();
    }

    public function test_st01_05_eliminar_libro_elimina_contenido_y_desaparece_de_busqueda(): void
    {
        $admin = $this->userWithRole('admin');

        $nombreLibro = 'ST01 Libro Para Eliminar ' . uniqid();

        $libro = $this->createBookViaHttp(
            $admin,
            $nombreLibro,
            'Descripción para libro eliminado en ST-01-05'
        );

        $libroId = $libro->id;

        $capitulo = $this->createChapterViaHttp(
            $admin,
            $libro,
            'ST01 Capítulo Hijo ' . uniqid()
        );

        $capituloId = $capitulo->id;

        $libro->refresh();
        $libro->indexForSearch();

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($nombreLibro))
            ->assertOk()
            ->assertSee($nombreLibro);

        $this->actingAs($admin)
            ->delete($libro->getUrl())
            ->assertRedirect();

        $this->assertSoftDeleted('entities', [
            'id' => $libroId,
            'type' => 'book',
        ]);

        $this->assertSoftDeleted('entities', [
            'id' => $capituloId,
            'type' => 'chapter',
        ]);

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($nombreLibro))
            ->assertOk()
            ->assertSee('0 total results found');
    }
    public function test_st01_06_crear_libro_sin_nombre_retorna_error_de_validacion(): void
    {
        $admin = $this->userWithRole('admin');

        $booksBefore = Book::query()->count();

        $this->actingAs($admin)
            ->post('/books', [
                'name' => '',
                'description' => 'Libro inválido sin nombre',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertSame($booksBefore, Book::query()->count());
    }

    public function test_st01_07_crear_capitulo_sin_nombre_retorna_error_de_validacion(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Validación Capítulo ' . uniqid(),
            'Libro para validar capítulo sin nombre'
        );

        $chaptersBefore = Chapter::query()
            ->where('book_id', $book->id)
            ->count();

        $this->actingAs($admin)
            ->post($book->getUrl('/create-chapter'), [
                'name' => '',
                'description' => 'Capítulo inválido sin nombre',
            ])
            ->assertSessionHasErrors(['name']);

        $chaptersAfter = Chapter::query()
            ->where('book_id', $book->id)
            ->count();

        $this->assertSame($chaptersBefore, $chaptersAfter);
    }

    public function test_st01_08_actualizar_pagina_publicada_cambia_nombre_y_contenido_visible(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'ST01 Página Antes De Actualizar ' . uniqid(),
            '<p>Contenido inicial antes de actualizar</p>'
        );

        $updatedName = 'ST01 Página Actualizada ' . uniqid();

        $this->actingAs($admin)
            ->put($page->getUrl(), [
                'name' => $updatedName,
                'html' => '<p>Contenido actualizado desde flujo system.</p>',
                'markdown' => '',
                'summary' => 'Actualización desde ST01-08',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($updatedName, $page->name);
        $this->assertStringContainsString('Contenido actualizado desde flujo system', $page->html);

        $this->actingAs($admin)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($updatedName)
            ->assertSee('Contenido actualizado desde flujo system');
    }

    public function test_st01_09_admin_crea_pagina_directamente_en_raiz_del_libro(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Página Raíz ' . uniqid(),
            'Libro para validar creación de página en raíz'
        );

        $this->actingAs($admin)
            ->get($book->getUrl('/create-page'))
            ->assertRedirect();

        $draft = Page::query()
            ->where('book_id', $book->id)
            ->whereNull('chapter_id')
            ->where('draft', true)
            ->where('created_by', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $pageName = 'ST01 Página Raíz ' . uniqid();

        $this->actingAs($admin)
            ->post($draft->getUrl(), [
                'name' => $pageName,
                'html' => '<p>Contenido creado directamente en la raíz del libro.</p>',
                'markdown' => '',
            ])
            ->assertRedirect();

        $page = Page::query()
            ->where('book_id', $book->id)
            ->whereNull('chapter_id')
            ->where('name', $pageName)
            ->where('draft', false)
            ->firstOrFail();

        $this->assertSame($book->id, $page->book_id);
        $this->assertNull($page->chapter_id);
        $this->assertFalse((bool) $page->draft);

        $this->actingAs($admin)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($pageName)
            ->assertSee('Contenido creado directamente en la raíz del libro');
    }

    public function test_st01_10_eliminar_pagina_publicada_aplica_soft_delete(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'ST01 Página Para Eliminar ' . uniqid(),
            '<p>Contenido de página que será eliminada.</p>'
        );

        $pageId = $page->id;

        $this->actingAs($admin)
            ->delete($page->getUrl())
            ->assertRedirect();

        $this->assertSoftDeleted('entities', [
            'id' => $pageId,
            'type' => 'page',
        ]);
    }
    public function test_st01_11_libro_creado_aparece_en_listado_de_libros(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Listado ' . uniqid(),
            'Libro para validar aparición en listado general'
        );

        $this->actingAs($admin)
            ->get('/books')
            ->assertOk()
            ->assertSee($book->name);
    }

    public function test_st01_12_capitulo_creado_aparece_en_vista_del_libro(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Con Capítulo ' . uniqid(),
            'Libro para validar capítulo visible'
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'ST01 Capítulo Visible En Libro ' . uniqid(),
            'Capítulo creado para aparecer dentro del libro'
        );

        $this->actingAs($admin)
            ->get($book->getUrl())
            ->assertOk()
            ->assertSee($book->name)
            ->assertSee($chapter->name);
    }

    public function test_st01_13_pagina_creada_aparece_en_vista_del_capitulo(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'ST01 Página Visible En Capítulo ' . uniqid(),
            '<p>Contenido visible dentro del capítulo ST01-13.</p>'
        );

        $this->actingAs($admin)
            ->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($chapter->name)
            ->assertSee($page->name);
    }

    public function test_st01_14_actualizar_libro_cambia_nombre_y_descripcion_visible(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'ST01 Libro Antes De Actualizar ' . uniqid(),
            'Descripción inicial del libro'
        );

        $updatedName = 'ST01 Libro Actualizado ' . uniqid();
        $updatedDescription = 'Descripción actualizada desde prueba system';

        $this->actingAs($admin)
            ->put($book->getUrl(), [
                'name' => $updatedName,
                'description' => $updatedDescription,
                'description_html' => '<p>' . $updatedDescription . '</p>',
            ])
            ->assertRedirect();

        $book->refresh();

        $this->assertSame($updatedName, $book->name);
        $this->assertStringContainsString($updatedDescription, $book->description);
        $this->assertStringContainsString($updatedDescription, $book->description_html);

        $this->actingAs($admin)
            ->get($book->getUrl())
            ->assertOk()
            ->assertSee($updatedName)
            ->assertSee($updatedDescription);
    }

    public function test_st01_15_actualizar_capitulo_cambia_nombre_y_descripcion_visible(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'ST01 Capítulo Antes De Actualizar ' . uniqid(),
            'Descripción inicial del capítulo'
        );

        $updatedName = 'ST01 Capítulo Actualizado ' . uniqid();
        $updatedDescription = 'Descripción actualizada del capítulo desde prueba system';

        $this->actingAs($admin)
            ->put($chapter->getUrl(), [
                'name' => $updatedName,
                'description' => $updatedDescription,
                'description_html' => '<p>' . $updatedDescription . '</p>',
            ])
            ->assertRedirect();

        $chapter->refresh();

        $this->assertSame($updatedName, $chapter->name);
        $this->assertStringContainsString($updatedDescription, $chapter->description);
        $this->assertStringContainsString($updatedDescription, $chapter->description_html);

        $this->actingAs($admin)
            ->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($updatedName)
            ->assertSee($updatedDescription);
    }

    public function test_st01_16_crear_pagina_sin_nombre_no_publica_pagina_con_nombre_vacio(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $this->actingAs($admin)
            ->get($chapter->getUrl('/create-page'))
            ->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', true)
            ->where('created_by', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $publishedPagesBefore = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', false)
            ->count();

        $this->actingAs($admin)
            ->post($draft->getUrl(), [
                'name' => '',
                'html' => '<p>Contenido sin nombre</p>',
                'markdown' => '',
            ]);

        $publishedPagesAfter = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', false)
            ->count();

        $this->assertSame($publishedPagesBefore, $publishedPagesAfter);

        $this->assertFalse(
            Page::query()
                ->where('chapter_id', $chapter->id)
                ->where('draft', false)
                ->where('name', '')
                ->exists()
        );
    }

    public function test_st01_17_actualizar_pagina_sin_nombre_retorna_error_y_conserva_estado(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'ST01 Página Original ' . uniqid(),
            '<p>Contenido original antes de validación.</p>'
        );

        $originalName = $page->name;
        $originalText = $page->text;

        $this->actingAs($admin)
            ->put($page->getUrl(), [
                'name' => '',
                'html' => '<p>Contenido inválido por nombre vacío.</p>',
                'markdown' => '',
                'summary' => 'Intento inválido',
            ])
            ->assertSessionHasErrors(['name']);

        $page->refresh();

        $this->assertSame($originalName, $page->name);
        $this->assertSame($originalText, $page->text);
    }

    public function test_st01_18_busqueda_por_nombre_de_capitulo_retorna_capitulo(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);

        $chapterName = 'ST01 Capítulo Buscable ' . uniqid();

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            $chapterName,
            'Capítulo creado para búsqueda ST01-18'
        );

        $chapter->indexForSearch();

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($chapterName))
            ->assertOk()
            ->assertSee($chapterName);
    }

    public function test_st01_19_busqueda_por_nombre_de_libro_retorna_libro(): void
    {
        $admin = $this->userWithRole('admin');

        $bookName = 'ST01 Libro Buscable ' . uniqid();

        $book = $this->createBookViaHttp(
            $admin,
            $bookName,
            'Libro creado para búsqueda ST01-19'
        );

        $book->indexForSearch();

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($bookName))
            ->assertOk()
            ->assertSee($bookName);
    }

    public function test_st01_20_pagina_actualizada_incrementa_revision_count(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'ST01 Página Con Revisión ' . uniqid(),
            '<p>Contenido inicial para revisión.</p>'
        );

        $this->assertSame(1, $page->revision_count);

        $this->actingAs($admin)
            ->put($page->getUrl(), [
                'name' => $page->name,
                'html' => '<p>Contenido actualizado para incrementar revisión.</p>',
                'markdown' => '',
                'summary' => 'Actualización para ST01-20',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame(2, $page->revision_count);

        $this->assertDatabaseHas('page_revisions', [
            'page_id' => $page->id,
            'type' => 'version',
            'revision_number' => 2,
            'summary' => 'Actualización para ST01-20',
            'text' => 'Contenido actualizado para incrementar revisión.',
        ]);
    }
}