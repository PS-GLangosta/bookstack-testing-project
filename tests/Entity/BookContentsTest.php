<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\BookContents;
use Tests\TestCase;

class BookContentsTest extends TestCase
{
    // getLastPriority()

    public function test_get_last_priority_returns_at_least_1_for_empty_book(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Empty Priority Book']);
        $contents = new BookContents($book);
        $this->assertGreaterThanOrEqual(1, $contents->getLastPriority());
    }

    public function test_get_last_priority_reflects_existing_chapter_priority(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Chapter Priority Book']);
        $chapter = $this->entities->newChapter(['name' => 'High Priority Chapter', 'priority' => 10], $book);

        $contents = new BookContents($book->fresh());
        $this->assertGreaterThanOrEqual(10, $contents->getLastPriority());
    }

    public function test_get_last_priority_increases_after_each_chapter_creation(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Growing Priority Book']);
        $contents = new BookContents($book->fresh());
        $initial = $contents->getLastPriority();

        $this->entities->newChapter(['name' => 'Added Chapter'], $book);

        $contents2 = new BookContents($book->fresh());
        $this->assertGreaterThan($initial, $contents2->getLastPriority());
    }

    // getTree()

    public function test_get_tree_returns_non_empty_collection_for_book_with_contents(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $tree = (new BookContents($book))->getTree();
        $this->assertGreaterThan(0, $tree->count());
    }

    public function test_get_tree_excludes_draft_pages_by_default(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $tree = (new BookContents($book))->getTree(false);

        $containsDraft = false;
        foreach ($tree as $item) {
            if ($item instanceof Page && $item->draft) {
                $containsDraft = true;
            }
        }
        $this->assertFalse($containsDraft);
    }

    public function test_get_tree_contains_chapter_instances(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $tree = (new BookContents($book))->getTree();

        $hasChapter = $tree->contains(fn($item) => $item instanceof Chapter);
        $this->assertTrue($hasChapter);
    }

    public function test_get_tree_items_have_book_relation_set(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $tree = (new BookContents($book))->getTree();

        foreach ($tree as $item) {
            $this->assertSame($book->id, $item->getRelation('book')->id);
        }
    }

    public function test_get_tree_chapters_include_visible_pages_attribute(): void
    {
        $this->asAdmin();
        $book = $this->entities->bookHasChaptersAndPages();
        $tree = (new BookContents($book))->getTree();

        $chapters = $tree->filter(fn($item) => $item instanceof Chapter);
        $this->assertGreaterThan(0, $chapters->count());

        foreach ($chapters as $chapter) {
            $this->assertTrue($chapter->hasAttribute('visible_pages'));
        }
    }

    public function test_get_tree_returns_empty_collection_for_book_with_no_visible_content(): void
    {
        $this->asAdmin();
        $book = $this->entities->newBook(['name' => 'Empty Tree Book']);
        $tree = (new BookContents($book))->getTree();
        $this->assertCount(0, $tree);
    }
}
