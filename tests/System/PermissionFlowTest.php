<?php

namespace Tests\System;

use BookStack\Entities\Models\Entity;
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

        $this->admin = $this->users->admin();
        $this->editor = $this->users->editor();
        $this->viewer = $this->users->viewer();
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