<?php

namespace Tests\Entity;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Repos\BookRepo;
use Tests\TestCase;

class BookRepoTest extends TestCase
{
    protected BookRepo $bookRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookRepo = app(BookRepo::class);
    }

    // create()

    public function test_create_returns_book_instance(): void
    {
        $this->asAdmin();
        $book = $this->bookRepo->create(['name' => 'New Repo Book']);
        $this->assertInstanceOf(Book::class, $book);
    }

    public function test_create_persists_name_to_database(): void
    {
        $this->asAdmin();
        $book = $this->bookRepo->create(['name' => 'Persisted Book Name']);
        $this->assertDatabaseHasEntityData('book', ['name' => 'Persisted Book Name']);
    }

    public function test_create_generates_non_empty_slug(): void
    {
        $this->asAdmin();
        $book = $this->bookRepo->create(['name' => 'Slug Generation Book']);
        $this->assertNotEmpty($book->slug);
        $this->assertStringNotContainsString(' ', $book->slug);
    }

    public function test_create_assigns_description(): void
    {
        $this->asAdmin();
        $book = $this->bookRepo->create([
            'name'        => 'Book With Desc',
            'description' => 'A meaningful description',
        ]);
        $this->assertSame('A meaningful description', $book->description);
    }

    public function test_create_logs_book_create_activity(): void
    {
        $this->asAdmin();
        $book = $this->bookRepo->create(['name' => 'Activity Create Book']);
        $this->assertActivityExists(ActivityType::BOOK_CREATE, $book);
    }

    public function test_create_assigns_current_user_as_owner(): void
    {
        $user = $this->users->editor();
        $this->actingAs($user);
        $book = $this->bookRepo->create(['name' => 'Owner Check Book']);
        $this->assertSame($user->id, $book->owned_by);
    }

    // update()

    public function test_update_changes_book_name(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Before Update']);
        $updated = $this->bookRepo->update($book, ['name' => 'After Update']);
        $this->assertSame('After Update', $updated->name);
    }

    public function test_update_regenerates_slug_when_name_changes(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Original Slug Name']);
        $oldSlug = $book->slug;
        $this->bookRepo->update($book, ['name' => 'Completely Different Name']);
        $this->assertNotSame($oldSlug, $book->fresh()->slug);
    }

    public function test_update_logs_book_update_activity(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Update Activity Book']);
        $this->bookRepo->update($book, ['name' => 'Updated Name']);
        $this->assertActivityExists(ActivityType::BOOK_UPDATE, $book);
    }

    public function test_update_persists_description_html(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'HTML Desc Book']);
        $this->bookRepo->update($book, [
            'name'             => 'HTML Desc Book',
            'description_html' => '<p>Rich description</p>',
        ]);
        $fresh = $book->fresh();
        $this->assertStringContainsString('Rich description', $fresh->description_html);
    }

    // destroy()

    public function test_destroy_soft_deletes_the_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Book To Destroy']);
        $bookId = $book->id;
        $this->bookRepo->destroy($book);
        $this->assertDatabaseMissing('entities', ['id' => $bookId, 'deleted_at' => null, 'type' => 'book']);
    }

    public function test_destroy_logs_book_delete_activity(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Delete Activity Book']);
        $this->bookRepo->destroy($book);
        $this->assertActivityExists(ActivityType::BOOK_DELETE);
    }

    public function test_destroy_removes_book_from_visible_queries(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Invisible After Delete']);
        $bookId = $book->id;
        $this->bookRepo->destroy($book);
        $found = Book::query()->find($bookId);
        $this->assertNull($found);
    }
}
