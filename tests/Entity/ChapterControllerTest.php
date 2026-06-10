<?php

namespace Tests\Entity;

use Tests\TestCase;

class ChapterControllerTest extends TestCase
{
    // create()

    public function test_create_form_accessible_with_permission(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->get($book->getUrl('/create-chapter'))->assertOk();
    }

    public function test_create_form_denied_to_viewer(): void
    {
        $book = $this->entities->book();
        $this->asViewer()->get($book->getUrl('/create-chapter'))->assertRedirect();
    }

    // store()

    public function test_store_creates_chapter_and_redirects(): void
    {
        $book = $this->entities->book();
        $resp = $this->asEditor()->post($book->getUrl('/create-chapter'), ['name' => 'My New Chapter']);
        $resp->assertRedirect();
        $this->assertDatabaseHasEntityData('chapter', ['name' => 'My New Chapter']);
    }

    public function test_store_requires_name(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->post($book->getUrl('/create-chapter'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_store_name_max_length_validation(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->post($book->getUrl('/create-chapter'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
    }

    // show()

    public function test_show_displays_chapter_page(): void
    {
        $chapter = $this->entities->chapter();
        $this->asEditor()->get($chapter->getUrl())->assertOk()->assertSee($chapter->name);
    }

    public function test_show_returns_404_for_nonexistent_slugs(): void
    {
        $book = $this->entities->book();
        $this->asEditor()->get('/books/' . $book->slug . '/chapter/nonexistent-xyz')->assertNotFound();
    }

    // edit()

    public function test_edit_form_accessible_to_admin(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->get($chapter->getUrl('/edit'))->assertOk();
    }

    public function test_edit_denied_to_viewer(): void
    {
        $chapter = $this->entities->chapter();
        $this->asViewer()->get($chapter->getUrl('/edit'))->assertRedirect();
    }

    // update()

    public function test_update_saves_new_name_and_redirects(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->put($chapter->getUrl(), ['name' => 'Updated Chapter Name'])->assertRedirect();
        $this->assertDatabaseHasEntityData('chapter', ['name' => 'Updated Chapter Name']);
    }

    public function test_update_requires_name(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->put($chapter->getUrl(), ['name' => ''])->assertSessionHasErrors('name');
    }

    // showDelete()

    public function test_show_delete_displays_confirmation(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->get($chapter->getUrl('/delete'))->assertOk()->assertSee($chapter->name);
    }

    // destroy()

    public function test_destroy_soft_deletes_chapter(): void
    {
        $chapter = $this->entities->chapter();
        $chapterId = $chapter->id;
        $this->asAdmin()->delete($chapter->getUrl())->assertRedirect();
        $this->assertDatabaseMissing('entities', ['id' => $chapterId, 'deleted_at' => null, 'type' => 'chapter']);
    }

    public function test_destroy_denied_to_viewer(): void
    {
        $chapter = $this->entities->chapter();
        $this->asViewer()->delete($chapter->getUrl())->assertRedirect();
        $this->assertDatabaseHasEntityData('chapter', ['id' => $chapter->id]);
    }

    // showMove()

    public function test_show_move_form_accessible_to_admin(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->get($chapter->getUrl('/move'))->assertOk();
    }

    // move()

    public function test_move_changes_parent_book(): void
    {
        $chapter = $this->entities->chapter();
        $newBook = $this->entities->book();
        $this->asAdmin()->put($chapter->getUrl('/move'), [
            'entity_selection' => 'book:' . $newBook->id,
        ])->assertRedirect();
        $this->assertSame($newBook->id, $chapter->fresh()->book_id);
    }

    public function test_move_with_no_selection_redirects_back(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->put($chapter->getUrl('/move'), ['entity_selection' => ''])->assertRedirect($chapter->getUrl());
    }

    // showCopy()

    public function test_show_copy_displays_form(): void
    {
        $chapter = $this->entities->chapter();
        $this->asAdmin()->get($chapter->getUrl('/copy'))->assertOk();
    }

    // copy()

    public function test_copy_creates_new_chapter(): void
    {
        $chapter = $this->entities->chapter();
        $countBefore = \BookStack\Entities\Models\Chapter::count();
        $this->asAdmin()->post($chapter->getUrl('/copy'), ['name' => 'Copied Chapter'])->assertRedirect();
        $this->assertGreaterThan($countBefore, \BookStack\Entities\Models\Chapter::count());
    }
}
