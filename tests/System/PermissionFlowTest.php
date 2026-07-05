<?php

namespace Tests\System;

use BookStack\Entities\Models\Entity;
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