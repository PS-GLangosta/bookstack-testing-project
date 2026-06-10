<?php

namespace Tests\Entity;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Repos\BookshelfRepo;
use Tests\TestCase;

class BookshelfRepoTest extends TestCase
{
    protected BookshelfRepo $shelfRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shelfRepo = app(BookshelfRepo::class);
    }

    // create()

    public function test_create_returns_bookshelf_instance(): void
    {
        $this->asAdmin();
        $shelf = $this->shelfRepo->create(['name' => 'New Shelf'], []);
        $this->assertInstanceOf(Bookshelf::class, $shelf);
    }

    public function test_create_persists_name_to_database(): void
    {
        $this->asAdmin();
        $this->shelfRepo->create(['name' => 'Persisted Shelf'], []);
        $this->assertDatabaseHasEntityData('bookshelf', ['name' => 'Persisted Shelf']);
    }

    public function test_create_generates_non_empty_slug(): void
    {
        $this->asAdmin();
        $shelf = $this->shelfRepo->create(['name' => 'Slug Shelf Test'], []);
        $this->assertNotEmpty($shelf->slug);
        $this->assertStringNotContainsString(' ', $shelf->slug);
    }

    public function test_create_with_description_persists_description(): void
    {
        $this->asAdmin();
        $shelf = $this->shelfRepo->create([
            'name'        => 'Shelf With Description',
            'description' => 'My shelf description',
        ], []);
        $this->assertSame('My shelf description', $shelf->description);
    }

    public function test_create_logs_bookshelf_create_activity(): void
    {
        $this->asAdmin();
        $shelf = $this->shelfRepo->create(['name' => 'Activity Shelf'], []);
        $this->assertActivityExists(ActivityType::BOOKSHELF_CREATE, $shelf);
    }

    public function test_create_assigns_given_books(): void
    {
        $this->asAdmin();
        $book1 = $this->entities->book();
        $book2 = $this->entities->book();
        $shelf = $this->shelfRepo->create(['name' => 'Shelf With Books'], [$book1->id, $book2->id]);
        $shelfBookIds = $shelf->books()->pluck('id')->toArray();
        $this->assertContains($book1->id, $shelfBookIds);
        $this->assertContains($book2->id, $shelfBookIds);
    }

    public function test_create_with_empty_books_creates_shelf_without_books(): void
    {
        $this->asAdmin();
        $shelf = $this->shelfRepo->create(['name' => 'Empty Books Shelf'], []);
        $this->assertCount(0, $shelf->books()->get());
    }

    // update()

    public function test_update_changes_shelf_name(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->newShelf(['name' => 'Before Update', 'description' => '']);
        $updated = $this->shelfRepo->update($shelf, ['name' => 'After Update'], null);
        $this->assertSame('After Update', $updated->name);
    }

    public function test_update_logs_bookshelf_update_activity(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->newShelf(['name' => 'Update Activity Shelf', 'description' => '']);
        $this->shelfRepo->update($shelf, ['name' => 'Updated Shelf'], null);
        $this->assertActivityExists(ActivityType::BOOKSHELF_UPDATE, $shelf);
    }

    public function test_update_syncs_books_when_book_ids_provided(): void
    {
        $this->asAdmin();
        $book1 = $this->entities->book();
        $book2 = $this->entities->book();
        $shelf = $this->shelfRepo->create(['name' => 'Sync Shelf'], [$book1->id]);
        $this->shelfRepo->update($shelf, ['name' => 'Sync Shelf'], [$book2->id]);
        $shelfBookIds = $shelf->fresh()->books()->pluck('id')->toArray();
        $this->assertContains($book2->id, $shelfBookIds);
        $this->assertNotContains($book1->id, $shelfBookIds);
    }

    public function test_update_does_not_change_books_when_null_passed(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $shelf = $this->shelfRepo->create(['name' => 'No Book Change Shelf'], [$book->id]);
        $this->shelfRepo->update($shelf, ['name' => 'No Book Change Shelf'], null);
        $this->assertContains($book->id, $shelf->fresh()->books()->pluck('id')->toArray());
    }

    // destroy()

    public function test_destroy_soft_deletes_the_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->newShelf(['name' => 'Shelf To Delete', 'description' => '']);
        $shelfId = $shelf->id;
        $this->shelfRepo->destroy($shelf);
        $this->assertDatabaseMissing('entities', ['id' => $shelfId, 'deleted_at' => null, 'type' => 'bookshelf']);
    }

    public function test_destroy_logs_bookshelf_delete_activity(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->newShelf(['name' => 'Delete Activity Shelf', 'description' => '']);
        $this->shelfRepo->destroy($shelf);
        $this->assertActivityExists(ActivityType::BOOKSHELF_DELETE);
    }
}
