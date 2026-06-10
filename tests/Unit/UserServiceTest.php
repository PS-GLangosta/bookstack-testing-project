<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use BookStack\Users\UserRepo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    protected function validUserPayload(array $overrides = []): array
    {
        $role = Role::getRole('admin');
        $password = 'Password123!';

        return array_merge([
            'name' => 'Issue 17 User ' . Str::random(8),
            'email' => strtolower(Str::random(10)) . '@example.com',
            'password' => $password,
            'password-confirm' => $password,
            'roles[' . $role->id . ']' => 'true',
        ], $overrides);
    }

    public function test_ut_us_01_create_user_with_valid_email_and_password(): void
    {
        $payload = $this->validUserPayload();

        $this->asAdmin()
            ->post('/settings/users/create', $payload)
            ->assertRedirect('/settings/users');

        $this->assertDatabaseHas('users', [
            'name' => $payload['name'],
            'email' => $payload['email'],
        ]);
    }

    public function test_ut_us_02_duplicated_email_is_rejected(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'duplicated_issue_17@example.com',
        ]);

        $payload = $this->validUserPayload([
            'email' => $existingUser->email,
        ]);

        $this->asAdmin()
            ->from('/settings/users/create')
            ->post('/settings/users/create', $payload)
            ->assertRedirect('/settings/users/create')
            ->assertSessionHasErrors('email');
    }

    public function test_ut_us_03_password_shorter_than_8_characters_is_rejected(): void
    {
        $payload = $this->validUserPayload([
            'password' => '1234567',
            'password-confirm' => '1234567',
        ]);

        $this->asAdmin()
            ->from('/settings/users/create')
            ->post('/settings/users/create', $payload)
            ->assertRedirect('/settings/users/create')
            ->assertSessionHasErrors('password');
    }

    public function test_ut_us_04_wrong_password_confirmation_is_rejected(): void
    {
        $payload = $this->validUserPayload([
            'password' => 'Password123!',
            'password-confirm' => 'OtherPassword123!',
        ]);

        $this->asAdmin()
            ->from('/settings/users/create')
            ->post('/settings/users/create', $payload)
            ->assertRedirect('/settings/users/create')
            ->assertSessionHasErrors('password-confirm');
    }

    public function test_ut_us_05_delete_user_reassigns_owned_content_to_admin(): void
    {
        [$user] = $this->users->newUserWithRole([], []);
        $admin = $this->users->admin();

        $content = $this->entities->createChainBelongingToUser($user);

        /** @var Entity $book */
        $book = $content['book'];

        app(UserRepo::class)->destroy($user, $admin->id);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('entities', [
            'id' => $book->id,
            'owned_by' => $admin->id,
        ]);
    }

    public function test_user_repo_updates_user_name_and_email_when_management_is_allowed(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Issue 17 User',
            'email' => 'original_issue_17@example.com',
        ]);

        app(UserRepo::class)->updateWithoutActivity($user, [
            'name' => 'Updated Issue 17 User',
            'email' => 'updated_issue_17@example.com',
        ], true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Issue 17 User',
            'email' => 'updated_issue_17@example.com',
        ]);
    }

    public function test_user_repo_does_not_update_email_without_manage_permission(): void
    {
        $user = User::factory()->create([
            'name' => 'Viewer Issue 17 User',
            'email' => 'viewer_issue_17@example.com',
        ]);

        $originalEmail = $user->email;

        app(UserRepo::class)->updateWithoutActivity($user, [
            'name' => 'Viewer Name Updated',
            'email' => 'not_allowed_email_update@example.com',
        ], false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Viewer Name Updated',
            'email' => $originalEmail,
        ]);
    }

    public function test_user_repo_hashes_password_when_user_is_updated(): void
    {
        $user = User::factory()->create([
            'email' => 'password_update_issue_17@example.com',
        ]);

        $newPassword = 'NewPassword123!';

        app(UserRepo::class)->updateWithoutActivity($user, [
            'password' => $newPassword,
        ], true);

        $user->refresh();

        $this->assertTrue(Hash::check($newPassword, $user->password));
    }
}