<?php

namespace Tests\Integration\App;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppHttpCoverageTest extends TestCase
{
    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'App HTTP ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'app-http-' . $roleName . '-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function createBookViaHttp(User $user, ?string $name = null, string $description = ''): Book
    {
        $name = $name ?: 'UAT45 App Libro ' . uniqid();

        $this->actingAs($user)
            ->post('/books', [
                'name' => $name,
                'description' => $description,
            ])
            ->assertRedirect();

        $book = Book::query()
            ->where('name', $name)
            ->latest('id')
            ->firstOrFail();

        $book->refresh();
        $book->rebuildPermissions();
        $book->indexForSearch();

        return $book->refresh();
    }

    protected function createChapterViaHttp(User $user, Book $book, ?string $name = null): Chapter
    {
        $name = $name ?: 'UAT45 App Capítulo ' . uniqid();

        $this->actingAs($user)
            ->post($book->getUrl('/create-chapter'), [
                'name' => $name,
                'description' => 'Capítulo creado para cobertura App.',
            ])
            ->assertRedirect();

        $chapter = Chapter::query()
            ->where('book_id', $book->id)
            ->where('name', $name)
            ->latest('id')
            ->firstOrFail();

        $book->refresh();
        $book->rebuildPermissions();
        $chapter->refresh();
        $chapter->indexForSearch();

        return $chapter->refresh();
    }

    protected function createPublishedPageViaHttp(
        User $user,
        Chapter $chapter,
        ?string $name = null,
        string $html = '<p>Contenido App</p>'
    ): Page {
        $name = $name ?: 'UAT45 App Página ' . uniqid();

        $this->actingAs($user)
            ->get($chapter->getUrl('/create-page'))
            ->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', true)
            ->where('created_by', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)
            ->post($draft->getUrl(), [
                'name' => $name,
                'html' => $html,
                'markdown' => '',
            ])
            ->assertRedirect();

        $page = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('name', $name)
            ->where('draft', false)
            ->latest('id')
            ->firstOrFail();

        $page->refresh();
        $page->indexForSearch();

        return $page->refresh();
    }

    public function test_meta_robots_respects_public_setting_and_config_override(): void
    {
        $originalAllowRobots = config('app.allow_robots');

        try {
            config()->set('app.allow_robots', null);
            $this->setSettings(['app-public' => 'false']);

            $blocked = $this->get('/robots.txt');
            $blocked->assertOk();

            $blockedContent = str_replace(["\r\n", "\r"], "\n", $blocked->getContent());

            $this->assertStringContainsString("User-agent: *", $blockedContent);
            $this->assertStringContainsString("Disallow: /", $blockedContent);
            $this->assertStringContainsString('text/plain', $blocked->headers->get('Content-Type'));

            $this->setSettings(['app-public' => 'true']);

            $public = $this->get('/robots.txt');
            $public->assertOk();

            $publicContent = str_replace(["\r\n", "\r"], "\n", $public->getContent());

            $this->assertStringContainsString("User-agent: *", $publicContent);
            $this->assertStringContainsString("Disallow:", $publicContent);
            $this->assertStringNotContainsString("Disallow: /", $publicContent);

            config()->set('app.allow_robots', false);

            $forcedBlocked = $this->get('/robots.txt');
            $forcedBlocked->assertOk();

            $forcedBlockedContent = str_replace(["\r\n", "\r"], "\n", $forcedBlocked->getContent());

            $this->assertStringContainsString("User-agent: *", $forcedBlockedContent);
            $this->assertStringContainsString("Disallow: /", $forcedBlockedContent);

            config()->set('app.allow_robots', true);

            $forcedAllowed = $this->get('/robots.txt');
            $forcedAllowed->assertOk();

            $forcedAllowedContent = str_replace(["\r\n", "\r"], "\n", $forcedAllowed->getContent());

            $this->assertStringContainsString("User-agent: *", $forcedAllowedContent);
            $this->assertStringContainsString("Disallow:", $forcedAllowedContent);
            $this->assertStringNotContainsString("Disallow: /", $forcedAllowedContent);
        } finally {
            config()->set('app.allow_robots', $originalAllowRobots);
        }
    }

    public function test_meta_manifest_endpoint_returns_pwa_manifest_json(): void
    {
        $this->setSettings([
            'app-name' => 'UAT45 Manifest App',
            'app-color' => '#00ACED',
        ]);

        $response = $this->get('/manifest.json');

        $response->assertOk();
        $response->assertJsonPath('name', 'UAT45 Manifest App');
        $response->assertJsonPath('short_name', 'UAT45 Manifest App');
        $response->assertJsonPath('display', 'standalone');
        $response->assertJsonPath('theme_color', '#00ACED');
        $response->assertJsonPath('launch_handler.client_mode', 'focus-existing');
        $response->assertJsonStructure([
            'icons' => [
                '*' => ['src', 'sizes', 'type'],
            ],
        ]);
    }

    public function test_meta_opensearch_endpoint_returns_xml_description(): void
    {
        setting()->put('app-name', 'UAT45 Coverage Search Application');

        $response = $this->get('/opensearch.xml');

        $response->assertOk();
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('OpenSearchDescription', false);
        $response->assertSee('Search UAT45 Coverage Search Application', false);

        $this->assertStringContainsString(
            'application/opensearchdescription+xml',
            $response->headers->get('Content-Type')
        );
    }

    public function test_meta_fallback_route_returns_404_view(): void
    {
        $this->get('/uat45-route-that-does-not-exist-' . uniqid())
            ->assertNotFound();
    }

    public function test_meta_licenses_endpoint_renders_license_information(): void
    {
        $response = $this->get('/licenses');

        $response->assertOk();
        $response->assertSee('License', false);
    }

    public function test_homepage_invalid_type_falls_back_to_default_homepage(): void
    {
        $admin = $this->userWithRole('admin');

        $this->setSettings([
            'app-homepage-type' => 'tipo-invalido-uat45',
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('home-default', false)
            ->assertSee('Recently Updated Pages');
    }

    public function test_homepage_books_type_renders_visible_books(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Visible En Home ' . uniqid(),
            'Libro usado para cubrir HomeController en modo books.'
        );

        $this->setSettings([
            'app-homepage-type' => 'books',
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee($book->name);
    }

    public function test_homepage_specific_page_type_renders_configured_page(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin, 'UAT45 Libro Home Page ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Home Configurada ' . uniqid(),
            '<p>Contenido único para cubrir homepage específica UAT45.</p>'
        );

        $this->setSettings([
            'app-homepage-type' => 'page',
            'app-homepage' => (string) $page->id,
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee($page->name)
            ->assertSee('Contenido único para cubrir homepage específica UAT45');
    }
        public function test_homepage_default_como_guest_muestra_contenido_publico(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Público Home Guest ' . uniqid(),
            'Libro público usado para cubrir HomeController como invitado.'
        );

        $this->setSettings([
            'app-public' => 'true',
            'app-homepage-type' => 'default',
        ]);

        auth()->logout();

        $this->get('/')
            ->assertOk()
            ->assertSee($book->name);
    }

    public function test_homepage_page_type_con_id_inexistente_retorna_404(): void
    {
        $admin = $this->userWithRole('admin');

        $this->setSettings([
            'app-homepage-type' => 'page',
            'app-homepage' => '999999999:',
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertNotFound();
    }
}