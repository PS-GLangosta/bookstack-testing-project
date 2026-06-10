<?php

namespace Tests\Exports;

use Tests\TestCase;

class BookExportControllerTest extends TestCase
{
    public function test_html_export_returns_html_file(): void
    {
        $book = $this->entities->bookHasChaptersAndPages();
        $resp = $this->asEditor()->get($book->getUrl('/export/html'));
        $resp->assertOk();
        $resp->assertHeader('Content-Disposition');
        $this->assertStringContainsString('.html', $resp->headers->get('Content-Disposition'));
    }

    public function test_html_export_contains_book_name(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->get($book->getUrl('/export/html'));
        $resp->assertOk()->assertSee($book->name);
    }

    public function test_plain_text_export_returns_txt_file(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->get($book->getUrl('/export/plaintext'));
        $resp->assertOk();
        $this->assertStringContainsString('.txt', $resp->headers->get('Content-Disposition'));
    }

    public function test_plain_text_export_contains_book_name(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->get($book->getUrl('/export/plaintext'));
        $resp->assertOk()->assertSee($book->name);
    }

    public function test_markdown_export_returns_md_file(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->get($book->getUrl('/export/markdown'));
        $resp->assertOk();
        $this->assertStringContainsString('.md', $resp->headers->get('Content-Disposition'));
    }

    public function test_markdown_export_contains_book_name(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->get($book->getUrl('/export/markdown'));
        $resp->assertOk()->assertSee($book->name);
    }

    public function test_export_denied_to_user_without_export_permission(): void
    {
        $book = $this->entities->book();
        $viewer = $this->users->viewer();
        $this->permissions->removeUserRolePermissions($viewer, ['content-export']);
        $resp = $this->actingAs($viewer)->get($book->getUrl('/export/html'));
        $this->assertPermissionError($resp);
    }

    public function test_export_returns_404_for_nonexistent_book(): void
    {
        $this->asEditor()->get('/books/nonexistent-book-xyz/export/html')->assertNotFound();
    }
}
