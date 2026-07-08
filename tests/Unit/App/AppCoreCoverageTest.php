<?php

namespace Tests\Unit\App;

use BookStack\App\Application;
use BookStack\App\MailNotification;
use BookStack\App\PwaManifestBuilder;
use BookStack\App\SystemApiController;
use BookStack\Translation\LocaleDefinition;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppCoreCoverageTest extends TestCase
{
    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'App Coverage ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'app-coverage-' . $roleName . '-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    public function test_application_config_path_points_to_app_config_directory(): void
    {
        $application = new Application('/tmp/bookstack-app-coverage');

        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config',
            $application->configPath()
        );

        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'app.php',
            $application->configPath('app.php')
        );
    }

    public function test_pwa_manifest_builder_uses_light_dark_settings_and_custom_icons(): void
    {
        $this->setSettings([
            'app-name' => 'UAT45 Coverage App',
            'app-color' => '#123456',
            'app-color-dark' => '#ABCDEF',
            'app-icon-32' => 'https://cdn.example.test/icon-32.png',
            'app-icon-64' => 'https://cdn.example.test/icon-64.png',
            'app-icon-128' => 'https://cdn.example.test/icon-128.png',
            'app-icon-180' => 'https://cdn.example.test/icon-180.png',
            'app-icon' => 'https://cdn.example.test/icon-256.png',
        ]);

        $lightUser = $this->userWithRole('viewer');
        $darkUser = $this->userWithRole('editor');

        setting()->putUser($darkUser, 'dark-mode-enabled', 'true');

        $lightManifest = $this->actingAs($lightUser)
            ->app
            ->make(PwaManifestBuilder::class)
            ->build();

        $this->assertSame('UAT45 Coverage App', $lightManifest['name']);
        $this->assertSame('UAT45 Coverage App', $lightManifest['short_name']);
        $this->assertSame('#F2F2F2', $lightManifest['background_color']);
        $this->assertSame('#123456', $lightManifest['theme_color']);
        $this->assertSame('focus-existing', $lightManifest['launch_handler']['client_mode']);
        $this->assertSame('https://cdn.example.test/icon-32.png', $lightManifest['icons'][0]['src']);
        $this->assertSame('32x32', $lightManifest['icons'][0]['sizes']);

        $darkManifest = $this->actingAs($darkUser)
            ->app
            ->make(PwaManifestBuilder::class)
            ->build();

        $this->assertSame('#111111', $darkManifest['background_color']);
        $this->assertSame('#ABCDEF', $darkManifest['theme_color']);
    }

    public function test_system_api_controller_returns_logo_variants_and_core_app_data(): void
    {
        $controller = $this->app->make(SystemApiController::class);

        $this->setSettings([
            'app-name' => 'UAT45 System API App',
            'instance-id' => 'uat45-instance-id',
            'app-logo' => '',
        ]);

        $defaultLogoData = $controller->read()->getData(true);

        $this->assertSame('UAT45 System API App', $defaultLogoData['app_name']);
        $this->assertSame('uat45-instance-id', $defaultLogoData['instance_id']);
        $this->assertSame(url('/logo.png'), $defaultLogoData['app_logo']);
        $this->assertSame(url('/'), $defaultLogoData['base_url']);
        $this->assertNotEmpty($defaultLogoData['version']);

        $this->setSettings(['app-logo' => 'none']);
        $noneLogoData = $controller->read()->getData(true);
        $this->assertNull($noneLogoData['app_logo']);

        $this->setSettings(['app-logo' => '/uploads/system-logo.png']);
        $customLogoData = $controller->read()->getData(true);
        $this->assertSame(url('/uploads/system-logo.png'), $customLogoData['app_logo']);
    }

    public function test_mail_notification_uses_mail_channel_and_default_notification_views(): void
    {
        $notification = new class extends MailNotification {
            public function toMail(User $notifiable): MailMessage
            {
                return $this->exposedNewMailMessage();
            }

            public function exposedNewMailMessage(?LocaleDefinition $locale = null): MailMessage
            {
                return $this->newMailMessage($locale);
            }
        };

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer);

        $this->assertSame(['mail'], $notification->via($viewer));

        $locale = new LocaleDefinition('en', 'en', false);
        $message = $notification->exposedNewMailMessage($locale);

        $this->assertSame([
            'html' => 'vendor.notifications.email',
            'text' => 'vendor.notifications.email-plain',
        ], $message->view);

        $this->assertSame($locale, $message->viewData['locale']);

        $defaultMessage = $notification->exposedNewMailMessage();
        $this->assertInstanceOf(LocaleDefinition::class, $defaultMessage->viewData['locale']);
    }

    public function test_versioned_asset_in_development_adds_file_hash_to_url(): void
    {
        $file = 'uat45-versioned-asset.css';
        $path = public_path($file);
        $originalEnv = config('app.env');

        file_put_contents($path, 'body { font-size: 14px; }');
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        try {
            config()->set('app.env', 'development');

            $url = versioned_asset($file);

            $this->assertStringContainsString($file . '?version=', $url);
            $this->assertStringContainsString(sha1_file($path), $url);
        } finally {
            config()->set('app.env', $originalEnv);
        }
    }
}