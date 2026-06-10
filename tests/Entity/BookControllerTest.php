<?php

namespace Tests\Entity;

use Tests\TestCase;

class BookControllerTest extends TestCase
{
    // index()

    public function test_index_accessible_to_logged_in_user(): void
    {
        $this->asEditor()->get('/books')->assertOk();
    }

    public function test_index_shows_book_listing(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->get('/books')->assertSee($book->name);
    }

    public function test_index_accessible_without_login(): void
    {
        $this->permissions->makeAppPublic();
        $this->get('/books')->assertOk();
    }

    // create()

    public function test_create_form_accessible_to_user_with_permission(): void
    {
        $this->asEditor()->get('/create-book')->assertOk();
    }

    public function test_create_form_denied_to_viewer(): void
    {
        $this->asViewer()->get('/create-book')->assertRedirect();
    }

    // store()

    public function test_store_creates_book_and_redirects(): void
    {
        $resp = $this->asEditor()->post('/books', ['name' => 'My New Controller Book']);
        $resp->assertRedirect();
        $this->assertDatabaseHasEntityData('book', ['name' => 'My New Controller Book']);
    }

    public function test_store_requires_name(): void
    {
        $this->asEditor()->post('/books', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_store_name_max_length_validation(): void
    {
        $this->asEditor()->post('/books', ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
    }

    // show()

    public function test_show_displays_book_page(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->get($book->getUrl())->assertOk()->assertSee($book->name);
    }

    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $this->asEditor()->get('/books/slug-does-not-exist-xyz')->assertNotFound();
    }

    // edit()

    public function test_edit_form_accessible_to_book_owner(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->get($book->getUrl('/edit'))->assertOk();
    }

    public function test_edit_denied_to_viewer(): void
    {
        $book = $this->entities->book();
        $this->asViewer()->get($book->getUrl('/edit'))->assertRedirect();
    }

    // update()

    public function test_update_saves_new_name(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->put($book->getUrl(), ['name' => 'Updated Book Name']);
        $this->assertDatabaseHasEntityData('book', ['name' => 'Updated Book Name']);
    }

    public function test_update_requires_name(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->put($book->getUrl(), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_update_redirects_to_book_after_save(): void
    {
        $book = $this->entities->book();
        $resp = $this->asAdmin()->put($book->getUrl(), ['name' => 'Redirect Book']);
        $resp->assertRedirect();
    }

    // showDelete()

    public function test_show_delete_displays_confirmation_page(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->get($book->getUrl('/delete'))->assertOk()->assertSee($book->name);
    }

    // destroy()

    public function test_destroy_soft_deletes_book_and_redirects(): void
    {
        $book = $this->entities->book();
        $bookId = $book->id;
        $this->asAdmin()->delete($book->getUrl())->assertRedirect();
        $this->assertDatabaseMissing('entities', ['id' => $bookId, 'deleted_at' => null, 'type' => 'book']);
    }

    public function test_destroy_denied_to_viewer(): void
    {
        $book = $this->entities->book();
        $this->asViewer()->delete($book->getUrl())->assertRedirect();
        $this->assertDatabaseHasEntityData('book', ['id' => $book->id]);
    }

    // showCopy()

    public function test_show_copy_displays_copy_form(): void
    {
        $book = $this->entities->book();
        $this->asAdmin()->get($book->getUrl('/copy'))->assertOk();
    }

    // copy()

    public function test_copy_creates_new_book(): void
    {
        $book = $this->entities->book();
        $countBefore = \BookStack\Entities\Models\Book::count();
        $this->asAdmin()->post($book->getUrl('/copy'), ['name' => 'Copied Book'])->assertRedirect();
        $this->assertGreaterThan($countBefore, \BookStack\Entities\Models\Book::count());
    }
}
