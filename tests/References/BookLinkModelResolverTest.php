<?php

namespace Tests\References;

use BookStack\References\ModelResolvers\BookLinkModelResolver;
use Tests\TestCase;

class BookLinkModelResolverTest extends TestCase
{
    protected BookLinkModelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(BookLinkModelResolver::class);
    }

    public function test_resolve_returns_book_for_valid_book_url(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug);
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($book->id, $result->id);
    }

    public function test_resolve_returns_null_for_nonexistent_slug(): void
    {
        $this->asAdmin();
        $url = url('/books/this-book-does-not-exist-xyz');
        $result = $this->resolver->resolve($url);
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_non_book_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve(url('/shelves/some-shelf'));
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_completely_different_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve('https://example.com/something');
        $this->assertNull($result);
    }

    public function test_resolve_handles_url_with_trailing_path(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug . '/chapter/some-chapter');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($book->id, $result->id);
    }

    public function test_resolve_handles_url_with_query_string(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug . '?tab=activity');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
    }

    public function test_resolve_handles_url_with_hash_fragment(): void
    {
        $this->asAdmin();
        $book = $this->entities->book();
        $url = url('/books/' . $book->slug . '#section');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
    }
}
