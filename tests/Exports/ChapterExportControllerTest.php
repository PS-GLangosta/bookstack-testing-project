<?php

namespace Tests\Exports;

use Tests\TestCase;

class ChapterExportControllerTest extends TestCase
{
    public function test_html_export_returns_html_file(): void
    {
        $chapter = $this->entities->chapterHasPages();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/html'));
        $resp->assertOk();
        $this->assertStringContainsString('.html', $resp->headers->get('Content-Disposition'));
    }

    public function test_html_export_contains_chapter_name(): void
    {
        $chapter = $this->entities->chapter();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/html'));
        $resp->assertOk()->assertSee($chapter->name);
    }

    public function test_plain_text_export_returns_txt_file(): void
    {
        $chapter = $this->entities->chapter();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/plaintext'));
        $resp->assertOk();
        $this->assertStringContainsString('.txt', $resp->headers->get('Content-Disposition'));
    }

    public function test_plain_text_export_contains_chapter_name(): void
    {
        $chapter = $this->entities->chapter();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/plaintext'));
        $resp->assertOk()->assertSee($chapter->name);
    }

    public function test_markdown_export_returns_md_file(): void
    {
        $chapter = $this->entities->chapter();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/markdown'));
        $resp->assertOk();
        $this->assertStringContainsString('.md', $resp->headers->get('Content-Disposition'));
    }

    public function test_markdown_export_contains_chapter_name(): void
    {
        $chapter = $this->entities->chapter();
        $resp = $this->asEditor()->get($chapter->getUrl('/export/markdown'));
        $resp->assertOk()->assertSee($chapter->name);
    }

    public function test_export_denied_to_user_without_export_permission(): void
    {
        $chapter = $this->entities->chapter();
        $viewer = $this->users->viewer();
        $this->permissions->removeUserRolePermissions($viewer, ['content-export']);
        $resp = $this->actingAs($viewer)->get($chapter->getUrl('/export/html'));
        $this->assertPermissionError($resp);
    }

    public function test_export_returns_404_for_nonexistent_chapter(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->get('/books/' . $book->slug . '/chapter/nonexistent-chapter/export/html')->assertNotFound();
    }
}
