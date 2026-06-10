<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Queries\BookshelfQueries;
use BookStack\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class BookshelfQueriesTest extends TestCase
{
    protected BookshelfQueries $queries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queries = app(BookshelfQueries::class);
    }

    public function test_start_returns_eloquent_builder_for_bookshelf(): void
    {
        $builder = $this->queries->start();
        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertInstanceOf(Bookshelf::class, $builder->getModel());
    }

    public function test_find_visible_by_id_returns_matching_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $found = $this->queries->findVisibleById($shelf->id);
        $this->assertNotNull($found);
        $this->assertSame($shelf->id, $found->id);
    }

    public function test_find_visible_by_id_returns_null_for_nonexistent(): void
    {
        $this->asAdmin();
        $this->assertNull($this->queries->findVisibleById(999999));
    }

    public function test_find_visible_by_id_or_fail_returns_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $found = $this->queries->findVisibleByIdOrFail($shelf->id);
        $this->assertSame($shelf->id, $found->id);
    }

    public function test_find_visible_by_id_or_fail_throws_not_found_for_missing(): void
    {
        $this->asAdmin();
        $this->expectException(NotFoundException::class);
        $this->queries->findVisibleByIdOrFail(999999);
    }

    public function test_find_visible_by_slug_or_fail_returns_matching_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $found = $this->queries->findVisibleBySlugOrFail($shelf->slug);
        $this->assertSame($shelf->id, $found->id);
    }

    public function test_find_visible_by_slug_or_fail_throws_for_missing_slug(): void
    {
        $this->asAdmin();
        $this->expectException(NotFoundException::class);
        $this->queries->findVisibleBySlugOrFail('slug-does-not-exist-xyz');
    }

    public function test_visible_for_list_returns_builder_with_shelves(): void
    {
        $this->asAdmin();
        $results = $this->queries->visibleForList()->get();
        $this->assertGreaterThan(0, $results->count());
        $this->assertInstanceOf(Bookshelf::class, $results->first());
    }

    public function test_visible_for_content_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->queries->visibleForContent());
    }

    public function test_visible_for_list_with_cover_returns_results(): void
    {
        $this->asAdmin();
        $results = $this->queries->visibleForListWithCover()->get();
        $this->assertNotNull($results);
    }

    public function test_recently_viewed_for_current_user_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->queries->recentlyViewedForCurrentUser());
    }

    public function test_popular_for_list_returns_builder(): void
    {
        $this->asAdmin();
        $this->assertInstanceOf(Builder::class, $this->queries->popularForList());
    }
}
