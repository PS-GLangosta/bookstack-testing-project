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

        // creamos el libro desde la ruta principal
        $createResponse = $this->post('/books', [
            'name' => 'Libro administrativo IT-PM-01',
            'description_html' => '<p>Libro creado por el administrador.</p>',
        ]);

        // buscamos el libro creado para continuar con la prueba
        $book = Book::query()
            ->where('name', 'Libro administrativo IT-PM-01')
            ->firstOrFail();

        // comprobamos que redirija al libro recien creado
        $createResponse->assertRedirect($book->getUrl());

        // verificamos que el libro exista en la base de datos
        $this->assertDatabaseHas('entities', [
            'id' => $book->id,
            'type' => 'book',
            'name' => 'Libro administrativo IT-PM-01',
        ]);

        // restringimos el libro sin dar permisos a ningun rol
        $this->setEntityPermissions($book, []);

        // intentamos actualizar el libro como administrador
        $updateResponse = $this->put($book->getUrl(), [
            'name' => 'Libro administrativo actualizado IT-PM-01',
            'description_html' => '<p>Contenido actualizado por el administrador.</p>',
        ]);

        // actualizamos los datos del modelo desde la base de datos
        $book->refresh();

        // comprobamos que redirija al libro actualizado
        $updateResponse->assertRedirect($book->getUrl());

        // verificamos que los nuevos datos se hayan guardado
        $this->assertDatabaseHas('entities', [
            'id' => $book->id,
            'type' => 'book',
            'name' => 'Libro administrativo actualizado IT-PM-01',
        ]);

        // eliminamos el libro como administrador
        $deleteResponse = $this->delete($book->getUrl());

        // despues de eliminar debe volver al listado de libros
        $deleteResponse->assertRedirect('/books');

        // comprobamos que el libro haya quedado eliminado logicamente
        $this->assertSoftDeleted('entities', [
            'id' => $book->id,
            'type' => 'book',
        ]);
    }

    public function test_it_pm_02_editor_solo_edita_paginas_autorizadas(): void
    {
        $allowed = $this->entities
            ->createChainBelongingToUser($this->admin);

        $this->setEntityPermissions(
            $allowed['book'],
            ['view', 'update'],
            [$this->editorRole]
        );

        $allowedPage = $allowed['page'];

        $this->actingAs($this->editor);

        $allowedResponse = $this->put($allowedPage->getUrl(), [
            'name' => 'Página autorizada actualizada IT-PM-02',
            'html' => '<p>Actualización permitida.</p>',
        ]);

        $allowedResponse->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'id' => $allowedPage->id,
            'type' => 'page',
            'name' => 'Página autorizada actualizada IT-PM-02',
        ]);

        $this->assertJointPermission(
            $allowedPage,
            $this->editorRole,
            PermissionStatus::EXPLICIT_ALLOW
        );
    }
    
}