<?php

namespace Tests\System;

use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
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
    }

    protected function createPageForParent(
        Entity $parent,
        array $input
    ): Page {
        $pageRepo = app(PageRepo::class);
        $draft = $pageRepo->getNewDraftPage($parent);

        return $pageRepo->publishDraft($draft, $input);
    }

    protected function setPermissionsForUser(
        Entity $entity,
        User $user,
        array $actions = []
    ): void {
        $role = $user->roles->first();

        $this->permissions->setEntityPermissions(
            $entity,
            $actions,
            [$role]
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

    protected function assertStoredPermissions(
        Entity $entity,
        User $user,
        array $actions = []
    ): void {
        $role = $user->roles->first();

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
}