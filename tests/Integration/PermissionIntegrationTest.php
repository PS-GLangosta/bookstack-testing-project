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
}