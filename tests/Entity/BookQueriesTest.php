<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Queries\BookQueries;
use BookStack\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class BookQueriesTest extends TestCase
{
    protected BookQueries $bookQueries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookQueries = app(BookQueries::class);
    }

    // start()

    public function test_start_returns_eloquent_builder_for_book(): void
    {
        $builder = $this->bookQueries->start();
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(Book::class, $builder->getModel());
    }

    // findVisibleById()

    public function test_find_visible_by_id_returns_correct_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $found = $this->bookQueries->findVisibleById($book->id);
        $this->assertNotNull($found);
        $this->assertSame($book->id, $found->id);
    }

    public function test_find_visible_by_id_returns_null_for_nonexistent_id(): void
    {
        $this->asAdmin();
        $result = $this->bookQueries->findVisibleById(999999);
        $this->assertNull($result);
    }

    // findVisibleByIdOrFail()

    public function test_find_visible_by_id_or_fail_returns_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $found = $this->bookQueries->findVisibleByIdOrFail($book->id);
        $this->assertSame($book->id, $found->id);
    }

    public function test_find_visible_by_id_or_fail_throws_for_nonexistent_id(): void
    {
        $this->asAdmin();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->bookQueries->findVisibleByIdOrFail(999999);
    }

    // findVisibleBySlugOrFail()

    public function test_find_visible_by_slug_or_fail_returns_matching_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $found = $this->bookQueries->findVisibleBySlugOrFail($book->slug);
        $this->assertSame($book->id, $found->id);
    }

    public function test_find_visible_by_slug_or_fail_throws_not_found_exception(): void
    {
        $this->asAdmin();
        $this->expectException(NotFoundException::class);
        $this->bookQueries->findVisibleBySlugOrFail('this-slug-does-not-exist-xyz');
    }

    // visibleForList()

    public function test_visible_for_list_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->bookQueries->visibleForList());
    }

    public function test_visible_for_list_returns_books(): void
    {
        $this->asAdmin();
        $results = $this->bookQueries->visibleForList()->get();
        $this->assertGreaterThan(0, $results->count());
        $this->assertInstanceOf(Book::class, $results->first());
    }

    // visibleForContent()

    public function test_visible_for_content_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->bookQueries->visibleForContent());
    }

    // visibleForListWithCover()

    public function test_visible_for_list_with_cover_returns_results(): void
    {
        $this->asAdmin();
        $results = $this->bookQueries->visibleForListWithCover()->get();
        $this->assertNotNull($results);
        $this->assertGreaterThan(0, $results->count());
    }

    // recentlyViewedForCurrentUser()

    public function test_recently_viewed_for_current_user_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->bookQueries->recentlyViewedForCurrentUser());
    }

    // popularForList()

    public function test_popular_for_list_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->bookQueries->popularForList());
    }
}
