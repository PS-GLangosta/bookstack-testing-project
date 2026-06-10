<?php

namespace Tests\Exports;

use Tests\Api\TestsApi;
use Tests\TestCase;

class BookExportApiControllerTest extends TestCase
{
    use TestsApi;

    public function test_export_html_returns_html_content(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->get("/api/books/{$book->id}/export/html");
        $resp->assertOk();
        $this->assertStringContainsString('.html', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_html_contains_book_name(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $this->get("/api/books/{$book->id}/export/html")->assertOk()->assertSee($book->name);
    }

    public function test_export_plain_text_returns_txt_content(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->get("/api/books/{$book->id}/export/plaintext");
        $resp->assertOk();
        $this->assertStringContainsString('.txt', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_markdown_returns_md_content(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->get("/api/books/{$book->id}/export/markdown");
        $resp->assertOk();
        $this->assertStringContainsString('.md', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_requires_authentication(): void
    {
        $book = $this->entities->book();
        $this->get("/api/books/{$book->id}/export/html")->assertUnauthorized();
    }

    public function test_export_returns_404_for_nonexistent_book(): void
    {
        $this->actingAsApiAdmin();
        $this->get('/api/books/999999/export/html')->assertNotFound();
    }
}
