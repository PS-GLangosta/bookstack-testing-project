<?php

namespace Tests\Entity;

use Tests\TestCase;

class BookshelfControllerTest extends TestCase
{
    // index()

    public function test_index_accessible_to_logged_in_user(): void
    {
        $this->asEditor()->get('/shelves')->assertOk();
    }

    public function test_index_shows_shelf_listing(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->get('/shelves')->assertSee($shelf->name);
    }

    // create()

    public function test_create_form_accessible_with_permission(): void
    {
        $this->asAdmin()->get('/create-shelf')->assertOk();
    }

    public function test_create_form_denied_to_viewer(): void
    {
        $this->asViewer()->get('/create-shelf')->assertRedirect();
    }

    // store()

    public function test_store_creates_shelf_and_redirects(): void
    {
        $resp = $this->asAdmin()->post('/shelves', ['name' => 'My New Shelf']);
        $resp->assertRedirect();
        $this->assertDatabaseHasEntityData('bookshelf', ['name' => 'My New Shelf']);
    }

    public function test_store_requires_name(): void
    {
        $this->asAdmin()->post('/shelves', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_store_can_assign_books(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->post('/shelves', [
            'name'  => 'Shelf With Book',
            'books' => (string) $book->id,
        ])->assertRedirect();
        $shelf = \BookStack\Entities\Models\Bookshelf::where('name', 'Shelf With Book')->first();
        $this->assertNotNull($shelf);
        $this->assertTrue($shelf->contains($book));
    }

    // show()

    public function test_show_displays_shelf_page(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->get($shelf->getUrl())->assertOk()->assertSee($shelf->name);
    }

    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $this->asAdmin()->get('/shelves/nonexistent-shelf-xyz')->assertNotFound();
    }

    // edit()

    public function test_edit_form_accessible_to_admin(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->get($shelf->getUrl('/edit'))->assertOk();
    }

    public function test_edit_denied_to_viewer(): void
    {
        $shelf = $this->entities->shelf();
        $this->asViewer()->get($shelf->getUrl('/edit'))->assertRedirect();
    }

    // update()

    public function test_update_saves_new_name_and_redirects(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->put($shelf->getUrl(), ['name' => 'Updated Shelf Name'])->assertRedirect();
        $this->assertDatabaseHasEntityData('bookshelf', ['name' => 'Updated Shelf Name']);
    }

    public function test_update_requires_name(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->put($shelf->getUrl(), ['name' => ''])->assertSessionHasErrors('name');
    }

    // showDelete()

    public function test_show_delete_displays_confirmation(): void
    {
        $shelf = $this->entities->shelf();
        $this->asAdmin()->get($shelf->getUrl('/delete'))->assertOk()->assertSee($shelf->name);
    }

    // destroy()

    public function test_destroy_soft_deletes_shelf(): void
    {
        $shelf = $this->entities->shelf();
        $shelfId = $shelf->id;
        $this->asAdmin()->delete($shelf->getUrl())->assertRedirect('/shelves');
        $this->assertDatabaseMissing('entities', ['id' => $shelfId, 'deleted_at' => null, 'type' => 'bookshelf']);
    }

    public function test_destroy_denied_to_viewer(): void
    {
        $shelf = $this->entities->shelf();
        $this->asViewer()->delete($shelf->getUrl())->assertRedirect();
        $this->assertDatabaseHasEntityData('bookshelf', ['id' => $shelf->id]);
    }
}
