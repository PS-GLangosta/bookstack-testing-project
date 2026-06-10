<?php

namespace Tests\Api;

use BookStack\Entities\Models\Book;
use Tests\TestCase;

class BookApiControllerTest extends TestCase
{
    use TestsApi;

    // list()

    public function test_list_returns_json_with_data_array(): void
    {
        $this->actingAsApiAdmin();
        $resp = $this->getJson('/api/books');
        $resp->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_list_contains_visible_books(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->getJson('/api/books');
        $resp->assertOk()->assertJsonFragment(['id' => $book->id]);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/books')->assertUnauthorized();
    }

    // create()

    public function test_create_returns_new_book_json(): void
    {
        $this->actingAsApiAdmin();
        $resp = $this->postJson('/api/books', ['name' => 'API Created Book']);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Created Book']);
        $this->assertDatabaseHasEntityData('book', ['name' => 'API Created Book']);
    }

    public function test_create_requires_name(): void
    {
        $this->actingAsApiAdmin();
        $this->postJson('/api/books', [])->assertStatus(422);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/books', ['name' => 'Test'])->assertUnauthorized();
    }

    public function test_create_accepts_description(): void
    {
        $this->actingAsApiAdmin();
        $resp = $this->postJson('/api/books', [
            'name'        => 'API Book With Desc',
            'description' => 'A test description',
        ]);
        $resp->assertOk();
        $this->assertDatabaseHasEntityData('book', ['name' => 'API Book With Desc']);
    }

    // read()

    public function test_read_returns_book_detail(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->getJson("/api/books/{$book->id}");
        $resp->assertOk()->assertJsonFragment(['id' => $book->id, 'name' => $book->name]);
    }

    public function test_read_returns_404_for_nonexistent_book(): void
    {
        $this->actingAsApiAdmin();
        $this->getJson('/api/books/999999')->assertNotFound();
    }

    public function test_read_includes_contents_field(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->getJson("/api/books/{$book->id}");
        $resp->assertOk()->assertJsonStructure(['contents']);
    }

    // update()

    public function test_update_changes_book_name(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $resp = $this->putJson("/api/books/{$book->id}", ['name' => 'API Updated Book']);
        $resp->assertOk()->assertJsonFragment(['name' => 'API Updated Book']);
    }

    public function test_update_returns_404_for_nonexistent_book(): void
    {
        $this->actingAsApiAdmin();
        $this->putJson('/api/books/999999', ['name' => 'Test'])->assertNotFound();
    }

    public function test_update_requires_authentication(): void
    {
        $book = $this->entities->book();
        $this->putJson("/api/books/{$book->id}", ['name' => 'Test'])->assertUnauthorized();
    }

    // delete()

    public function test_delete_soft_deletes_book_and_returns_204(): void
    {
        $this->actingAsApiAdmin();
        $book = $this->entities->book();
        $bookId = $book->id;
        $this->deleteJson("/api/books/{$bookId}")->assertNoContent();
        $this->assertDatabaseMissing('entities', ['id' => $bookId, 'deleted_at' => null, 'type' => 'book']);
    }

    public function test_delete_returns_404_for_nonexistent_book(): void
    {
        $this->actingAsApiAdmin();
        $this->deleteJson('/api/books/999999')->assertNotFound();
    }

    public function test_delete_requires_authentication(): void
    {
        $book = $this->entities->book();
        $this->deleteJson("/api/books/{$book->id}")->assertUnauthorized();
    }
}
