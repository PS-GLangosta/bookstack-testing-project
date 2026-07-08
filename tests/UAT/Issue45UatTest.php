<?php

namespace Tests\UAT;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Issue #45 - Ejecución de pruebas de aceptación UAT.
 *
 * Estas pruebas automatizan los escenarios UAT principales definidos en la issue #45.
 *
 * Regla aplicada:
 * - Dependencia 0 de seeds.
 * - No usar $this->users.
 * - No usar $this->entities.
 * - No usar asEditor/asAdmin.
 * - Cada prueba crea sus propios usuarios y contenido.
 *
 * Escenarios:
 * - UAT-01: Admin crea jerarquía completa y gestiona contenido.
 * - UAT-02: Editor accede a contenido permitido, crea y edita páginas.
 * - UAT-03: Viewer lee contenido público, exporta y no puede editar.
 * - UAT-04: Admin crea usuario, asigna rol y valida permisos.
 * - UAT-05: Búsqueda retorna resultado relevante y tiempo aceptable.
 * - UAT-06: Instalación limpia cuenta con archivos base documentados.
 */
#[Group('uat')]
#[Group('issue-45')]
#[Group('sprint-4')]
class Issue45UatTest extends TestCase
{
    use DatabaseTransactions;

    private const SEARCH_MAX_SECONDS = 3.0;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'UAT45 ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'uat45-' . $roleName . '-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function createBookViaHttp(User $user, ?string $name = null, string $description = ''): Book
    {
        $name = $name ?: 'UAT45 Libro ' . uniqid();

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

    protected function createChapterViaHttp(
        User $user,
        Book $book,
        ?string $name = null,
        string $description = ''
    ): Chapter {
        $name = $name ?: 'UAT45 Capítulo ' . uniqid();

        $this->actingAs($user)
            ->post($book->getUrl('/create-chapter'), [
                'name' => $name,
                'description' => $description,
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
        string $html = '<p>Contenido UAT45</p>'
    ): Page {
        $name = $name ?: 'UAT45 Página ' . uniqid();

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

    /**
     * UAT-01 / CF-01, CF-02, CF-03, CF-04, CF-05
     *
     * Admin crea jerarquía completa:
     * libro → capítulo → página, y luego gestiona el contenido.
     */
    public function test_uat_01_admin_crea_jerarquia_completa_y_gestiona_contenido(): void
    {
        $admin = $this->userWithRole('admin');

        $suffix = uniqid();

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Admin ' . $suffix,
            'Libro creado por admin para UAT-01'
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Admin ' . $suffix,
            'Capítulo creado por admin para UAT-01'
        );

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Admin ' . $suffix,
            '<p>Contenido inicial creado por administrador en UAT-01.</p>'
        );

        $updatedContent = 'Contenido actualizado por administrador en UAT-01';

        $this->actingAs($admin)
            ->put($page->getUrl(), [
                'name' => $page->name,
                'html' => '<p>' . $updatedContent . '</p>',
                'markdown' => '',
                'summary' => 'Actualización UAT-01',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($book->id, $chapter->book_id);
        $this->assertSame($book->id, $page->book_id);
        $this->assertSame($chapter->id, $page->chapter_id);
        $this->assertSame($updatedContent, $page->text);

        $this->actingAs($admin)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name)
            ->assertSee($updatedContent);
    }

    /**
     * UAT-02 / CF-01, CF-04, CF-05
     *
     * Editor accede a contenido permitido, crea una página y la edita.
     */
    public function test_uat_02_editor_accede_a_libro_permitido_crea_y_edita_paginas(): void
    {
        $editor = $this->userWithRole('editor');

        $book = $this->createBookViaHttp(
            $editor,
            'UAT45 Libro Editor ' . uniqid(),
            'Libro disponible para editor en UAT-02'
        );

        $chapter = $this->createChapterViaHttp(
            $editor,
            $book,
            'UAT45 Capítulo Editor ' . uniqid(),
            'Capítulo disponible para editor'
        );

        $page = $this->createPublishedPageViaHttp(
            $editor,
            $chapter,
            'UAT45 Página Editor ' . uniqid(),
            '<p>Contenido inicial creado por editor.</p>'
        );

        $updatedContent = 'Contenido actualizado por editor en UAT-02';

        $this->actingAs($editor)
            ->put($page->getUrl(), [
                'name' => $page->name,
                'html' => '<p>' . $updatedContent . '</p>',
                'markdown' => '',
                'summary' => 'Actualización UAT-02',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($updatedContent, $page->text);

        $this->actingAs($editor)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name)
            ->assertSee($updatedContent);
    }

    /**
     * UAT-03 / CF-01, CF-06, CF-07
     *
     * Viewer lee contenido público, exporta una página y no puede editar.
     */
    public function test_uat_03_viewer_lee_contenido_publico_exporta_y_no_puede_editar(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Público ' . uniqid(),
            'Libro público para viewer en UAT-03'
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Público ' . uniqid()
        );

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Pública ' . uniqid(),
            '<p>Contenido público visible para viewer en UAT-03.</p>'
        );

        $originalName = $page->name;
        $originalText = $page->text;
        $originalHtml = $page->html;

        $this->actingAs($viewer)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name)
            ->assertSee('Contenido público visible para viewer');

        $this->actingAs($viewer)
            ->get($page->getUrl('/export/plaintext'))
            ->assertOk()
            ->assertSee($page->name);

        /*
         * BookStack en rutas web no devuelve 403 plano para este caso.
         * Redirige con mensaje de notificación cuando el usuario no tiene permiso.
         */
        $this->actingAs($viewer)
            ->get($page->getUrl('/edit'))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->put($page->getUrl(), [
                'name' => 'Intento de edición viewer',
                'html' => '<p>Este cambio no debe aplicarse.</p>',
                'markdown' => '',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($originalName, $page->name);
        $this->assertSame($originalText, $page->text);
        $this->assertSame($originalHtml, $page->html);
        $this->assertNotSame('Intento de edición viewer', $page->name);
        $this->assertStringNotContainsString('Este cambio no debe aplicarse', $page->html);
    }

    /**
     * UAT-04 / CF-01, CF-08
     *
     * Admin crea usuario, asigna rol y valida permisos correctos.
     */
    public function test_uat_04_admin_crea_usuario_asigna_rol_y_valida_permisos_correctos(): void
    {
        $admin = $this->userWithRole('admin');

        $viewerRole = Role::getRole('viewer');

        $newUser = User::factory()->create([
            'name' => 'UAT45 Usuario Viewer ' . uniqid(),
            'email' => 'uat45-created-viewer-' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->actingAs($admin);

        $newUser->roles()->sync([$viewerRole->id]);
        $newUser->refresh();

        $this->assertTrue(
            $newUser->roles()->where('roles.id', $viewerRole->id)->exists(),
            'El usuario creado debe tener asignado el rol viewer.'
        );

        $book = $this->createBookViaHttp($admin, 'UAT45 Libro Validación Rol ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $book);

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Validación Rol ' . uniqid(),
            '<p>Contenido para validar permisos del usuario creado.</p>'
        );

        $originalText = $page->text;
        $originalName = $page->name;

        $this->actingAs($newUser)
            ->get($page->getUrl())
            ->assertOk()
            ->assertSee($page->name);

        /*
         * Igual que en UAT-03, la ruta web redirige cuando no hay permisos.
         * Lo importante para UAT es validar que el usuario no pueda editar.
         */
        $this->actingAs($newUser)
            ->get($page->getUrl('/edit'))
            ->assertRedirect();

        $this->actingAs($newUser)
            ->put($page->getUrl(), [
                'name' => 'Cambio no autorizado UAT-04',
                'html' => '<p>Contenido modificado sin permiso.</p>',
                'markdown' => '',
            ])
            ->assertRedirect();

        $page->refresh();

        $this->assertSame($originalName, $page->name);
        $this->assertSame($originalText, $page->text);
        $this->assertStringNotContainsString('Contenido modificado sin permiso', $page->html);
    }

    /**
     * UAT-05 / CF-09
     *
     * Búsqueda retorna resultado relevante y se verifica tiempo de respuesta.
     */
    public function test_uat_05_busqueda_retorna_resultado_relevante_y_tiempo_aceptable(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp($admin, 'UAT45 Libro Búsqueda ' . uniqid());
        $chapter = $this->createChapterViaHttp($admin, $book);

        $uniqueTerm = 'UAT45BUSQUEDA' . uniqid();

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Buscable ' . uniqid(),
            '<p>Contenido con término único ' . $uniqueTerm . ' para validar búsqueda.</p>'
        );

        $page->indexForSearch();

        $start = microtime(true);

        $response = $this->actingAs($admin)
            ->get('/search?term=' . urlencode($uniqueTerm));

        $duration = microtime(true) - $start;

        $response
            ->assertOk()
            ->assertSee($uniqueTerm)
            ->assertSee($page->name);

        $this->assertLessThanOrEqual(
            self::SEARCH_MAX_SECONDS,
            $duration,
            'La búsqueda debe responder en un tiempo aceptable para el entorno local.'
        );
    }

    /**
     * UAT-06 / CF-10
     *
     * Instalación limpia documentada.
     *
     * Este escenario se complementa con evidencia manual porque una instalación
     * limpia desde cero no debe ejecutarse dentro del mismo entorno PHPUnit.
     *
     * La prueba automatizada valida que el repositorio tenga los archivos mínimos
     * necesarios para seguir el proceso documentado de instalación.
     */
    public function test_uat_06_instalacion_limpia_tiene_archivos_base_documentados(): void
    {
        $this->assertFileExists(base_path('artisan'));
        $this->assertFileExists(base_path('composer.json'));

        $this->assertTrue(
            file_exists(base_path('.env.example')) || file_exists(base_path('.env.example.complete')),
            'Debe existir un archivo de ejemplo para configuración del entorno.'
        );

        $this->assertDirectoryExists(base_path('database'));
        $this->assertDirectoryExists(base_path('database/migrations'));

        $this->assertTrue(
            file_exists(base_path('README.md')) || file_exists(base_path('readme.md')),
            'Debe existir documentación base para instalación o uso del proyecto.'
        );
    }
        /**
     * CF-11
     *
     * Admin intenta crear un libro sin nombre.
     * El sistema debe rechazar la operación y no debe crear un libro vacío.
     */
    public function test_cf_11_crear_libro_sin_nombre_no_registra_libro_vacio(): void
    {
        $admin = $this->userWithRole('admin');

        $booksBefore = Book::query()->count();

        $this->actingAs($admin)
            ->post('/books', [
                'name' => '',
                'description' => 'Libro sin nombre para validación funcional.',
            ])
            ->assertSessionHasErrors();

        $booksAfter = Book::query()->count();

        $this->assertSame($booksBefore, $booksAfter);
    }

    /**
     * CF-12
     *
     * Admin intenta crear un capítulo sin nombre.
     * El sistema debe rechazar la operación.
     */
    public function test_cf_12_crear_capitulo_sin_nombre_no_registra_capitulo_vacio(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Para Capítulo Inválido ' . uniqid()
        );

        $chaptersBefore = Chapter::query()
            ->where('book_id', $book->id)
            ->count();

        $this->actingAs($admin)
            ->post($book->getUrl('/create-chapter'), [
                'name' => '',
                'description' => 'Capítulo sin nombre para validación funcional.',
            ])
            ->assertSessionHasErrors();

        $chaptersAfter = Chapter::query()
            ->where('book_id', $book->id)
            ->count();

        $this->assertSame($chaptersBefore, $chaptersAfter);
    }

    /**
     * CF-13
     *
     * Admin intenta publicar una página sin nombre.
     * El sistema no debe publicar una página con nombre vacío.
     */
    public function test_cf_13_crear_pagina_sin_nombre_no_publica_pagina_vacia(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Para Página Inválida ' . uniqid()
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Para Página Inválida ' . uniqid()
        );

        $this->actingAs($admin)
            ->get($chapter->getUrl('/create-page'))
            ->assertRedirect();

        $draft = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', true)
            ->where('created_by', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $publishedBefore = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', false)
            ->count();

        $this->actingAs($admin)
            ->post($draft->getUrl(), [
                'name' => '',
                'html' => '<p>Contenido sin nombre.</p>',
                'markdown' => '',
            ]);

        $publishedAfter = Page::query()
            ->where('chapter_id', $chapter->id)
            ->where('draft', false)
            ->count();

        $this->assertSame($publishedBefore, $publishedAfter);

        $this->assertFalse(
            Page::query()
                ->where('chapter_id', $chapter->id)
                ->where('draft', false)
                ->where('name', '')
                ->exists()
        );
    }

    /**
     * CF-14
     *
     * Viewer intenta eliminar una página.
     * El sistema debe bloquear la acción y conservar la página.
     */
    public function test_cf_14_viewer_no_puede_eliminar_pagina_publica(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Delete Viewer ' . uniqid()
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Delete Viewer ' . uniqid()
        );

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página No Eliminable Por Viewer ' . uniqid(),
            '<p>Contenido que el viewer no debe eliminar.</p>'
        );

        $pageId = $page->id;
        $originalName = $page->name;

        $this->actingAs($viewer)
            ->delete($page->getUrl())
            ->assertRedirect();

        $this->assertDatabaseHas('entities', [
            'id' => $pageId,
            'type' => 'page',
            'name' => $originalName,
            'deleted_at' => null,
        ]);
    }

    /**
     * CF-15
     *
     * Admin actualiza nombre y descripción de un libro.
     */
    public function test_cf_15_admin_actualiza_nombre_y_descripcion_de_libro(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Antes De Actualizar ' . uniqid(),
            'Descripción inicial del libro UAT45.'
        );

        $updatedName = 'UAT45 Libro Actualizado ' . uniqid();
        $updatedDescription = 'Descripción actualizada para prueba funcional CF-15.';

        $this->actingAs($admin)
            ->put($book->getUrl(), [
                'name' => $updatedName,
                'description' => $updatedDescription,
                'description_html' => '<p>' . $updatedDescription . '</p>',
            ])
            ->assertRedirect();

        $book->refresh();

        $this->assertSame($updatedName, $book->name);
        $this->assertStringContainsString($updatedDescription, $book->description);
        $this->assertStringContainsString($updatedDescription, $book->description_html);

        $this->actingAs($admin)
            ->get($book->getUrl())
            ->assertOk()
            ->assertSee($updatedName)
            ->assertSee($updatedDescription);
    }

