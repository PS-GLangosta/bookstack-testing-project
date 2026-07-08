<?php
namespace Database\Seeders;

use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        if (Role::getRole('editor')->users()->count() === 0) {
            $editor = User::factory()->create(['name' => 'Test Editor']);
            $editor->attachRole(Role::getRole('editor'));
        }

        if (Role::getRole('viewer')->users()->count() === 0) {
            $viewer = User::factory()->create(['name' => 'Test Viewer']);
            $viewer->attachRole(Role::getRole('viewer'));
        }
    }
}
