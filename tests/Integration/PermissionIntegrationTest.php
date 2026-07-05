<?php

namespace Tests\Integration;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Entity;
use BookStack\Permissions\PermissionStatus;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Tests\TestCase;

class PermissionIntegrationTest extends TestCase
{
    protected User $admin;
    protected User $editor;
    protected User $viewer;
    protected Role $editorRole;
    protected Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // usamos el admin que ya viene en los datos de prueba
        $this->admin = $this->users->admin();

        // creamos un usuario editor con permisos para ver crear y editar paginas
        [$this->editor, $this->editorRole] = $this->users->newUserWithRole(
            ['name' => 'Issue 24 Editor'],
            [
                'book-view-all',
                'chapter-view-all',
                'page-view-all',
                'page-update-all',
                'page-create-all',
            ]
        );

        // creamos un usuario que solo puede ver el contenido
        [$this->viewer, $this->viewerRole] = $this->users->newUserWithRole(
            ['name' => 'Issue 24 Viewer'],
            [
                'book-view-all',
                'chapter-view-all',
                'page-view-all',
            ]
        );
    }

    protected function setEntityPermissions(
        Entity $entity,
        array $actions,
        array $roles = [],
        bool $inherit = false
    ): void {
        // configuramos los permisos de la entidad
        $this->permissions->setEntityPermissions(
            $entity,
            $actions,
            $roles,
            $inherit
        );

        // verificamos la restriccion general cuando no se heredan permisos
        if (!$inherit) {
            $this->assertDatabaseHas('entity_permissions', [
                'entity_id' => $entity->id,
                'entity_type' => $entity->getMorphClass(),
                'role_id' => 0,
            ]);
        }

        // revisamos los permisos guardados para cada rol
        foreach ($roles as $role) {
            $this->assertDatabaseHas('entity_permissions', [
                'entity_id' => $entity->id,
                'entity_type' => $entity->getMorphClass(),
                'role_id' => $role->id,
                'view' => in_array('view', $actions, true),
                'create' => in_array('create', $actions, true),
                'update' => in_array('update', $actions, true),
                'delete' => in_array('delete', $actions, true),
            ]);
        }
    }

    protected function assertJointPermission(
        Entity $entity,
        Role $role,
        int $status
    ): void {
        // comprobamos el permiso final que tiene el rol sobre la entidad
        $this->assertDatabaseHas('joint_permissions', [
            'entity_id' => $entity->id,
            'entity_type' => $entity->getMorphClass(),
            'role_id' => $role->id,
            'status' => $status,
        ]);
    }

    public function test_it_pm_01_admin_puede_gestionar_libro_restringido(): void
    {
        // iniciamos sesion como administrador
        $this->actingAs($this->admin);

        // enviamos la solicitud para crear el libro
        $createResponse = $this->post('/books', [
            'name' => 'Libro administrativo IT-PM-01',
            'description_html' => '<p>Libro creado por el administrador.</p>',
        ]);

        // buscamos el libro que acabamos de crear
        $book = Book::query()
            ->where('name', 'Libro administrativo IT-PM-01')
            ->firstOrFail();

        // comprobamos que redirija al libro creado
        $createResponse->assertRedirect($book->getUrl());

        // confirmamos que el libro se guardo en la base de datos
        $this->assertDatabaseHas('entities', [
            'id' => $book->id,
            'type' => 'book',
            'name' => 'Libro administrativo IT-PM-01',
        ]);
    }
}