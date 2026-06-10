<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\User;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    protected User $user;
    protected mixed $role;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->user, $this->role] = $this->users->newUserWithRole([], []);
    }

    protected function setUserEntityPermissions(Entity $entity, array $actions = [], bool $inherit = false): void
    {
        $this->permissions->setEntityPermissions($entity, $actions, [$this->role], $inherit);
    }

    protected function createContentChain(): array
    {
        return $this->entities->createChainBelongingToUser($this->users->admin());
    }

    public function test_ut_pm_01_admin_has_access_to_manage_content(): void
    {
        [$adminUser] = $this->users->newUserWithRole([], [
            'book-view-all',
            'book-create-all',
            'book-update-all',
            'book-delete-all',
        ]);

        $content = $this->entities->createChainBelongingToUser($adminUser);
        $book = $content['book'];

        $this->actingAs($adminUser)
            ->get('/create-book')
            ->assertOk();

        $this->actingAs($adminUser)
            ->get($book->getUrl('/edit'))
            ->assertOk()
            ->assertSee('Edit Book');

        $this->actingAs($adminUser)
            ->get($book->getUrl('/delete'))
            ->assertOk()
            ->assertSee('Delete Book');
    }

    public function test_ut_pm_02_viewer_cannot_create_book(): void
    {
        $this->actingAs($this->user)
            ->get('/create-book')
            ->assertRedirect('/');

        $this->get('/')
            ->assertSee('You do not have permission');
    }

    public function test_ut_pm_03_book_permissions_are_inherited_by_chapter_and_page(): void
    {
        $content = $this->createContentChain();

        $book = $content['book'];
        $chapter = $content['chapter'];
        $page = $content['page'];

        $this->setUserEntityPermissions($book, []);

        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get($book->getUrl())
            ->assertSee('Book not found');

        $this->followingRedirects()
            ->get($chapter->getUrl())
            ->assertSee('Chapter not found');

        $this->followingRedirects()
            ->get($page->getUrl())
            ->assertSee('Page not found');

        $this->setUserEntityPermissions($book, ['view']);

        $this->get($book->getUrl())
            ->assertOk()
            ->assertSee($book->name);

        $this->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($chapter->name);

        $this->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name);
    }

    public function test_ut_pm_04_explicit_chapter_permission_overrides_book_restriction(): void
    {
        $content = $this->createContentChain();

        $book = $content['book'];
        $chapter = $content['chapter'];

        $this->setUserEntityPermissions($book, []);

        $this->permissions->setEntityPermissions($chapter, ['view'], [$this->role], false);

        $this->actingAs($this->user);

        $this->followingRedirects()
            ->get($book->getUrl())
            ->assertSee('Book not found');

        $this->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($chapter->name);
    }

    public function test_ut_pm_05_user_without_permissions_cannot_access_private_content(): void
    {
        $publicContent = $this->createContentChain();
        $privateContent = $this->createContentChain();

        $publicBook = $publicContent['book'];
        $privateBook = $privateContent['book'];

        $this->setUserEntityPermissions($publicBook, ['view']);
        $this->setUserEntityPermissions($privateBook, []);

        $this->actingAs($this->user);

        $this->get($publicBook->getUrl())
            ->assertOk()
            ->assertSee($publicBook->name);

        $this->followingRedirects()
            ->get($privateBook->getUrl())
            ->assertSee('Book not found');
    }
}