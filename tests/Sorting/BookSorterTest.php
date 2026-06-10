<?php

namespace Tests\Sorting;

use BookStack\Sorting\BookSortMap;
use BookStack\Sorting\BookSortMapItem;
use BookStack\Sorting\BookSorter;
use BookStack\Sorting\SortRule;
use BookStack\Sorting\SortRuleOperation;
use Tests\TestCase;

class BookSorterTest extends TestCase
{
    protected BookSorter $sorter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sorter = $this->app->make(BookSorter::class);
    }

    protected function createSortRule(string $name, SortRuleOperation $op): SortRule
    {
        $rule = new SortRule();
        $rule->name = $name;
        $rule->setOperations([$op]);
        $rule->save();
        return $rule;
    }

    // runBookAutoSort()

    public function test_run_book_auto_sort_is_noop_when_book_has_no_sort_rule(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $this->assertNull($book->sort_rule_id);

        $chapter = $book->chapters()->first();
        $originalPriority = $chapter->priority;

        $this->sorter->runBookAutoSort($book);

        $this->assertSame($originalPriority, $chapter->fresh()->priority);
    }

    public function test_run_book_auto_sort_applies_sort_rule_to_chapters(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();

        $rule = $this->createSortRule('Sort by name asc', SortRuleOperation::NameAsc);
        $book->sort_rule_id = $rule->id;
        $book->save();

        $this->sorter->runBookAutoSort($book);

        $chapters = $book->chapters()->orderBy('priority')->get(['id', 'name', 'priority']);
        for ($i = 0; $i < $chapters->count() - 1; $i++) {
            $this->assertLessThanOrEqual(0, strcmp($chapters[$i]->name, $chapters[$i + 1]->name));
        }
    }

    public function test_run_book_auto_sort_for_all_with_set_sorts_assigned_books(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();

        $rule = $this->createSortRule('Sort rule for batch', SortRuleOperation::NameAsc);
        $book->sort_rule_id = $rule->id;
        $book->save();

        $this->sorter->runBookAutoSortForAllWithSet($rule);

        $chapters = $book->chapters()->orderBy('priority')->get();
        $this->assertGreaterThan(0, $chapters->count());
    }

    // sortUsingMap()

    public function test_sort_using_map_updates_priorities(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $chapters = $book->chapters()->orderBy('priority')->get();

        if ($chapters->count() < 2) {
            $this->markTestSkipped('Need at least 2 chapters');
        }

        $firstChapter  = $chapters[0];
        $secondChapter = $chapters[1];

        $map = new BookSortMap();
        $map->addItem(new BookSortMapItem($firstChapter->id,  10, null, 'chapter', $book->id));
        $map->addItem(new BookSortMapItem($secondChapter->id, 5,  null, 'chapter', $book->id));

        $this->sorter->sortUsingMap($map);

        $this->assertSame(10, $firstChapter->fresh()->priority);
        $this->assertSame(5, $secondChapter->fresh()->priority);
    }

    public function test_sort_using_map_returns_involved_books(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $chapter = $book->chapters()->first();

        $map = new BookSortMap();
        $map->addItem(new BookSortMapItem($chapter->id, 1, null, 'chapter', $book->id));

        $result = $this->sorter->sortUsingMap($map);

        $this->assertIsArray($result);
        $bookIds = array_map(fn ($b) => $b->id, $result);
        $this->assertContains($book->id, $bookIds);
    }

    public function test_sort_using_map_with_empty_map_returns_empty_array(): void
    {
        $this->asAdmin();
        $map = new BookSortMap();
        $result = $this->sorter->sortUsingMap($map);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_sort_using_map_can_move_chapter_to_different_book(): void
    {
        $this->asAdmin();
        $sourceBook = $this->entities->bookHasChaptersAndPages();
        $targetBook = $this->entities->book();
        $chapter = $sourceBook->chapters()->first();

        $map = new BookSortMap();
        $map->addItem(new BookSortMapItem($chapter->id, 1, null, 'chapter', $targetBook->id));

        $this->sorter->sortUsingMap($map);

        $this->assertSame($targetBook->id, $chapter->fresh()->book_id);
    }
}
