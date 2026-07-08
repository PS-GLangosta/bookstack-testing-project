<?php

namespace Tests\Integration\Entities;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Deletion;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EntityWebControllerCoverageTest extends TestCase
{
    use DatabaseTransactions;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'Entity Web ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'entity-web-' . $roleName . '-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function createBookViaHttp(User $user, ?string $name = null): Book
    {
        $name = $name ?: 'Entity Web Libro ' . uniqid();

        $this->actingAs($user)
            ->post('/books', [
                'name' => $name,
                'description_html' => '<p>Descripción para cobertura de libro.</p>',
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

    protected function createChapterViaHttp(User $user, Book $book, ?string $name = null): Chapter
    {
        $name = $name ?: 'Entity Web Capítulo ' . uniqid();

        $this->actingAs($user)
            ->post($book->getUrl('/create-chapter'), [
                'name' => $name,
                'description_html' => '<p>Descripción para cobertura de capítulo.</p>',
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
        string $html = '<p>Contenido de página para cobertura.</p>'
    ): Page {
        $name = $name ?: 'Entity Web Página ' . uniqid();

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

    protected function createBookshelfViaHttp(User $user, ?string $name = null, array $bookIds = []): Bookshelf
    {
        $name = $name ?: 'Entity Web Estantería ' . uniqid();

        $this->actingAs($user)
            ->post('/shelves', [
                'name' => $name,
                'description_html' => '<p>Descripción para cobertura de estantería.</p>',
                'books' => implode(',', $bookIds),
                'tags' => [
                    [
                        'name' => 'Tipo',
                        'value' => 'Cobertura',
                    ],
                ],
            ])
            ->assertRedirect();

        $shelf = Bookshelf::query()
            ->where('name', $name)
            ->latest('id')
            ->firstOrFail();

        $shelf->refresh();
        $shelf->rebuildPermissions();
        $shelf->indexForSearch();

        return $shelf->refresh();
    }

    public function test_bookshelf_web_crud_cubre_index_create_show_edit_update_delete(): void
    {
        $admin = $this->userWithRole('admin');

        $bookOne = $this->createBookViaHttp($admin, 'Entity Web Libro Shelf Uno ' . uniqid());
        $bookTwo = $this->createBookViaHttp($admin, 'Entity Web Libro Shelf Dos ' . uniqid());

        $this->actingAs($admin)
            ->get('/shelves')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/create-shelf')
            ->assertOk()
            ->assertSee('Shelf', false);

        $shelf = $this->createBookshelfViaHttp(
            $admin,
            'Entity Web Shelf CRUD ' . uniqid(),
            [$bookOne->id]
        );

        $this->assertTrue($shelf->contains($bookOne));

        $this->actingAs($admin)
            ->get($shelf->getUrl())
            ->assertOk()
            ->assertSee($shelf->name)
            ->assertSee($bookOne->name);

        $this->actingAs($admin)
            ->get($shelf->getUrl('/edit'))
            ->assertOk()
            ->assertSee($shelf->name);

        $updatedName = 'Entity Web Shelf Actualizada ' . uniqid();

        $this->actingAs($admin)
            ->put($shelf->getUrl(), [
                'name' => $updatedName,
                'description_html' => '<p>Descripción actualizada para shelf.</p>',
                'books' => (string) $bookTwo->id,
                'image_reset' => 'true',
            ])
            ->assertRedirect();

        $shelf->refresh();

        $this->assertSame($updatedName, $shelf->name);
        $this->assertTrue($shelf->contains($bookTwo));
        $this->assertFalse($shelf->contains($bookOne));

        $this->actingAs($admin)
            ->get($shelf->getUrl('/delete'))
            ->assertOk()
            ->assertSee($updatedName);

        $this->actingAs($admin)
            ->delete($shelf->getUrl())
            ->assertRedirect('/shelves');

        $this->assertDatabaseMissing('entities', [
            'id' => $shelf->id,
            'type' => 'bookshelf',
            'deleted_at' => null,
        ]);
    }

    public function test_bookshelf_web_viewer_no_puede_crear_editar_ni_eliminar(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp($admin);
        $shelf = $this->createBookshelfViaHttp($admin, 'Entity Web Shelf Protegida ' . uniqid(), [$book->id]);

        $originalName = $shelf->name;

        $this->actingAs($viewer)
            ->get('/create-shelf')
            ->assertRedirect();

        $this->actingAs($viewer)
            ->get($shelf->getUrl('/edit'))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->put($shelf->getUrl(), [
                'name' => 'Cambio no autorizado',
                'description_html' => '<p>No debe cambiar.</p>',
                'books' => '',
            ])
            ->assertRedirect();

        $this->actingAs($viewer)
            ->delete($shelf->getUrl())
            ->assertRedirect();

        $shelf->refresh();

        $this->assertSame($originalName, $shelf->name);
        $this->assertDatabaseHas('entities', [
            'id' => $shelf->id,
            'type' => 'bookshelf',
            'deleted_at' => null,
        ]);
    }

    public function test_page_template_controller_lista_busca_y_entrega_contenido_de_template(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $templateName = 'Entity Web Template ' . uniqid();
        $templateHtml = '<p>Contenido reutilizable desde template.</p>';

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            $templateName,
            $templateHtml
        );

        $normalPage = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity Web Página Normal ' . uniqid(),
            '<p>No es template.</p>'
        );

        $this->actingAs($admin)
            ->get('/templates/' . $normalPage->id)
            ->assertNotFound();

        $page->template = true;
        $page->html = $templateHtml;
        $page->markdown = '# Markdown Template';
        $page->save();

        $this->actingAs($admin)
            ->get('/templates?search=' . urlencode($templateName))
            ->assertOk()
            ->assertSee($templateName);

        $this->actingAs($admin)
            ->get('/templates/' . $page->id)
            ->assertOk()
            ->assertJson([
                'html' => $templateHtml,
                'markdown' => '# Markdown Template',
            ]);
    }

    public function test_recycle_bin_web_lista_muestra_y_restaura_eliminacion(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);
        $page = $this->createPublishedPageViaHttp($admin, $chapter);

        $this->actingAs($admin)
            ->delete($page->getUrl())
            ->assertRedirect();

        $deletion = Deletion::query()
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get('/settings/recycle-bin')
            ->assertOk();

        $this->actingAs($admin)
            ->get("/settings/recycle-bin/{$deletion->id}/restore")
            ->assertOk()
            ->assertSee($page->name);

        $this->actingAs($admin)
            ->post("/settings/recycle-bin/{$deletion->id}/restore")
            ->assertRedirect('/settings/recycle-bin');

        $this->assertDatabaseHas('entities', [
            'id' => $page->id,
            'type' => 'page',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseMissing('deletions', [
            'id' => $deletion->id,
        ]);
    }

    public function test_recycle_bin_web_muestra_y_elimina_permanentemente(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);
        $page = $this->createPublishedPageViaHttp($admin, $chapter);

        $this->actingAs($admin)
            ->delete($page->getUrl())
            ->assertRedirect();

        $deletion = Deletion::query()
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get("/settings/recycle-bin/{$deletion->id}/destroy")
            ->assertOk()
            ->assertSee($page->name);

        $this->actingAs($admin)
            ->delete("/settings/recycle-bin/{$deletion->id}")
            ->assertRedirect('/settings/recycle-bin');

        $this->assertDatabaseMissing('entities', [
            'id' => $page->id,
            'type' => 'page',
        ]);

        $this->assertDatabaseMissing('deletions', [
            'id' => $deletion->id,
        ]);
    }

    public function test_recycle_bin_web_empty_elimina_todo_el_contenido_reciclado(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $pageOne = $this->createPublishedPageViaHttp($admin, $chapter, 'Entity Web Papelera Uno ' . uniqid());
        $pageTwo = $this->createPublishedPageViaHttp($admin, $chapter, 'Entity Web Papelera Dos ' . uniqid());

        $this->actingAs($admin)->delete($pageOne->getUrl())->assertRedirect();
        $this->actingAs($admin)->delete($pageTwo->getUrl())->assertRedirect();

        $this->assertGreaterThanOrEqual(2, Deletion::query()->count());

        $this->actingAs($admin)
            ->post('/settings/recycle-bin/empty')
            ->assertRedirect('/settings/recycle-bin');

        $this->assertSame(0, Deletion::query()->count());

        $this->assertDatabaseMissing('entities', [
            'id' => $pageOne->id,
            'type' => 'page',
        ]);

        $this->assertDatabaseMissing('entities', [
            'id' => $pageTwo->id,
            'type' => 'page',
        ]);
    }

    public function test_book_web_creado_desde_shelf_queda_asociado_a_estanteria(): void
    {
        $admin = $this->userWithRole('admin');

        $shelf = $this->createBookshelfViaHttp(
            $admin,
            'Entity Web Shelf Para Crear Libro ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($shelf->getUrl('/create-book'))
            ->assertOk();

        $bookName = 'Entity Web Libro Creado Desde Shelf ' . uniqid();

        $this->actingAs($admin)
            ->post($shelf->getUrl('/create-book'), [
                'name' => $bookName,
                'description_html' => '<p>Libro creado directamente desde una estantería.</p>',
            ])
            ->assertRedirect();

        $book = Book::query()
            ->where('name', $bookName)
            ->latest('id')
            ->firstOrFail();

        $shelf->refresh();

        $this->assertTrue($shelf->contains($book));
    }

    public function test_book_web_copy_crea_copia_con_nombre_personalizado(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'Entity Web Libro Para Copiar ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($book->getUrl('/copy'))
            ->assertOk();

        $copyName = 'Entity Web Libro Copiado ' . uniqid();

        $this->actingAs($admin)
            ->post($book->getUrl('/copy'), [
                'name' => $copyName,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'type' => 'book',
            'name' => $copyName,
            'deleted_at' => null,
        ]);
    }

    public function test_chapter_web_move_con_destino_vacio_y_destino_valido(): void
    {
        $admin = $this->userWithRole('admin');

        $sourceBook = $this->createBookViaHttp($admin, 'Entity Web Libro Origen Move Chapter ' . uniqid());
        $targetBook = $this->createBookViaHttp($admin, 'Entity Web Libro Destino Move Chapter ' . uniqid());

        $chapter = $this->createChapterViaHttp(
            $admin,
            $sourceBook,
            'Entity Web Capítulo Para Mover ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($chapter->getUrl('/move'))
            ->assertOk()
            ->assertSee('Move Chapter');

        $this->actingAs($admin)
            ->put($chapter->getUrl('/move'), [
                'entity_selection' => '',
            ])
            ->assertRedirect($chapter->getUrl());

        $chapter->refresh();

        $this->assertSame($sourceBook->id, $chapter->book_id);

        $this->actingAs($admin)
            ->put($chapter->getUrl('/move'), [
                'entity_selection' => 'book:' . $targetBook->id,
            ])
            ->assertRedirect();

        $chapter->refresh();

        $this->assertSame($targetBook->id, $chapter->book_id);
    }

    public function test_chapter_web_copy_y_convert_to_book(): void
    {
        $admin = $this->userWithRole('admin');

        $sourceBook = $this->createBookViaHttp($admin, 'Entity Web Libro Origen Copy Chapter ' . uniqid());
        $targetBook = $this->createBookViaHttp($admin, 'Entity Web Libro Destino Copy Chapter ' . uniqid());

        $chapter = $this->createChapterViaHttp(
            $admin,
            $sourceBook,
            'Entity Web Capítulo Para Copiar ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($chapter->getUrl('/copy'))
            ->assertOk();

        $copyName = 'Entity Web Capítulo Copiado ' . uniqid();

        $this->actingAs($admin)
            ->post($chapter->getUrl('/copy'), [
                'name' => $copyName,
                'entity_selection' => 'book:' . $targetBook->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'type' => 'chapter',
            'book_id' => $targetBook->id,
            'name' => $copyName,
            'deleted_at' => null,
        ]);

        $chapterToConvert = $this->createChapterViaHttp(
            $admin,
            $sourceBook,
            'Entity Web Capítulo Convertido A Libro ' . uniqid()
        );

        $this->actingAs($admin)
            ->post($chapterToConvert->getUrl('/convert-to-book'))
            ->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'type' => 'book',
            'name' => $chapterToConvert->name,
            'deleted_at' => null,
        ]);
    }

    public function test_page_web_ajax_muestra_pagina_guarda_borrador_y_link_redirige(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity Web Página Ajax ' . uniqid(),
            '<p>Contenido original para AJAX.</p>'
        );

        $this->actingAs($admin)
            ->get('/ajax/page/' . $page->id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $page->id,
                'name' => $page->name,
            ]);

        $this->actingAs($admin)
            ->put('/ajax/page/' . $page->id . '/save-draft', [
                'name' => $page->name,
                'html' => '<p>Contenido guardado como borrador AJAX.</p>',
                'markdown' => '',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'success',
            ]);

        $this->actingAs($admin)
            ->get('/link/' . $page->id)
            ->assertRedirect($page->getUrl());

        $this->actingAs($admin)
            ->get('/pages/recently-updated')
            ->assertOk();
    }

    public function test_page_web_move_y_copy_cubren_destinos_vacios_validos_e_invalidos(): void
    {
        $admin = $this->userWithRole('admin');

        $sourceBook = $this->createBookViaHttp($admin, 'Entity Web Libro Origen Page Move ' . uniqid());
        $targetBook = $this->createBookViaHttp($admin, 'Entity Web Libro Destino Page Move ' . uniqid());

        $sourceChapter = $this->createChapterViaHttp($admin, $sourceBook);
        $targetChapter = $this->createChapterViaHttp($admin, $targetBook);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $sourceChapter,
            'Entity Web Página Para Mover Y Copiar ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($page->getUrl('/move'))
            ->assertOk();

        $this->actingAs($admin)
            ->put($page->getUrl('/move'), [
                'entity_selection' => '',
            ])
            ->assertRedirect($page->getUrl());

        $page->refresh();

        $this->assertSame($sourceChapter->id, $page->chapter_id);

        $this->actingAs($admin)
            ->put($page->getUrl('/move'), [
                'entity_selection' => 'chapter:' . $targetChapter->id,
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($targetBook->id, $page->book_id);
        $this->assertSame($targetChapter->id, $page->chapter_id);

        $this->actingAs($admin)
            ->get($page->getUrl('/copy'))
            ->assertOk();

        $this->actingAs($admin)
            ->post($page->getUrl('/copy'), [
                'name' => 'No debe copiarse por destino inválido',
                'entity_selection' => 'book:999999999',
            ])
            ->assertRedirect($page->getUrl('/copy'));

        $copyName = 'Entity Web Página Copiada ' . uniqid();

        $this->actingAs($admin)
            ->post($page->getUrl('/copy'), [
                'name' => $copyName,
                'entity_selection' => 'chapter:' . $sourceChapter->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'type' => 'page',
            'book_id' => $sourceBook->id,
            'chapter_id' => $sourceChapter->id,
            'name' => $copyName,
            'deleted_at' => null,
        ]);
    }

    public function test_page_web_delete_view_detecta_pagina_usada_como_template(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity Web Página Usada Como Template ' . uniqid()
        );

        $book->default_template_id = $page->id;
        $book->save();

        $this->actingAs($admin)
            ->get($page->getUrl('/delete'))
            ->assertOk();
    }
}