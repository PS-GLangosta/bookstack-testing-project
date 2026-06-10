<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Chapter;
use Tests\TestCase;

class BookModelTest extends TestCase
{
    // Book::getUrl()

    public function test_book_get_url_contains_books_path_and_slug(): void
    {
        $book = new Book();
        $book->slug = 'my-test-book';

        $this->assertStringContainsString('/books/my-test-book', $book->getUrl());
    }

    public function test_book_get_url_with_path_appends_segment(): void
    {
        $book = new Book();
        $book->slug = 'my-book';

        $this->assertStringContainsString('/books/my-book/edit', $book->getUrl('/edit'));
    }

    public function test_book_get_url_trims_leading_and_trailing_slashes_from_path(): void
    {
        $book = new Book();
        $book->slug = 'my-book';

        $url = $book->getUrl('/edit/');
        $this->assertStringContainsString('/books/my-book/edit', $url);
        $this->assertStringNotContainsString('/books/my-book/edit/', $url);
    }

    public function test_book_get_url_encodes_slug_with_special_characters(): void
    {
        $book = new Book();
        $book->slug = 'my book';

        $url = $book->getUrl();
        $this->assertStringNotContainsString('my book', $url);
        $this->assertStringContainsString('my', $url);
    }

    public function test_book_search_factor_is_1_point_2(): void
    {
        $this->assertSame(1.2, (new Book())->searchFactor);
    }

    // Bookshelf::getUrl()

    public function test_bookshelf_get_url_contains_shelves_path_and_slug(): void
    {
        $shelf = new Bookshelf();
        $shelf->slug = 'my-shelf';

        $this->assertStringContainsString('/shelves/my-shelf', $shelf->getUrl());
    }

    public function test_bookshelf_get_url_with_path_appends_segment(): void
    {
        $shelf = new Bookshelf();
        $shelf->slug = 'my-shelf';

        $this->assertStringContainsString('/shelves/my-shelf/edit', $shelf->getUrl('/edit'));
    }

    public function test_bookshelf_search_factor_is_1_point_2(): void
    {
        $this->assertSame(1.2, (new Bookshelf())->searchFactor);
    }

    // Chapter::getUrl()

    public function test_chapter_get_url_uses_book_slug_attribute(): void
    {
        $chapter = new Chapter();
        $chapter->slug = 'my-chapter';
        $chapter->book_slug = 'my-book';

        $this->assertStringContainsString('/books/my-book/chapter/my-chapter', $chapter->getUrl());
    }

    public function test_chapter_get_url_with_path_appends_segment(): void
    {
        $chapter = new Chapter();
        $chapter->slug = 'my-chapter';
        $chapter->book_slug = 'my-book';

        $this->assertStringContainsString('/books/my-book/chapter/my-chapter/edit', $chapter->getUrl('/edit'));
    }

    public function test_chapter_get_url_trims_path_slashes(): void
    {
        $chapter = new Chapter();
        $chapter->slug = 'my-chapter';
        $chapter->book_slug = 'my-book';

        $url = $chapter->getUrl('/edit/');
        $this->assertStringContainsString('/chapter/my-chapter/edit', $url);
        $this->assertStringNotContainsString('/chapter/my-chapter/edit/', $url);
    }

    public function test_chapter_search_factor_is_1_point_2(): void
    {
        $this->assertSame(1.2, (new Chapter())->searchFactor);
    }
}
