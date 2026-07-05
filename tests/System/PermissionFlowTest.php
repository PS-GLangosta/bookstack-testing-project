<?php

namespace Tests\System;

use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use BookStack\Users\UserRepo;
use Tests\TestCase;

class PermissionFlowTest extends TestCase
{
    protected User $admin;
    protected User $editor;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->users->newUser([
            'name' => 'ST-02 Admin',
        ]);
        $this->admin->attachRole(Role::getSystemRole('admin'));

        $this->editor = $this->users->newUser([
            'name' => 'ST-02 Editor',
        ]);
        $this->editor->attachRole(Role::getRole('editor'));

        $this->viewer = $this->users->newUser([
            'name' => 'ST-02 Viewer',
        ]);
        $this->viewer->attachRole(Role::getRole('viewer'));
    }

    public function test_st_02_01_admin_restringe_edicion_de_un_libro_para_editor(): void
    {
        $this->actingAs($this->admin);

        $book = $this->entities->newBook([
            'name' => 'Libro restringido ST-02-01',
            'description' => 'Libro usado para comprobar permisos explicitos',
        ]);

        static::assertTrue($book->exists);
        static::assertSame('Libro restringido ST-02-01', $book->name);

        $response = $this->actingAs($this->editor)
            ->get($book->getUrl('/edit'));

        $response->assertOk();
        $this->assertNotPermissionError($response);

        $this->actingAs($this->admin);

        $this->setPermissionsForUser(
            $book,
            $this->editor,
            ['view']
        );

        $this->assertStoredPermissions(
            $book,
            $this->editor,
            ['view']
        );

        $response = $this->actingAs($this->editor)
            ->get($book->getUrl('/edit'));

        $response->assertRedirect('/');
        $this->assertPermissionError($response);

        $originalName = $book->name;
        $originalDescription = $book->description;

        $response = $this->actingAs($this->editor)
            ->put($book->getUrl(), [
                'name' => 'Libro modificado por editor',
                'description' => 'Este cambio no debe guardarse',
            ]);

        $response->assertRedirect('/');
        $this->assertPermissionError($response);

        $book->refresh();

        static::assertSame($originalName, $book->name);
        static::assertSame($originalDescription, $book->description);

        static::assertFalse(
            $book->newQuery()
                ->whereKey($book->id)
                ->where('name', 'Libro modificado por editor')
                ->exists()
        );
    }

    public function test_st_02_02_revocar_permiso_restaura_acceso_por_defecto(): void
    {
        $this->actingAs($this->admin);

        $book = $this->entities->newBook([
            'name' => 'Libro con permiso revocable ST-02-02',
            'description' => 'Libro usado para probar la restauracion de permisos',
        ]);

        static::assertTrue($book->exists);
        static::assertSame(
            'Libro con permiso revocable ST-02-02',
            $book->name
        );

        $response = $this->actingAs($this->editor)
            ->get($book->getUrl('/edit'));

        $response->assertOk();
        $this->assertNotPermissionError($response);

        $this->actingAs($this->admin);

        $this->setPermissionsForUser(
            $book,
            $this->editor,
            ['view']
        );

        $this->assertStoredPermissions(
            $book,
            $this->editor,
            ['view']
        );

        $response = $this->actingAs($this->editor)
            ->get($book->getUrl('/edit'));

        $response->assertRedirect('/');
        $this->assertPermissionError($response);

        $this->actingAs($this->admin);
        $this->revokeEntityPermissions($book);

        static::assertCount(0, $book->permissions()->get());

        $this->assertDatabaseMissing('entity_permissions', [
            'entity_id' => $book->id,
            'entity_type' => $book->getMorphClass(),
        ]);

        $response = $this->actingAs($this->editor)
            ->get($book->getUrl('/edit'));

        $response->assertOk();
        $this->assertNotPermissionError($response);
    }

    public function test_st_02_03_viewer_no_accede_a_contenido_privado_en_ningun_nivel(): void
    {
        $this->actingAs($this->admin);

        $book = $this->entities->newBook([
            'name' => 'Libro privado ST-02-03',
            'description' => 'Libro usado para comprobar contenido privado',
        ]);

        $chapter = $this->entities->newChapter([
            'name' => 'Capitulo privado ST-02-03',
            'description' => 'Capitulo dentro del libro privado',
        ], $book);

        $page = $this->createPageForParent($chapter, [
            'name' => 'Pagina privada ST-02-03',
            'html' => '<p>Contenido privado del escenario ST-02-03</p>',
        ]);

        static::assertSame($book->id, $chapter->book_id);
        static::assertSame($book->id, $page->book_id);
        static::assertSame($chapter->id, $page->chapter_id);

        $this->permissions->grantUserRolePermissions(
            $this->viewer,
            ['access-api']
        );

        $this->actingAs($this->viewer, 'api');

        $this->getJson("/api/books/{$book->id}")
            ->assertOk();

        $this->getJson("/api/chapters/{$chapter->id}")
            ->assertOk();

        $this->getJson("/api/pages/{$page->id}")
            ->assertOk();

        $this->actingAs($this->admin);
        $this->makeEntityPrivate($book);

        $this->assertDatabaseHas('entity_permissions', [
            'entity_id' => $book->id,
            'entity_type' => $book->getMorphClass(),
            'role_id' => 0,
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false,
        ]);

        $this->actingAs($this->viewer, 'api');

        $bookResponse = $this->getJson("/api/books/{$book->id}");

        $bookResponse->assertNotFound();
        $bookResponse->assertJsonPath('error.code', 404);

        $chapterResponse = $this->getJson("/api/chapters/{$chapter->id}");

        $chapterResponse->assertNotFound();
        $chapterResponse->assertJsonPath('error.code', 404);

        $pageResponse = $this->getJson("/api/pages/{$page->id}");

        $pageResponse->assertNotFound();
        $pageResponse->assertJsonPath('error.code', 404);

        static::assertTrue(
            $book->newQuery()
                ->whereKey($book->id)
                ->exists()
        );

        static::assertTrue(
            $chapter->newQuery()
                ->whereKey($chapter->id)
                ->where('book_id', $book->id)
                ->exists()
        );

        static::assertTrue(
            $page->newQuery()
                ->whereKey($page->id)
                ->where('book_id', $book->id)
                ->where('chapter_id', $chapter->id)
                ->exists()
        );
    }

    public function test_st_02_04_capitulo_publico_sobrescribe_libro_privado(): void
    {
        $this->actingAs($this->admin);

        $book = $this->entities->newBook([
            'name' => 'Libro privado con override ST-02-04',
            'description' => 'Libro usado para comprobar acceso selectivo',
        ]);

        $publicChapter = $this->entities->newChapter([
            'name' => 'Capitulo publico ST-02-04',
            'description' => 'Capitulo que recibira un permiso publico propio',
        ], $book);

        $publicPage = $this->createPageForParent($publicChapter, [
            'name' => 'Pagina publica ST-02-04',
            'html' => '<p>Contenido que debe permanecer visible</p>',
        ]);

        $privateChapter = $this->entities->newChapter([
            'name' => 'Capitulo privado ST-02-04',
            'description' => 'Capitulo que conservara la restriccion del libro',
        ], $book);

        $privatePage = $this->createPageForParent($privateChapter, [
            'name' => 'Pagina privada ST-02-04',
            'html' => '<p>Contenido que debe permanecer oculto</p>',
        ]);

        static::assertSame($book->id, $publicChapter->book_id);
        static::assertSame($publicChapter->id, $publicPage->chapter_id);
        static::assertSame($book->id, $privateChapter->book_id);
        static::assertSame($privateChapter->id, $privatePage->chapter_id);

        $this->permissions->makeAppPublic();

        auth('standard')->logout();

        $this->get($book->getUrl())
            ->assertOk();

        $this->get($publicChapter->getUrl())
            ->assertOk();

        $this->get($publicPage->getUrl())
            ->assertOk();

        $this->get($privateChapter->getUrl())
            ->assertOk();

        $this->get($privatePage->getUrl())
            ->assertOk();

        $this->actingAs($this->admin);
        $this->makeEntityPrivate($book);

        $this->assertDatabaseHas('entity_permissions', [
            'entity_id' => $book->id,
            'entity_type' => $book->getMorphClass(),
            'role_id' => 0,
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false,
        ]);

        auth('standard')->logout();

        $this->followingRedirects()
            ->get($book->getUrl())
            ->assertSeeText('Book not found');

        $this->followingRedirects()
            ->get($publicChapter->getUrl())
            ->assertSeeText('Chapter not found');

        $this->followingRedirects()
            ->get($publicPage->getUrl())
            ->assertSeeText('Page not found');

        $this->followingRedirects()
            ->get($privateChapter->getUrl())
            ->assertSeeText('Chapter not found');

        $this->followingRedirects()
            ->get($privatePage->getUrl())
            ->assertSeeText('Page not found');

        $this->actingAs($this->admin);

        $publicRole = Role::getSystemRole('public');

        $this->setPermissionsForRole(
            $publicChapter,
            $publicRole,
            ['view']
        );

        $this->assertStoredPermissionsForRole(
            $publicChapter,
            $publicRole,
            ['view']
        );

        $this->assertDatabaseHas('entity_permissions', [
            'entity_id' => $publicChapter->id,
            'entity_type' => $publicChapter->getMorphClass(),
            'role_id' => 0,
            'view' => false,
            'create' => false,
            'update' => false,
            'delete' => false,
        ]);

        static::assertCount(
            2,
            $publicChapter->permissions()->get()
        );

        auth('standard')->logout();

        $publicChapterResponse = $this->get(
            $publicChapter->getUrl()
        );

        $publicChapterResponse
            ->assertOk()
            ->assertSeeText($publicChapter->name)
            ->assertSeeText($publicPage->name);

        $publicPageResponse = $this->get(
            $publicPage->getUrl()
        );

        $publicPageResponse
            ->assertOk()
            ->assertSeeText($publicPage->name)
            ->assertSeeText('Contenido que debe permanecer visible');

        $this->followingRedirects()
            ->get($book->getUrl())
            ->assertSeeText('Book not found');

        $this->followingRedirects()
            ->get($privateChapter->getUrl())
            ->assertSeeText('Chapter not found');

        $this->followingRedirects()
            ->get($privatePage->getUrl())
            ->assertSeeText('Page not found');

        $publicChapterResponse
            ->assertDontSeeText($privateChapter->name)
            ->assertDontSeeText($privatePage->name);

        $publicPageResponse
            ->assertDontSeeText($privateChapter->name)
            ->assertDontSeeText($privatePage->name);

        static::assertTrue(
            $book->newQuery()
                ->whereKey($book->id)
                ->exists()
        );

        static::assertTrue(
            $publicChapter->newQuery()
                ->whereKey($publicChapter->id)
                ->where('book_id', $book->id)
                ->exists()
        );

        static::assertTrue(
            $publicPage->newQuery()
                ->whereKey($publicPage->id)
                ->where('book_id', $book->id)
                ->where('chapter_id', $publicChapter->id)
                ->exists()
        );

        static::assertTrue(
            $privateChapter->newQuery()
                ->whereKey($privateChapter->id)
                ->where('book_id', $book->id)
                ->exists()
        );

        static::assertTrue(
            $privatePage->newQuery()
                ->whereKey($privatePage->id)
                ->where('book_id', $book->id)
                ->where('chapter_id', $privateChapter->id)
                ->exists()
        );

        static::assertCount(
            2,
            $publicChapter->permissions()->get()
        );

        static::assertCount(
            0,
            $publicPage->permissions()->get()
        );

        static::assertCount(
            0,
            $privateChapter->permissions()->get()
        );

        static::assertCount(
            0,
            $privatePage->permissions()->get()
        );
    }

    public function test_st_02_05_cambio_de_admin_a_viewer_actualiza_permisos_en_misma_sesion(): void
    {
        $roleUser = $this->users->newUser([
            'name' => 'ST-02 Usuario con cambio de rol',
        ]);

        $adminRole = Role::getSystemRole('admin');
        $viewerRole = Role::getRole('viewer');

        $roleUser->attachRole($adminRole);

        $this->actingAs($roleUser);

        $book = $this->entities->newBook([
            'name' => 'Libro para cambio de rol ST-02-05',
            'description' => 'Libro usado para comprobar permisos en la misma sesion',
        ]);

        static::assertTrue($book->exists);

        static::assertTrue(
            $roleUser->roles()
                ->whereKey($adminRole->id)
                ->exists()
        );

        static::assertFalse(
            $roleUser->roles()
                ->whereKey($viewerRole->id)
                ->exists()
        );

        $this->get($book->getUrl())
            ->assertOk()
            ->assertSeeText($book->name);

        $this->get($book->getUrl('/permissions'))
            ->assertOk();

        $currentUser = auth('standard')->user();

        static::assertNotNull($currentUser);
        static::assertSame($roleUser->id, $currentUser->id);

        $this->replaceUserRoles(
            $currentUser,
            [$viewerRole]
        );

        static::assertFalse(
            $roleUser->roles()
                ->whereKey($adminRole->id)
                ->exists()
        );

        static::assertTrue(
            $roleUser->roles()
                ->whereKey($viewerRole->id)
                ->exists()
        );

        static::assertCount(1, $currentUser->roles);
        static::assertFalse($currentUser->hasRole($adminRole->id));
        static::assertTrue($currentUser->hasRole($viewerRole->id));

        $sessionUser = auth('standard')->user();

        static::assertNotNull($sessionUser);
        static::assertSame($roleUser->id, $sessionUser->id);
        static::assertSame($currentUser->id, $sessionUser->id);
        static::assertTrue(auth('standard')->check());

        $permissionsResponse = $this->get(
            $book->getUrl('/permissions')
        );

        $this->assertPermissionError($permissionsResponse);

        $editResponse = $this->get(
            $book->getUrl('/edit')
        );

        $this->assertPermissionError($editResponse);

        $viewResponse = $this->get(
            $book->getUrl()
        );

        $viewResponse
            ->assertOk()
            ->assertSeeText($book->name);
    }

    protected function replaceUserRoles(
        User $user,
        array $roles
    ): void {
        $roleIds = array_map(
            static fn (Role $role): int => $role->id,
            $roles
        );

        app(UserRepo::class)->updateWithoutActivity(
            $user,
            ['roles' => $roleIds],
            true
        );

        $user->unsetRelation('roles');
        $user->load('roles');
        $user->clearPermissionCache();
    }

    protected function createPageForParent(
        Entity $parent,
        array $input
    ): Page {
        $pageRepo = app(PageRepo::class);
        $draft = $pageRepo->getNewDraftPage($parent);

        return $pageRepo->publishDraft($draft, $input);
    }

    protected function setPermissionsForRole(
        Entity $entity,
        Role $role,
        array $actions = []
    ): void {
        $this->permissions->setEntityPermissions(
            $entity,
            $actions,
            [$role]
        );
    }

    protected function setPermissionsForUser(
        Entity $entity,
        User $user,
        array $actions = []
    ): void {
        $role = $user->roles->first();

        $this->setPermissionsForRole(
            $entity,
            $role,
            $actions
        );
    }

    protected function makeEntityPrivate(Entity $entity): void
    {
        $this->permissions->disableEntityInheritedPermissions($entity);
    }

    protected function revokeEntityPermissions(Entity $entity): void
    {
        $this->permissions->setEntityPermissions(
            $entity,
            [],
            [],
            true
        );
    }

    protected function assertStoredPermissionsForRole(
        Entity $entity,
        Role $role,
        array $actions = []
    ): void {
        $this->assertDatabaseHas('entity_permissions', [
            'entity_id'   => $entity->id,
            'entity_type' => $entity->getMorphClass(),
            'role_id'     => $role->id,
            'view'        => in_array('view', $actions, true),
            'create'      => in_array('create', $actions, true),
            'update'      => in_array('update', $actions, true),
            'delete'      => in_array('delete', $actions, true),
        ]);
    }

    protected function assertStoredPermissions(
        Entity $entity,
        User $user,
        array $actions = []
    ): void {
        $role = $user->roles->first();

        $this->assertStoredPermissionsForRole(
            $entity,
            $role,
            $actions
        );
    }
}