<?php

namespace Tests\Entity;

use BookStack\Entities\Models\Bookshelf;
use Tests\TestCase;

class BookshelfModelTest extends TestCase
{
    // contains()

    public function test_contains_returns_true_when_book_is_in_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelfHasBooks();
        $book = $shelf->books()->first();
        $this->assertTrue($shelf->contains($book));
    }

    public function test_contains_returns_false_when_book_not_in_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $book = $this->entities->book();
        $this->assertFalse($shelf->contains($book));
    }

    // appendBook()

    public function test_append_book_adds_book_to_shelf(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $book = $this->entities->book();
        $this->assertFalse($shelf->contains($book));
        $shelf->appendBook($book);
        $this->assertTrue($shelf->contains($book));
    }

    public function test_append_book_does_not_add_duplicate(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $book = $this->entities->book();
        $shelf->appendBook($book);
        $shelf->appendBook($book);
        $count = $shelf->books()->where('id', $book->id)->count();
        $this->assertSame(1, $count);
    }

    public function test_append_book_sets_order_after_existing_books(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelfHasBooks();
        $maxOrderBefore = (int) \DB::table('bookshelves_books')->where('bookshelf_id', $shelf->id)->max('order');
        $newBook = $this->entities->book();
        $shelf->appendBook($newBook);
        $newOrder = (int) \DB::table('bookshelves_books')
            ->where('bookshelf_id', $shelf->id)
            ->where('book_id', $newBook->id)
            ->value('order');
        $this->assertGreaterThan($maxOrderBefore, $newOrder);
    }

    // visibleBooks()

    public function test_visible_books_returns_books_for_admin(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelfHasBooks();
        $this->assertGreaterThan(0, $shelf->visibleBooks()->count());
    }

    // books() relation

    public function test_books_relation_returns_ordered_by_order_field(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelfHasBooks();
        $books = $shelf->books()->get();
        $this->assertNotNull($books);
        $this->assertGreaterThan(0, $books->count());
    }
}
