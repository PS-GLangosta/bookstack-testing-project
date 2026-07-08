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

class EntityApiControllerCoverageTest extends TestCase
{
    use DatabaseTransactions;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'Entity API ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'entity-api-' . $roleName . '-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function actingAsApi(User $user): static
    {
        $this->actingAs($user, 'api');

        return $this;
    }

    protected function createBookViaHttp(User $user, ?string $name = null): Book
    {
        $name = $name ?: 'Entity API Libro ' . uniqid();

        $this->actingAs($user)
            ->post('/books', [
                'name' => $name,
                'description_html' => '<p>Descripción para cobertura API.</p>',
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
        $name = $name ?: 'Entity API Capítulo ' . uniqid();

        $this->actingAs($user)
            ->post($book->getUrl('/create-chapter'), [
                'name' => $name,
                'description_html' => '<p>Descripción de capítulo API.</p>',
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
        string $html = '<p>Contenido API.</p>'
    ): Page {
        $name = $name ?: 'Entity API Página ' . uniqid();

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

    public function test_bookshelf_api_crud_y_validaciones(): void
    {
        $admin = $this->userWithRole('admin');
        $book = $this->createBookViaHttp($admin, 'Entity API Libro Para Shelf ' . uniqid());

        $this->actingAsApi($admin)
            ->getJson('/api/shelves')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $this->actingAsApi($admin)
            ->postJson('/api/shelves', [])
            ->assertStatus(422);

        $shelfName = 'Entity API Shelf ' . uniqid();

        $createResponse = $this->actingAsApi($admin)
            ->postJson('/api/shelves', [
                'name' => $shelfName,
                'description' => 'Descripción de shelf desde API.',
                'description_html' => '<p>Descripción de shelf desde API.</p>',
                'books' => [$book->id],
                'tags' => [
                    [
                        'name' => 'Origen',
                        'value' => 'API',
                    ],
                ],
            ]);

        $createResponse
            ->assertOk()
            ->assertJsonFragment([
                'name' => $shelfName,
            ]);

        $shelf = Bookshelf::query()
            ->where('name', $shelfName)
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($shelf->contains($book));

        $this->actingAsApi($admin)
            ->getJson("/api/shelves/{$shelf->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $shelf->id,
                'name' => $shelfName,
            ])
            ->assertJsonStructure([
                'books',
                'tags',
                'created_by',
                'updated_by',
                'owned_by',
            ]);

        $updatedName = 'Entity API Shelf Actualizada ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson("/api/shelves/{$shelf->id}", [
                'name' => $updatedName,
                'description' => 'Descripción actualizada.',
                'description_html' => '<p>Descripción actualizada.</p>',
                'books' => [],
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $updatedName,
            ]);

        $shelf->refresh();

        $this->assertSame($updatedName, $shelf->name);
        $this->assertFalse($shelf->contains($book));

        $this->actingAsApi($admin)
            ->deleteJson("/api/shelves/{$shelf->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entities', [
            'id' => $shelf->id,
            'type' => 'bookshelf',
            'deleted_at' => null,
        ]);
    }

    public function test_chapter_api_crud_movimiento_y_validaciones(): void
    {
        $admin = $this->userWithRole('admin');

        $sourceBook = $this->createBookViaHttp($admin, 'Entity API Libro Origen Capítulo ' . uniqid());
        $targetBook = $this->createBookViaHttp($admin, 'Entity API Libro Destino Capítulo ' . uniqid());

        $this->actingAsApi($admin)
            ->getJson('/api/chapters')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $this->actingAsApi($admin)
            ->postJson('/api/chapters', [
                'book_id' => $sourceBook->id,
            ])
            ->assertStatus(422);

        $chapterName = 'Entity API Capítulo ' . uniqid();

        $createResponse = $this->actingAsApi($admin)
            ->postJson('/api/chapters', [
                'book_id' => $sourceBook->id,
                'name' => $chapterName,
                'description' => 'Descripción API capítulo.',
                'description_html' => '<p>Descripción API capítulo.</p>',
                'priority' => 5,
                'tags' => [
                    [
                        'name' => 'Modulo',
                        'value' => 'Entities',
                    ],
                ],
            ]);

        $createResponse
            ->assertOk()
            ->assertJsonFragment([
                'name' => $chapterName,
                'book_id' => $sourceBook->id,
            ]);

        $chapter = Chapter::query()
            ->where('name', $chapterName)
            ->latest('id')
            ->firstOrFail();

        $this->actingAsApi($admin)
            ->getJson("/api/chapters/{$chapter->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $chapter->id,
                'name' => $chapterName,
            ])
            ->assertJsonStructure([
                'pages',
                'created_by',
                'updated_by',
                'owned_by',
            ]);

        $updatedName = 'Entity API Capítulo Movido ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson("/api/chapters/{$chapter->id}", [
                'book_id' => $targetBook->id,
                'name' => $updatedName,
                'description' => 'Capítulo movido por API.',
                'description_html' => '<p>Capítulo movido por API.</p>',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $updatedName,
                'book_id' => $targetBook->id,
            ]);

        $chapter->refresh();

        $this->assertSame($targetBook->id, $chapter->book_id);
        $this->assertSame($updatedName, $chapter->name);

        $this->actingAsApi($admin)
            ->deleteJson("/api/chapters/{$chapter->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entities', [
            'id' => $chapter->id,
            'type' => 'chapter',
            'deleted_at' => null,
        ]);
    }

    public function test_page_api_crud_movimiento_y_validaciones(): void
    {
        $admin = $this->userWithRole('admin');

        $sourceBook = $this->createBookViaHttp($admin, 'Entity API Libro Origen Página ' . uniqid());
        $targetBook = $this->createBookViaHttp($admin, 'Entity API Libro Destino Página ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $sourceBook, 'Entity API Capítulo Para Página ' . uniqid());

        $this->actingAsApi($admin)
            ->getJson('/api/pages')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $this->actingAsApi($admin)
            ->postJson('/api/pages', [
                'name' => 'Página inválida sin padre',
                'html' => '<p>Sin padre.</p>',
            ])
            ->assertStatus(422);

        $pageName = 'Entity API Página ' . uniqid();

        $createResponse = $this->actingAsApi($admin)
            ->postJson('/api/pages', [
                'chapter_id' => $chapter->id,
                'name' => $pageName,
                'html' => '<p>Contenido inicial desde PageApiController.</p>',
                'priority' => 2,
                'tags' => [
                    [
                        'name' => 'Tipo',
                        'value' => 'API',
                    ],
                ],
            ]);

        $createResponse
            ->assertOk()
            ->assertJsonFragment([
                'name' => $pageName,
            ]);

        $page = Page::query()
            ->where('name', $pageName)
            ->where('draft', false)
            ->latest('id')
            ->firstOrFail();

        $this->actingAsApi($admin)
            ->getJson("/api/pages/{$page->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $page->id,
                'name' => $pageName,
            ])
            ->assertJsonStructure([
                'comments',
                'tags',
                'created_by',
                'updated_by',
                'owned_by',
            ]);

        $updatedName = 'Entity API Página Actualizada ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson("/api/pages/{$page->id}", [
                'book_id' => $targetBook->id,
                'name' => $updatedName,
                'html' => '<p>Contenido actualizado y movido hacia libro raíz.</p>',
                'priority' => 9,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $updatedName,
                'book_id' => $targetBook->id,
            ]);

        $page->refresh();

        $this->assertSame($targetBook->id, $page->book_id);
        $this->assertNull($page->chapter_id);
        $this->assertSame($updatedName, $page->name);

        $this->actingAsApi($admin)
            ->deleteJson("/api/pages/{$page->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('entities', [
            'id' => $page->id,
            'type' => 'page',
            'deleted_at' => null,
        ]);
    }

    public function test_recycle_bin_api_lista_restaura_y_destruye(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $pageToRestore = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity API Página Para Restaurar ' . uniqid()
        );

        $this->actingAs($admin)
            ->delete($pageToRestore->getUrl())
            ->assertRedirect();

        $restoreDeletion = Deletion::query()
            ->latest('id')
            ->firstOrFail();

        $this->actingAsApi($admin)
            ->getJson('/api/recycle-bin')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $restoreDeletion->id,
                'deletable_type' => 'page',
                'deletable_id' => $pageToRestore->id,
            ]);

        $this->actingAsApi($admin)
            ->putJson("/api/recycle-bin/{$restoreDeletion->id}")
            ->assertOk()
            ->assertJson([
                'restore_count' => 1,
            ]);

        $this->assertDatabaseHas('entities', [
            'id' => $pageToRestore->id,
            'type' => 'page',
            'deleted_at' => null,
        ]);

        $pageToDestroy = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity API Página Para Destruir ' . uniqid()
        );

        $this->actingAs($admin)
            ->delete($pageToDestroy->getUrl())
            ->assertRedirect();

        $destroyDeletion = Deletion::query()
            ->latest('id')
            ->firstOrFail();

        $this->actingAsApi($admin)
            ->deleteJson("/api/recycle-bin/{$destroyDeletion->id}")
            ->assertOk()
            ->assertJson([
                'delete_count' => 1,
            ]);

        $this->assertDatabaseMissing('entities', [
            'id' => $pageToDestroy->id,
            'type' => 'page',
        ]);
    }
        public function test_bookshelf_api_update_sin_books_actualiza_solo_datos_basicos(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin, 'Entity API Libro Shelf Mantener ' . uniqid());

        $shelfName = 'Entity API Shelf Update Parcial ' . uniqid();

        $this->actingAsApi($admin)
            ->postJson('/api/shelves', [
                'name' => $shelfName,
                'description_html' => '<p>Descripción inicial.</p>',
                'books' => [$book->id],
            ])
            ->assertOk();

        $shelf = Bookshelf::query()
            ->where('name', $shelfName)
            ->latest('id')
            ->firstOrFail();

        $updatedName = 'Entity API Shelf Update Sin Books ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson('/api/shelves/' . $shelf->id, [
                'name' => $updatedName,
                'description_html' => '<p>Descripción actualizada sin enviar books.</p>',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $updatedName,
            ]);

        $shelf->refresh();

        $this->assertSame($updatedName, $shelf->name);
        $this->assertTrue($shelf->contains($book));
    }

    public function test_chapter_api_actualiza_datos_sin_mover_de_libro(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin, 'Entity API Libro Chapter Update Simple ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $book, 'Entity API Chapter Update Simple ' . uniqid());

        $updatedName = 'Entity API Chapter Solo Datos ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson('/api/chapters/' . $chapter->id, [
                'name' => $updatedName,
                'description' => 'Descripción actualizada sin mover.',
                'description_html' => '<p>Descripción actualizada sin mover.</p>',
                'priority' => 12,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $chapter->id,
                'name' => $updatedName,
                'book_id' => $book->id,
            ]);

        $chapter->refresh();

        $this->assertSame($updatedName, $chapter->name);
        $this->assertSame($book->id, $chapter->book_id);
        $this->assertSame(12, $chapter->priority);
    }

    public function test_page_api_crea_pagina_en_raiz_de_libro_y_luego_la_mueve_a_capitulo(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin, 'Entity API Libro Página Raíz ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $book, 'Entity API Capítulo Destino Página Raíz ' . uniqid());

        $pageName = 'Entity API Página En Raíz ' . uniqid();

        $this->actingAsApi($admin)
            ->postJson('/api/pages', [
                'book_id' => $book->id,
                'name' => $pageName,
                'html' => '<p>Página creada directamente en la raíz del libro.</p>',
                'priority' => 3,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $pageName,
                'book_id' => $book->id,
                'chapter_id' => null,
            ]);

        $page = Page::query()
            ->where('name', $pageName)
            ->where('draft', false)
            ->latest('id')
            ->firstOrFail();

        $updatedName = 'Entity API Página Movida A Capítulo ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson('/api/pages/' . $page->id, [
                'chapter_id' => $chapter->id,
                'name' => $updatedName,
                'html' => '<p>Página movida desde raíz hacia capítulo.</p>',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => $updatedName,
                'book_id' => $book->id,
                'chapter_id' => $chapter->id,
            ]);

        $page->refresh();

        $this->assertSame($book->id, $page->book_id);
        $this->assertSame($chapter->id, $page->chapter_id);
        $this->assertSame($updatedName, $page->name);
    }

    public function test_page_api_actualiza_solo_contenido_sin_mover_parent(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin);
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'Entity API Página Update Simple ' . uniqid(),
            '<p>Contenido inicial API.</p>'
        );

        $updatedName = 'Entity API Página Solo Update ' . uniqid();

        $this->actingAsApi($admin)
            ->putJson('/api/pages/' . $page->id, [
                'name' => $updatedName,
                'html' => '<p>Contenido actualizado por API sin mover.</p>',
                'priority' => 15,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $page->id,
                'name' => $updatedName,
                'book_id' => $book->id,
                'chapter_id' => $chapter->id,
            ]);

        $page->refresh();

        $this->assertSame($updatedName, $page->name);
        $this->assertSame($chapter->id, $page->chapter_id);
        $this->assertSame(15, $page->priority);
    }

    public function test_recycle_bin_api_lista_deletions_de_book_y_chapter_con_datos_relacionados(): void
    {
        $admin = $this->userWithRole('admin');

        $bookToDelete = $this->createBookViaHttp($admin, 'Entity API Libro Papelera Con Hijos ' . uniqid());
        $chapterInBook = $this->createChapterViaHttp($admin, $bookToDelete);
        $this->createPublishedPageViaHttp($admin, $chapterInBook);

        $this->actingAs($admin)
            ->delete($bookToDelete->getUrl())
            ->assertRedirect();

        $deletedBook = Deletion::query()
            ->where('deletable_type', '=', 'book')
            ->latest('id')
            ->firstOrFail();

        $bookForChapterDelete = $this->createBookViaHttp($admin, 'Entity API Libro Padre De Chapter Delete ' . uniqid());
        $chapterToDelete = $this->createChapterViaHttp($admin, $bookForChapterDelete, 'Entity API Chapter Papelera ' . uniqid());
        $this->createPublishedPageViaHttp($admin, $chapterToDelete);

        $this->actingAs($admin)
            ->delete($chapterToDelete->getUrl())
            ->assertRedirect();

        $deletedChapter = Deletion::query()
            ->where('deletable_type', '=', 'chapter')
            ->latest('id')
            ->firstOrFail();

        $this->actingAsApi($admin)
            ->getJson('/api/recycle-bin')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $deletedBook->id,
                'deletable_type' => 'book',
            ])
            ->assertJsonFragment([
                'id' => $deletedChapter->id,
                'deletable_type' => 'chapter',
            ]);
    }
}