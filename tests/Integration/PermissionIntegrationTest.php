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

        // creamos un editor con permisos para ver crear y editar paginas
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
        // usamos el helper real para guardar y recalcular permisos
        $this->permissions->setEntityPermissions(
            $entity,
            $actions,
            $roles,
            $inherit
        );

        // cuando no heredamos debe existir la regla general
        if (!$inherit) {
            $this->assertEntityPermissionRow($entity, 0, []);
        }

        // cada rol debe guardar exactamente las acciones entregadas
        foreach ($roles as $role) {
            $this->assertEntityPermissionRow(
                $entity,
                $role->id,
                $actions
            );
        }
    }

    protected function assertEntityPermissionRow(
        Entity $entity,
        int $roleId,
        array $actions
    ): void {
        // convertimos las acciones en valores faciles de comparar
        $this->assertDatabaseHas('entity_permissions', [
            'entity_id' => $entity->id,
            'entity_type' => $entity->getMorphClass(),
            'role_id' => $roleId,
            'view' => in_array('view', $actions, true),
            'create' => in_array('create', $actions, true),
            'update' => in_array('update', $actions, true),
            'delete' => in_array('delete', $actions, true),
        ]);
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

        // recargamos los datos del libro desde la base de datos
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

        // comprobamos que vuelva al listado de libros
        $deleteResponse->assertRedirect('/books');

        // comprobamos que el libro haya quedado eliminado logicamente
        $this->assertSoftDeleted('entities', [
            'id' => $book->id,
            'type' => 'book',
        ]);
    }

    public function test_it_pm_02_editor_solo_edita_paginas_autorizadas(): void
    {
        // creamos una cadena de entidades que si podra editar
        $allowed = $this->entities
            ->createChainBelongingToUser($this->admin);

        // creamos otra cadena de entidades que no podra editar
        $denied = $this->entities
            ->createChainBelongingToUser($this->admin);

        // permitimos que el editor vea y actualice el primer libro
        $this->setEntityPermissions(
            $allowed['book'],
            ['view', 'update'],
            [$this->editorRole]
        );

        // permitimos que el editor solo vea el segundo libro
        $this->setEntityPermissions(
            $denied['book'],
            ['view'],
            [$this->editorRole]
        );

        // obtenemos las paginas de cada cadena
        $allowedPage = $allowed['page'];
        $deniedPage = $denied['page'];

        // guardamos el nombre original para comprobar que no cambie
        $deniedOriginalName = $deniedPage->name;

        // iniciamos sesion como editor
        $this->actingAs($this->editor);

        // actualizamos la pagina que si tiene permiso
        $allowedResponse = $this->put($allowedPage->getUrl(), [
            'name' => 'Página autorizada actualizada IT-PM-02',
            'html' => '<p>Actualización permitida.</p>',
        ]);

        // comprobamos que la actualizacion genere una redireccion
        $allowedResponse->assertRedirect();

        // verificamos que la pagina autorizada haya cambiado
        $this->assertDatabaseHas('entities', [
            'id' => $allowedPage->id,
            'type' => 'page',
            'name' => 'Página autorizada actualizada IT-PM-02',
        ]);

        // intentamos modificar la pagina sin permiso de actualizacion
        $deniedResponse = $this->put($deniedPage->getUrl(), [
            'name' => 'Página no autorizada modificada',
            'html' => '<p>Esta modificación debe rechazarse.</p>',
        ]);

        // comprobamos que bookstack rechace la solicitud
        $this->assertPermissionError($deniedResponse);

        // verificamos que la pagina conserve su nombre original
        $this->assertDatabaseHas('entities', [
            'id' => $deniedPage->id,
            'type' => 'page',
            'name' => $deniedOriginalName,
        ]);

        // comprobamos que el nombre no autorizado no se haya guardado
        $this->assertDatabaseMissing('entities', [
            'id' => $deniedPage->id,
            'name' => 'Página no autorizada modificada',
        ]);

        // comprobamos el permiso efectivo sobre la pagina autorizada
        $this->assertJointPermission(
            $allowedPage,
            $this->editorRole,
            PermissionStatus::EXPLICIT_ALLOW
        );

        // comprobamos el permiso efectivo de visualizacion sobre la otra pagina
        $this->assertJointPermission(
            $deniedPage,
            $this->editorRole,
            PermissionStatus::EXPLICIT_ALLOW
        );
    }

    public function test_it_pm_03_viewer_solo_lee_contenido_publico(): void
    {
        $content = $this->entities
            ->createChainBelongingToUser($this->admin);

        $book = $content['book'];
        $page = $content['page'];
        $originalName = $page->name;

        $this->actingAs($this->viewer)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name);

        $createResponse = $this->get(
            $book->getUrl('/create-page')
        );

        $this->assertPermissionError($createResponse);

        $updateResponse = $this->put($page->getUrl(), [
            'name' => 'Página alterada por viewer',
            'html' => '<p>Operación no permitida.</p>',
        ]);

        $this->assertPermissionError($updateResponse);

        $this->assertDatabaseHas('entities', [
            'id' => $page->id,
            'type' => 'page',
            'name' => $originalName,
        ]);

        $this->assertDatabaseMissing('entities', [
            'id' => $page->id,
            'name' => 'Página alterada por viewer',
        ]);

        $this->assertDatabaseMissing('entity_permissions', [
            'entity_id' => $book->id,
            'entity_type' => 'book',
        ]);

        $this->assertJointPermission(
            $page,
            $this->viewerRole,
            PermissionStatus::IMPLICIT_ALLOW
        );
    }

    public function test_it_pm_04_libro_privado_oculta_descendientes(): void
    {
        // armamos una cadena completa para probar la herencia
        $content = $this->entities
            ->createChainBelongingToUser($this->admin);

        $book = $content['book'];
        $chapter = $content['chapter'];
        $page = $content['page'];

        // dejamos el libro privado para usuarios normales
        $this->setEntityPermissions($book, []);

        // entramos como viewer para comprobar el bloqueo
        $this->actingAs($this->viewer);

        // el libro y sus hijos deben quedar ocultos
        $this->followingRedirects()
            ->get($book->getUrl())
            ->assertSee('Book not found');

        $this->followingRedirects()
            ->get($chapter->getUrl())
            ->assertSee('Chapter not found');

        $this->followingRedirects()
            ->get($page->getUrl())
            ->assertSee('Page not found');

        // revisamos el permiso calculado en cada nivel
        $this->assertJointPermission(
            $book,
            $this->viewerRole,
            PermissionStatus::IMPLICIT_DENY
        );

        $this->assertJointPermission(
            $chapter,
            $this->viewerRole,
            PermissionStatus::IMPLICIT_DENY
        );

        $this->assertJointPermission(
            $page,
            $this->viewerRole,
            PermissionStatus::IMPLICIT_DENY
        );
    }
}