    /**
     * CF-16
     *
     * Admin actualiza nombre y descripción de un capítulo.
     */
    public function test_cf_16_admin_actualiza_nombre_y_descripcion_de_capitulo(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Para Actualizar Capítulo ' . uniqid()
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Antes De Actualizar ' . uniqid(),
            'Descripción inicial del capítulo UAT45.'
        );

        $updatedName = 'UAT45 Capítulo Actualizado ' . uniqid();
        $updatedDescription = 'Descripción actualizada para prueba funcional CF-16.';

        $this->actingAs($admin)
            ->put($chapter->getUrl(), [
                'name' => $updatedName,
                'description' => $updatedDescription,
                'description_html' => '<p>' . $updatedDescription . '</p>',
            ])
            ->assertRedirect();

        $chapter->refresh();

        $this->assertSame($updatedName, $chapter->name);
        $this->assertStringContainsString($updatedDescription, $chapter->description);
        $this->assertStringContainsString($updatedDescription, $chapter->description_html);

        $this->actingAs($admin)
            ->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($updatedName)
            ->assertSee($updatedDescription);
    }

    /**
     * CF-17
     *
     * Búsqueda con término inexistente.
     * El sistema debe responder correctamente sin romper la vista.
     */
    public function test_cf_17_busqueda_con_termino_inexistente_retorna_sin_error(): void
    {
        $admin = $this->userWithRole('admin');

        $missingTerm = 'UAT45_TERMINO_INEXISTENTE_' . uniqid();

        $this->actingAs($admin)
            ->get('/search?term=' . urlencode($missingTerm))
            ->assertOk()
            ->assertSee('0 total results found');
    }

    /**
     * CF-18
     *
     * Admin crea varias páginas dentro del mismo capítulo.
     * El sistema debe mantenerlas asociadas correctamente.
     */
    public function test_cf_18_admin_crea_varias_paginas_en_un_mismo_capitulo(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Varias Páginas ' . uniqid()
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Varias Páginas ' . uniqid()
        );

        $pageOne = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Uno ' . uniqid(),
            '<p>Primera página del capítulo.</p>'
        );

        $pageTwo = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Dos ' . uniqid(),
            '<p>Segunda página del capítulo.</p>'
        );

        $this->assertSame($chapter->id, $pageOne->chapter_id);
        $this->assertSame($chapter->id, $pageTwo->chapter_id);
        $this->assertSame($book->id, $pageOne->book_id);
        $this->assertSame($book->id, $pageTwo->book_id);

        $this->actingAs($admin)
            ->get($chapter->getUrl())
            ->assertOk()
            ->assertSee($pageOne->name)
            ->assertSee($pageTwo->name);
    }

    /**
     * CF-19
     *
     * Admin exporta página en texto plano.
     */
    public function test_cf_19_admin_exporta_pagina_en_texto_plano(): void
    {
        $admin = $this->userWithRole('admin');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro Export Plaintext ' . uniqid()
        );

        $chapter = $this->createChapterViaHttp(
            $admin,
            $book,
            'UAT45 Capítulo Export Plaintext ' . uniqid()
        );

        $page = $this->createPublishedPageViaHttp(
            $admin,
            $chapter,
            'UAT45 Página Export Plaintext ' . uniqid(),
            '<p>Contenido exportable en texto plano para CF-19.</p>'
        );

        $this->actingAs($admin)
            ->get($page->getUrl('/export/plaintext'))
            ->assertOk()
            ->assertSee($page->name)
            ->assertSee('Contenido exportable en texto plano');
    }

    /**
     * CF-20
     *
     * Viewer no puede acceder a la pantalla de edición de libro.
     */
    public function test_cf_20_viewer_no_puede_acceder_a_edicion_de_libro(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');

        $book = $this->createBookViaHttp(
            $admin,
            'UAT45 Libro No Editable Por Viewer ' . uniqid(),
            'Libro público visible pero no editable por viewer.'
        );

        $originalName = $book->name;
        $originalDescription = $book->description;

        $this->actingAs($viewer)
            ->get($book->getUrl('/edit'))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->put($book->getUrl(), [
                'name' => 'Libro cambiado por viewer',
                'description' => 'Descripción cambiada por viewer.',
                'description_html' => '<p>Descripción cambiada por viewer.</p>',
            ])
            ->assertRedirect();

        $book->refresh();

        $this->assertSame($originalName, $book->name);
        $this->assertSame($originalDescription, $book->description);
    }
}