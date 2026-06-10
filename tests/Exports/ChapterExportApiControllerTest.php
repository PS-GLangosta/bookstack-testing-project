<?php

namespace Tests\Exports;

use Tests\Api\TestsApi;
use Tests\TestCase;

class ChapterExportApiControllerTest extends TestCase
{
    use TestsApi;

    public function test_export_html_returns_html_content(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $resp = $this->get("/api/chapters/{$chapter->id}/export/html");
        $resp->assertOk();
        $this->assertStringContainsString('.html', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_html_contains_chapter_name(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $this->get("/api/chapters/{$chapter->id}/export/html")->assertOk()->assertSee($chapter->name);
    }

    public function test_export_plain_text_returns_txt_content(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $resp = $this->get("/api/chapters/{$chapter->id}/export/plaintext");
        $resp->assertOk();
        $this->assertStringContainsString('.txt', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_markdown_returns_md_content(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $resp = $this->get("/api/chapters/{$chapter->id}/export/markdown");
        $resp->assertOk();
        $this->assertStringContainsString('.md', $resp->headers->get('Content-Disposition'));
    }

    public function test_export_requires_authentication(): void
    {
        $chapter = $this->entities->chapter();
        $this->get("/api/chapters/{$chapter->id}/export/html")->assertUnauthorized();
    }

    public function test_export_returns_404_for_nonexistent_chapter(): void
    {
        $this->actingAsApiAdmin();
        $this->get('/api/chapters/999999/export/html')->assertNotFound();
    }
}
