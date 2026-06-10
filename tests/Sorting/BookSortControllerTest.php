<?php

namespace Tests\Sorting;

use Tests\TestCase;

class BookSortControllerTest extends TestCase
{
    // show()

    public function test_show_displays_sort_page_for_book(): void
    {
        $book = $this->entities->bookHasChaptersAndPages();
        $this->asAdmin()->get($book->getUrl('/sort'))->assertOk()->assertSee($book->name);
    }

    public function test_show_denied_to_viewer(): void
    {
        $book = $this->entities->book();
        $this->asViewer()->get($book->getUrl('/sort'))->assertRedirect();
    }

    public function test_show_returns_404_for_nonexistent_book(): void
    {
        $this->asAdmin()->get('/books/nonexistent-book-xyz/sort')->assertNotFound();
    }

    // showItem()

    public function test_show_item_returns_sort_box_partial(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->get($book->getUrl('/sort-item'))->assertOk();
    }

    // update()

    public function test_update_redirects_to_book_after_sort(): void
    {
        $book = $this->entities->bookHasChaptersAndPages();
        $chapter = $book->chapters()->first();
        $page = $book->pages()->whereNull('chapter_id')->first();

        $sortTree = json_encode([
            ['id' => $chapter->id, 'sort' => 0, 'parentChapter' => false, 'type' => 'chapter', 'book' => $book->id],
            ['id' => $page->id, 'sort' => 1, 'parentChapter' => false, 'type' => 'page', 'book' => $book->id],
        ]);

        $resp = $this->asAdmin()->put($book->getUrl('/sort'), [
            'sort-tree' => $sortTree,
        ]);
        $resp->assertRedirect($book->getUrl());
    }

    public function test_update_denied_to_viewer(): void
    {
        $book = $this->entities->book();
        $this->asViewer()->put($book->getUrl('/sort'), ['sort-tree' => '[]'])->assertRedirect();
    }

    public function test_update_without_sort_tree_still_redirects(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->put($book->getUrl('/sort'), [])->assertRedirect($book->getUrl());
    }
}
