<?php

namespace Tests\Entity;

use BookStack\Activity\ActivityType;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Exceptions\MoveOperationException;
use Tests\TestCase;

class ChapterRepoTest extends TestCase
{
    protected ChapterRepo $chapterRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chapterRepo = app(ChapterRepo::class);
    }

    // create()

    public function test_create_returns_chapter_instance(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'New Chapter'], $book);
        $this->assertInstanceOf(Chapter::class, $chapter);
    }

    public function test_create_assigns_correct_parent_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Parented Chapter'], $book);
        $this->assertSame($book->id, $chapter->book_id);
    }

    public function test_create_generates_non_empty_slug(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Chapter Slug Test'], $book);
        $this->assertNotEmpty($chapter->slug);
    }

    public function test_create_persists_name_to_database(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $this->chapterRepo->create(['name' => 'Persisted Chapter'], $book);
        $this->assertDatabaseHasEntityData('chapter', ['name' => 'Persisted Chapter']);
    }

    public function test_create_sets_priority_higher_than_previous_chapter(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter1 = $this->chapterRepo->create(['name' => 'First Chapter'], $book);
        $chapter2 = $this->chapterRepo->create(['name' => 'Second Chapter'], $book);
        $this->assertGreaterThan($chapter1->priority, $chapter2->priority);
    }

    public function test_create_logs_chapter_create_activity(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Activity Chapter'], $book);
        $this->assertActivityExists(ActivityType::CHAPTER_CREATE, $chapter);
    }

    // update()

    public function test_update_changes_chapter_name(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Old Chapter Name'], $book);
        $updated = $this->chapterRepo->update($chapter, ['name' => 'New Chapter Name']);
        $this->assertSame('New Chapter Name', $updated->name);
    }

    public function test_update_persists_description(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Chapter With Desc'], $book);
        $this->chapterRepo->update($chapter, [
            'name'        => 'Chapter With Desc',
            'description' => 'Updated description text',
        ]);
        $this->assertSame('Updated description text', $chapter->fresh()->description);
    }

    public function test_update_logs_chapter_update_activity(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Update Activity Chapter'], $book);
        $this->chapterRepo->update($chapter, ['name' => 'Updated Chapter Name']);
        $this->assertActivityExists(ActivityType::CHAPTER_UPDATE, $chapter);
    }

    // destroy()

    public function test_destroy_soft_deletes_the_chapter(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Chapter To Delete'], $book);
        $chapterId = $chapter->id;
        $this->chapterRepo->destroy($chapter);
        $this->assertDatabaseMissing('entities', ['id' => $chapterId, 'deleted_at' => null, 'type' => 'chapter']);
    }

    public function test_destroy_logs_chapter_delete_activity(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Delete Activity Chapter'], $book);
        $this->chapterRepo->destroy($chapter);
        $this->assertActivityExists(ActivityType::CHAPTER_DELETE);
    }

    // move()

    public function test_move_changes_chapter_parent_book(): void
    {
        $this->asAdmin();
        $book1 = $this->entities->book();
        $book2 = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Movable Chapter'], $book1);

        $this->chapterRepo->move($chapter, "book:{$book2->id}");

        $this->assertSame($book2->id, $chapter->fresh()->book_id);
    }

    public function test_move_returns_the_new_parent_book(): void
    {
        $this->asAdmin();
        $book1 = $this->entities->book();
        $book2 = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Chapter To Move'], $book1);

        $result = $this->chapterRepo->move($chapter, "book:{$book2->id}");

        $this->assertSame($book2->id, $result->id);
    }

    public function test_move_logs_chapter_move_activity(): void
    {
        $this->asAdmin();
        $book1 = $this->entities->book();
        $book2 = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Move Activity Chapter'], $book1);

        $this->chapterRepo->move($chapter, "book:{$book2->id}");

        $this->assertActivityExists(ActivityType::CHAPTER_MOVE, $chapter);
    }

    public function test_move_throws_exception_when_book_not_found(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $chapter = $this->chapterRepo->create(['name' => 'Unmovable Chapter'], $book);

        $this->expectException(MoveOperationException::class);
        $this->chapterRepo->move($chapter, 'book:9999999');
    }
}
