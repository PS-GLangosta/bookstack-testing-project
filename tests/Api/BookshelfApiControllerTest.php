<?php

namespace Tests\Api;

use Tests\TestCase;

class BookshelfApiControllerTest extends TestCase
{
    use TestsApi;

    // list()

    public function test_list_returns_json_with_data_array(): void
    {
        $this->actingAsApiAdmin();
        $this->getJson('/api/shelves')->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_list_contains_visible_shelves(): void
    {
        $this->actingAsApiAdmin();
        $shelf = $this->entities->shelf();
        $this->getJson('/api/shelves')->assertOk()->assertJsonFragment(['id' => $shelf->id]);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/shelves')->assertUnauthorized();
    }

    // create()

    public function test_create_returns_new_shelf_json(): void
    {
        $this->actingAsApiAdmin();
        $resp = $this->postJson('/api/shelves', ['name' => 'API Created Shelf']);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Created Shelf']);
        $this->assertDatabaseHasEntityData('bookshelf', ['name' => 'API Created Shelf']);
    }

    public function test_create_requires_name(): void
    {
        $this->actingAsApiAdmin();
        $this->postJson('/api/shelves', [])->assertStatus(422);
    }

    public function test_create_accepts_books_array(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->postJson('/api/shelves', [
            'name'  => 'Shelf With API Books',
            'books' => [$book->id],
        ]);
        $resp->assertOk();
        $shelf = \BookStack\Entities\Models\Bookshelf::where('name', 'Shelf With API Books')->first();
        $this->assertNotNull($shelf);
        $this->assertTrue($shelf->contains($book));
    }

    // read()

    public function test_read_returns_shelf_detail(): void
    {
        $this->actingAsApiAdmin();
        $shelf = $this->entities->shelf();
        $resp = $this->getJson("/api/shelves/{$shelf->id}");
        $resp->assertOk()->assertJsonFragment(['id' => $shelf->id, 'name' => $shelf->name]);
    }

    public function test_read_returns_404_for_nonexistent_shelf(): void
    {
        $this->actingAsApiAdmin();
        $this->getJson('/api/shelves/999999')->assertNotFound();
    }

    public function test_read_includes_books_field(): void
    {
        $this->actingAsApiAdmin();
        $shelf = $this->entities->shelf();
        $resp = $this->getJson("/api/shelves/{$shelf->id}");
        $resp->assertOk()->assertJsonStructure(['books']);
    }

    // update()

    public function test_update_changes_shelf_name(): void
    {
        $this->actingAsApiAdmin();
        $shelf = $this->entities->shelf();
        $resp = $this->putJson("/api/shelves/{$shelf->id}", ['name' => 'API Updated Shelf']);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Updated Shelf']);
    }

    public function test_update_returns_404_for_nonexistent_shelf(): void
    {
        $this->actingAsApiAdmin();
        $this->putJson('/api/shelves/999999', ['name' => 'Test'])->assertNotFound();
    }

    // delete()

    public function test_delete_soft_deletes_shelf_and_returns_204(): void
    {
        $this->actingAsApiAdmin();
        $shelf = $this->entities->shelf();
        $shelfId = $shelf->id;
        $this->deleteJson("/api/shelves/{$shelfId}")->assertNoContent();
        $this->assertDatabaseMissing('entities', ['id' => $shelfId, 'deleted_at' => null, 'type' => 'bookshelf']);
    }

    public function test_delete_returns_404_for_nonexistent_shelf(): void
    {
        $this->actingAsApiAdmin();
        $this->deleteJson('/api/shelves/999999')->assertNotFound();
    }

    public function test_delete_requires_authentication(): void
    {
        $shelf = $this->entities->shelf();
        $this->deleteJson("/api/shelves/{$shelf->id}")->assertUnauthorized();
    }
}
