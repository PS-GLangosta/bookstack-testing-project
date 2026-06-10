<?php

namespace Tests\Api;

use Tests\TestCase;

class ChapterApiControllerTest extends TestCase
{
    use TestsApi;

    // list()

    public function test_list_returns_json_with_data_array(): void
    {
        $this->actingAsApiAdmin();
        $this->getJson('/api/chapters')->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_list_contains_visible_chapters(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $this->getJson('/api/chapters')->assertOk()->assertJsonFragment(['id' => $chapter->id]);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/chapters')->assertUnauthorized();
    }

    // create()

    public function test_create_returns_new_chapter_json(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->postJson('/api/chapters', [
            'book_id' => $book->id,
            'name'    => 'API Created Chapter',
        ]);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Created Chapter']);
        $this->assertDatabaseHasEntityData('chapter', ['name' => 'API Created Chapter']);
    }

    public function test_create_requires_name(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $this->postJson('/api/chapters', ['book_id' => $book->id])->assertStatus(422);
    }

    public function test_create_requires_book_id(): void
    {
        $this->actingAsApiAdmin();
        $this->postJson('/api/chapters', ['name' => 'No Book Chapter'])->assertStatus(422);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/chapters', ['name' => 'Test'])->assertUnauthorized();
    }

    // read()

    public function test_read_returns_chapter_detail(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $resp = $this->getJson("/api/chapters/{$chapter->id}");
        $resp->assertOk()->assertJsonFragment(['id' => $chapter->id, 'name' => $chapter->name]);
    }

    public function test_read_returns_404_for_nonexistent_chapter(): void
    {
        $this->actingAsApiAdmin();
        $this->getJson('/api/chapters/999999')->assertNotFound();
    }

    public function test_read_includes_pages_field(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapterHasPages();
        $resp = $this->getJson("/api/chapters/{$chapter->id}");
        $resp->assertOk()->assertJsonStructure(['pages']);
    }

    // update()

    public function test_update_changes_chapter_name(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $resp = $this->putJson("/api/chapters/{$chapter->id}", ['name' => 'API Updated Chapter']);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Updated Chapter']);
    }

    public function test_update_returns_404_for_nonexistent_chapter(): void
    {
        $this->actingAsApiAdmin();
        $this->putJson('/api/chapters/999999', ['name' => 'Test'])->assertNotFound();
    }

    public function test_update_can_move_chapter_by_providing_book_id(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $newBook = $this->entities->book();
        $this->putJson("/api/chapters/{$chapter->id}", [
            'name'    => $chapter->name,
            'book_id' => $newBook->id,
        ])->assertOk();
        $this->assertSame($newBook->id, $chapter->fresh()->book_id);
    }

    // delete()

    public function test_delete_soft_deletes_chapter_and_returns_204(): void
    {
        $this->actingAsApiAdmin();
        $chapter = $this->entities->chapter();
        $chapterId = $chapter->id;
        $this->deleteJson("/api/chapters/{$chapterId}")->assertNoContent();
        $this->assertDatabaseMissing('entities', ['id' => $chapterId, 'deleted_at' => null, 'type' => 'chapter']);
    }

    public function test_delete_returns_404_for_nonexistent_chapter(): void
    {
        $this->actingAsApiAdmin();
        $this->deleteJson('/api/chapters/999999')->assertNotFound();
    }

    public function test_delete_requires_authentication(): void
    {
        $chapter = $this->entities->chapter();
        $this->deleteJson("/api/chapters/{$chapter->id}")->assertUnauthorized();
    }
}
