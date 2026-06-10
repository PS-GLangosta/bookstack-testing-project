<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Queries\ChapterQueries;
use BookStack\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class ChapterQueriesTest extends TestCase
{
    protected ChapterQueries $chapterQueries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chapterQueries = app(ChapterQueries::class);
    }

    // start()

    public function test_start_returns_eloquent_builder_for_chapter(): void
    {
        $builder = $this->chapterQueries->start();
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(Chapter::class, $builder->getModel());
    }

    // findVisibleById()

    public function test_find_visible_by_id_returns_matching_chapter(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $found = $this->chapterQueries->findVisibleById($chapter->id);
        $this->assertNotNull($found);
        $this->assertSame($chapter->id, $found->id);
    }

    public function test_find_visible_by_id_returns_null_for_nonexistent_id(): void
    {
        $this->asAdmin();
        $result = $this->chapterQueries->findVisibleById(999999);
        $this->assertNull($result);
    }

    // findVisibleByIdOrFail()

    public function test_find_visible_by_id_or_fail_returns_chapter(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $found = $this->chapterQueries->findVisibleByIdOrFail($chapter->id);
        $this->assertSame($chapter->id, $found->id);
    }

    public function test_find_visible_by_id_or_fail_throws_for_nonexistent_id(): void
    {
        $this->asAdmin();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->chapterQueries->findVisibleByIdOrFail(999999);
    }

    // findVisibleBySlugsOrFail()

    public function test_find_visible_by_slugs_or_fail_returns_chapter(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;
        $found = $this->chapterQueries->findVisibleBySlugsOrFail($book->slug, $chapter->slug);
        $this->assertSame($chapter->id, $found->id);
    }

    public function test_find_visible_by_slugs_or_fail_loads_book_relation(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;
        $found = $this->chapterQueries->findVisibleBySlugsOrFail($book->slug, $chapter->slug);
        $this->assertTrue($found->relationLoaded('book'));
    }

    public function test_find_visible_by_slugs_or_fail_throws_not_found_exception(): void
    {
        $this->asAdmin();
        $this->expectException(NotFoundException::class);
        $this->chapterQueries->findVisibleBySlugsOrFail('no-such-book', 'no-such-chapter');
    }

    // visibleForList()

    public function test_visible_for_list_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->chapterQueries->visibleForList());
    }

    public function test_visible_for_list_returns_chapters(): void
    {
        $this->asAdmin();
        $results = $this->chapterQueries->visibleForList()->get();
        $this->assertGreaterThan(0, $results->count());
        $this->assertInstanceOf(Chapter::class, $results->first());
    }

    // visibleForContent()

    public function test_visible_for_content_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->chapterQueries->visibleForContent());
    }

    // usingSlugs()

    public function test_using_slugs_returns_chapter_matching_both_slugs(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;

        $results = $this->chapterQueries->usingSlugs($book->slug, $chapter->slug)->get();

        $this->assertCount(1, $results);
        $this->assertSame($chapter->id, $results->first()->id);
    }

    public function test_using_slugs_returns_empty_for_wrong_book_slug(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();

        $results = $this->chapterQueries->usingSlugs('nonexistent-book', $chapter->slug)->get();

        $this->assertCount(0, $results);
    }
}
