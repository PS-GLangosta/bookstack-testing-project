<?php

namespace Tests\References;

use BookStack\References\ModelResolvers\ChapterLinkModelResolver;
use Tests\TestCase;

class ChapterLinkModelResolverTest extends TestCase
{
    protected ChapterLinkModelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ChapterLinkModelResolver::class);
    }

    public function test_resolve_returns_chapter_for_valid_chapter_url(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;
        $url = url('/books/' . $book->slug . '/chapter/' . $chapter->slug);
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($chapter->id, $result->id);
    }

    public function test_resolve_returns_null_for_nonexistent_chapter_slug(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug . '/chapter/nonexistent-chapter-xyz');
        $result = $this->resolver->resolve($url);
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_book_only_url(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug);
        $result = $this->resolver->resolve($url);
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_non_matching_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve('https://other.domain.com/chapter/test');
        $this->assertNull($result);
    }

    public function test_resolve_handles_url_with_trailing_path(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;
        $url = url('/books/' . $book->slug . '/chapter/' . $chapter->slug . '/edit');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($chapter->id, $result->id);
    }

    public function test_resolve_handles_url_with_query_string(): void
    {
        $this->asAdmin();
        $chapter = $this->entities->chapter();
        $book = $chapter->book;
        $url = url('/books/' . $book->slug . '/chapter/' . $chapter->slug . '?ref=search');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
    }

    public function test_resolve_returns_null_for_shelf_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve(url('/shelves/some-shelf'));
        $this->assertNull($result);
    }
}
