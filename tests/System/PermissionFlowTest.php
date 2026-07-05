<?php

namespace Tests\System;

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
}