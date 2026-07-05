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

        $this->admin = $this->users->admin();

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
        $this->permissions->setEntityPermissions(
            $entity,
            $actions,
            $roles,
            $inherit
        );

        if (!$inherit) {
            $this->assertDatabaseHas('entity_permissions', [
                'entity_id' => $entity->id,
                'entity_type' => $entity->getMorphClass(),
                'role_id' => 0,
            ]);
        }

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
        $this->assertDatabaseHas('joint_permissions', [
            'entity_id' => $entity->id,
            'entity_type' => $entity->getMorphClass(),
            'role_id' => $role->id,
            'status' => $status,
        ]);
    }
}