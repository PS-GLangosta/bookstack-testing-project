<?php

namespace Tests\References;

use BookStack\References\ModelResolvers\BookshelfLinkModelResolver;
use Tests\TestCase;

class BookshelfLinkModelResolverTest extends TestCase
{
    protected BookshelfLinkModelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(BookshelfLinkModelResolver::class);
    }

    public function test_resolve_returns_shelf_for_valid_shelf_url(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $url = url('/shelves/' . $shelf->slug);
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($shelf->id, $result->id);
    }

    public function test_resolve_returns_null_for_nonexistent_shelf_slug(): void
    {
        $this->asAdmin();
        $url = url('/shelves/this-shelf-does-not-exist-xyz');
        $result = $this->resolver->resolve($url);
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_non_shelf_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve(url('/books/some-book'));
        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_external_url(): void
    {
        $this->asAdmin();
        $result = $this->resolver->resolve('https://example.com/shelves/test');
        $this->assertNull($result);
    }

    public function test_resolve_handles_url_with_trailing_slash(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $url = url('/shelves/' . $shelf->slug . '/');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
        $this->assertSame($shelf->id, $result->id);
    }

    public function test_resolve_handles_url_with_query_string(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $url = url('/shelves/' . $shelf->slug . '?tab=books');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
    }

    public function test_resolve_handles_url_with_hash_fragment(): void
    {
        $this->asAdmin();
        $shelf = $this->entities->shelf();
        $url = url('/shelves/' . $shelf->slug . '#section');
        $result = $this->resolver->resolve($url);
        $this->assertNotNull($result);
    }
}
