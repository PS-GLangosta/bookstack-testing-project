<?php

namespace Tests\System;

use BookStack\Api\ApiToken;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Repos\ChapterRepo;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class SearchAndExportFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $baseSearchEndpoint = '/api/search';

    protected function userWithRole(string $roleName): User
    {
        $role = Role::getRole($roleName);

        $user = User::factory()->create([
            'name' => 'ST03 ' . ucfirst($roleName) . ' ' . uniqid(),
            'email' => 'st03-' . $roleName . '-' . uniqid() . '@example.com',
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->refresh();
    }

    protected function apiTokenHeaderFor(User $user, string $secret = 'st03-api-secret'): array
    {
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'secret' => Hash::make($secret),
        ]);

        return [
            'Authorization' => "Token {$token->token_id}:{$secret}",
        ];
    }

    protected function createBookFor(User $user, array $attributes = []): Book
    {
        $this->actingAs($user);

        $book = Book::factory()->create(array_merge([
            'name' => 'Libro ST03 ' . uniqid(),
            'description' => 'Libro creado para SearchAndExportFlowTest.',
            'description_html' => '<p>Libro creado para SearchAndExportFlowTest.</p>',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ], $attributes));

        $book->rebuildPermissions();
        $book->indexForSearch();

        return $book->refresh();
    }

    protected function createChapterFor(User $user, Book $book, array $attributes = []): Chapter
    {
        $this->actingAs($user);

        $chapter = app(ChapterRepo::class)->create(array_merge([
            'name' => 'Capítulo ST03 ' . uniqid(),
            'description' => 'Capítulo creado para SearchAndExportFlowTest.',
            'description_html' => '<p>Capítulo creado para SearchAndExportFlowTest.</p>',
        ], $attributes), $book);

        $book->refresh();
        $book->rebuildPermissions();

        $chapter->refresh();
        $chapter->indexForSearch();

        return $chapter;
    }

    protected function createPublishedPageFor(User $user, Book|Chapter $parent, array $data = []): Page
    {
        $this->actingAs($user);

        $pageRepo = app(PageRepo::class);
        $draft = $pageRepo->getNewDraftPage($parent);

        $page = $pageRepo->publishDraft($draft, array_merge([
            'name' => 'Página ST03 ' . uniqid(),
            'html' => '<p>Contenido ST03</p>',
        ], $data));

        $page->refresh();
        $page->indexForSearch();

        return $page;
    }

    protected function createSearchablePageAs(User $user, string $name, string $html): Page
    {
        $book = $this->createBookFor($user);

        return $this->createPublishedPageFor($user, $book, [
            'name' => $name,
            'html' => $html,
        ]);
    }

    protected function createBookWithChapterAndPages(User $user): array
    {
        $book = $this->createBookFor($user, [
            'name' => 'Libro exportable ST03 ' . uniqid(),
            'description' => 'Libro exportable para pruebas ST03.',
            'description_html' => '<p>Libro exportable para pruebas ST03.</p>',
        ]);

        $chapter = $this->createChapterFor($user, $book, [
            'name' => 'Capítulo exportable ST03 ' . uniqid(),
            'description' => 'Capítulo exportable para pruebas ST03.',
            'description_html' => '<p>Capítulo exportable para pruebas ST03.</p>',
        ]);

        $rootPage = $this->createPublishedPageFor($user, $book, [
            'name' => 'Página raíz exportable ST03 ' . uniqid(),
            'html' => '<p>Contenido de página raíz exportable ST03.</p>',
        ]);

        $chapterPage = $this->createPublishedPageFor($user, $chapter, [
            'name' => 'Página de capítulo exportable ST03 ' . uniqid(),
            'html' => '<p>Contenido de página dentro del capítulo ST03.</p>',
        ]);

        $book->refresh()->load(['pages', 'chapters', 'chapters.pages']);
        $chapter->refresh()->load(['pages']);

        return compact('book', 'chapter', 'rootPage', 'chapterPage');
    }

    protected function assertZipDownloadResponse($response, string $expectedFileName): void
    {
        $response->assertStatus(200);

        $this->assertStringContainsString(
            $expectedFileName,
            (string) $response->baseResponse->headers->get('Content-Disposition')
        );

        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        $zipContent = $response->streamedContent();

        $this->assertIsString($zipContent);
        $this->assertNotEmpty($zipContent);
        $this->assertStringStartsWith('PK', $zipContent);
    }

    protected function assertForbiddenExportResponse($response): void
    {
        $response->assertStatus(403);

        $this->assertFalse(
            $response->baseResponse->headers->has('Content-Disposition'),
            'No debería generarse una descarga cuando el usuario no tiene permiso de exportación.'
        );
    }

    /**
     * ST-03-01
     * Crear página con término único, buscar y verificar que aparece en resultados.
     */
    public function test_st_03_01_crear_pagina_con_termino_unico_y_buscar_aparece_en_resultados(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $uniqueTerm = 'ST03BusquedaUnica' . uniqid();

        $page = $this->createSearchablePageAs(
            $admin,
            'Página ST-03 búsqueda única',
            '<p>Contenido con término único ' . $uniqueTerm . '</p>'
        );

        $response = $this->getJson(
            $this->baseSearchEndpoint . '?query=' . urlencode($uniqueTerm) . '&count=10&page=1',
            $headers
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                    'url',
                    'preview_html',
                ],
            ],
            'total',
        ]);

        $response->assertJsonFragment([
            'id' => $page->id,
            'type' => 'page',
            'name' => $page->name,
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    /**
     * ST-03-02
     * Buscar término inexistente retorna resultados vacíos sin error.
     */
    public function test_st_03_02_buscar_termino_inexistente_retorna_resultados_vacios_sin_error(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $missingTerm = 'ST03TerminoInexistente' . uniqid();

        $response = $this->getJson(
            $this->baseSearchEndpoint . '?query=' . urlencode($missingTerm) . '&count=10&page=1',
            $headers
        );

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'total' => 0,
        ]);
    }

    /**
     * ST-03-03
     * Búsqueda con filtro de tipo page retorna solo páginas.
     */
    public function test_st_03_03_busqueda_con_filtro_de_tipo_page_solo_retorna_paginas(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $uniqueTerm = 'ST03FiltroPage' . uniqid();

        $page = $this->createSearchablePageAs(
            $admin,
            'Página con filtro ' . $uniqueTerm,
            '<p>Contenido page filtrado ' . $uniqueTerm . '</p>'
        );

        $book = $this->createBookFor($admin, [
            'name' => 'Libro con término ' . $uniqueTerm,
            'description' => 'Libro usado para validar filtro de tipo.',
            'description_html' => '<p>Libro usado para validar filtro de tipo.</p>',
        ]);

        $chapter = $this->createChapterFor($admin, $book, [
            'name' => 'Capítulo con término ' . $uniqueTerm,
            'description' => 'Capítulo usado para validar filtro de tipo.',
            'description_html' => '<p>Capítulo usado para validar filtro de tipo.</p>',
        ]);

        $book->indexForSearch();
        $chapter->indexForSearch();

        $query = urlencode($uniqueTerm . ' {type:page}');

        $response = $this->getJson(
            $this->baseSearchEndpoint . '?query=' . $query . '&count=10&page=1',
            $headers
        );

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $page->id,
            'type' => 'page',
            'name' => $page->name,
        ]);

        $results = $response->json('data');

        $this->assertNotEmpty($results);

        foreach ($results as $result) {
            $this->assertSame('page', $result['type']);
        }
    }

    /**
     * ST-03-04
     * Exportar página como texto plano retorna respuesta descargable con contenido.
     */
    public function test_st_03_04_exportar_pagina_como_texto_plano_retorna_respuesta_descargable(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página exportable TXT ST-03',
            '<p>Contenido exportable en texto plano ST-03</p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/plaintext', $headers);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename*=UTF-8''{$page->slug}.txt"
        );

        $response->assertSee($page->name);
        $response->assertSee('Contenido exportable en texto plano ST-03');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * ST-03-05
     * Exportar página como HTML retorna estructura HTML válida.
     */
    public function test_st_03_05_exportar_pagina_como_html_retorna_estructura_html_valida(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página exportable HTML ST-03',
            '<p>Contenido HTML exportable ST-03</p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/html', $headers);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename*=UTF-8''{$page->slug}.html"
        );

        $response->assertSee('<html', false);
        $response->assertSee('</html>', false);
        $response->assertSee($page->name);
        $response->assertSee('Contenido HTML exportable ST-03');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * ST-03-06
     * Exportar página eliminada retorna 404.
     */
    public function test_st_03_06_exportar_pagina_eliminada_retorna_404(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página eliminada ST-03',
            '<p>Contenido de página que será eliminada</p>'
        );

        $deleteResponse = $this->deleteJson('/api/pages/' . $page->id, [], $headers);

        $deleteResponse->assertStatus(204);

        $this->assertNull(Page::query()->find($page->id));

        $exportResponse = $this->get('/api/pages/' . $page->id . '/export/plaintext', $headers);

        $exportResponse->assertStatus(404);
        $exportResponse->assertJsonStructure([
            'error' => [
                'message',
                'code',
            ],
        ]);
        $exportResponse->assertJsonPath('error.code', 404);
    }

    /**
     * ST-03-07
     * Buscar sin parámetro query retorna error de validación 422.
     */
    public function test_st_03_07_buscar_sin_query_retorna_error_de_validacion_422(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $response = $this->getJson($this->baseSearchEndpoint . '?count=10&page=1', $headers);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'validation' => [
                    'query',
                ],
                'code',
            ],
        ]);
        $response->assertJsonPath('error.code', 422);
    }

    /**
     * ST-03-08
     * Buscar término existente retorna preview_html con resaltado del término.
     */
    public function test_st_03_08_buscar_termino_existente_retorna_preview_html_con_resaltado(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $uniqueTerm = 'ST03Preview' . uniqid();

        $page = $this->createSearchablePageAs(
            $admin,
            'Página preview ' . $uniqueTerm,
            '<p>Contenido especial para preview ' . $uniqueTerm . '</p>'
        );

        $response = $this->getJson(
            $this->baseSearchEndpoint . '?query=' . urlencode($uniqueTerm) . '&count=10&page=1',
            $headers
        );

        $response->assertStatus(200);

        $results = $response->json('data');
        $pageResult = collect($results)->firstWhere('id', $page->id);

        $this->assertNotNull($pageResult);
        $this->assertSame('page', $pageResult['type']);
        $this->assertArrayHasKey('preview_html', $pageResult);
        $this->assertArrayHasKey('name', $pageResult['preview_html']);
        $this->assertArrayHasKey('content', $pageResult['preview_html']);

        $previewName = $pageResult['preview_html']['name'];
        $previewContent = $pageResult['preview_html']['content'];

        $this->assertTrue(
            str_contains($previewName, '<strong>' . $uniqueTerm . '</strong>')
            || str_contains($previewContent, '<strong>' . $uniqueTerm . '</strong>'),
            'El término buscado debería aparecer resaltado en preview_html.'
        );
    }

    /**
     * ST-03-09
     * Exportar página como Markdown retorna archivo .md con contenido correcto.
     */
    public function test_st_03_09_exportar_pagina_como_markdown_retorna_archivo_md_con_contenido(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página exportable Markdown ST-03',
            '<p>Contenido exportable en markdown ST-03</p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/markdown', $headers);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename*=UTF-8''{$page->slug}.md"
        );

        $response->assertSee('# ' . $page->name);
        $response->assertSee('Contenido exportable en markdown ST-03');

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * ST-03-10
     * Exportar página sin permiso content-export retorna 403 y no genera descarga.
     */
    public function test_st_03_10_exportar_pagina_sin_permiso_content_export_retorna_403(): void
    {
        $admin = $this->userWithRole('admin');
        $editor = $this->userWithRole('editor');

        $page = $this->createSearchablePageAs(
            $admin,
            'Página protegida para exportación ST-03',
            '<p>Contenido que no debe exportarse sin permiso.</p>'
        );

        $this->permissions->removeUserRolePermissions($editor, ['content-export']);

        $headers = $this->apiTokenHeaderFor($editor);

        $response = $this->getJson('/api/pages/' . $page->id . '/export/html', $headers);

        $response->assertStatus(403);

        $this->assertFalse(
            $response->baseResponse->headers->has('Content-Disposition'),
            'No debería generarse una descarga cuando el usuario no tiene permiso de exportación.'
        );

        $this->assertFalse(
            $response->baseResponse->headers->has('Content-Length')
            && (int) $response->baseResponse->headers->get('Content-Length') > 0,
            'No debería generarse un archivo exportado con contenido cuando el acceso está prohibido.'
        );
    }

    /**
     * ST-03-11
     * Exportar página como PDF retorna archivo descargable.
     */
    public function test_st_03_11_exportar_pagina_como_pdf_retorna_archivo_descargable(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página exportable PDF ST-03',
            '<p>Contenido exportable en PDF ST-03</p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/pdf', $headers);

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename*=UTF-8''{$page->slug}.pdf"
        );

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * ST-03-12
     * Exportar página como ZIP retorna archivo descargable.
     */
    public function test_st_03_12_exportar_pagina_como_zip_retorna_archivo_descargable(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página exportable ZIP ST-03',
            '<p>Contenido exportable en ZIP ST-03</p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/zip', $headers);

        $this->assertZipDownloadResponse($response, $page->slug . '.zip');
    }

    /**
     * ST-03-13
     * Exportar libro como HTML, texto plano y Markdown retorna contenido correcto.
     */
    public function test_st_03_13_exportar_libro_en_formatos_textuales_retorna_contenido_correcto(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $entities = $this->createBookWithChapterAndPages($admin);

        /** @var Book $book */
        $book = $entities['book'];

        $htmlResponse = $this->get('/api/books/' . $book->id . '/export/html', $headers);

        $htmlResponse->assertStatus(200);
        $this->assertStringContainsString(
            $book->slug . '.html',
            (string) $htmlResponse->baseResponse->headers->get('Content-Disposition')
        );
        $htmlResponse->assertSee($book->name);
        $htmlResponse->assertSee('<html', false);

        $plainTextResponse = $this->get('/api/books/' . $book->id . '/export/plaintext', $headers);

        $plainTextResponse->assertStatus(200);
        $this->assertStringContainsString(
            $book->slug . '.txt',
            (string) $plainTextResponse->baseResponse->headers->get('Content-Disposition')
        );
        $plainTextResponse->assertSee($book->name);

        $markdownResponse = $this->get('/api/books/' . $book->id . '/export/markdown', $headers);

        $markdownResponse->assertStatus(200);
        $this->assertStringContainsString(
            $book->slug . '.md',
            (string) $markdownResponse->baseResponse->headers->get('Content-Disposition')
        );
        $markdownResponse->assertSee('# ' . $book->name);
    }

    /**
     * ST-03-14
     * Exportar capítulo como HTML, texto plano y Markdown retorna contenido correcto.
     */
    public function test_st_03_14_exportar_capitulo_en_formatos_textuales_retorna_contenido_correcto(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $entities = $this->createBookWithChapterAndPages($admin);

        /** @var Chapter $chapter */
        $chapter = $entities['chapter'];

        $this->assertNotNull($chapter);

        $htmlResponse = $this->get('/api/chapters/' . $chapter->id . '/export/html', $headers);

        $htmlResponse->assertStatus(200);
        $this->assertStringContainsString(
            $chapter->slug . '.html',
            (string) $htmlResponse->baseResponse->headers->get('Content-Disposition')
        );
        $htmlResponse->assertSee($chapter->name);
        $htmlResponse->assertSee('<html', false);

        $plainTextResponse = $this->get('/api/chapters/' . $chapter->id . '/export/plaintext', $headers);

        $plainTextResponse->assertStatus(200);
        $this->assertStringContainsString(
            $chapter->slug . '.txt',
            (string) $plainTextResponse->baseResponse->headers->get('Content-Disposition')
        );
        $plainTextResponse->assertSee($chapter->name);

        $markdownResponse = $this->get('/api/chapters/' . $chapter->id . '/export/markdown', $headers);

        $markdownResponse->assertStatus(200);
        $this->assertStringContainsString(
            $chapter->slug . '.md',
            (string) $markdownResponse->baseResponse->headers->get('Content-Disposition')
        );
        $markdownResponse->assertSee('# ' . $chapter->name);
    }

    /**
     * ST-03-15
     * Exportar libro y capítulo como PDF y ZIP retorna archivos descargables.
     */
    public function test_st_03_15_exportar_libro_y_capitulo_como_pdf_y_zip_retorna_archivos_descargables(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $entities = $this->createBookWithChapterAndPages($admin);

        /** @var Book $book */
        $book = $entities['book'];

        /** @var Chapter $chapter */
        $chapter = $entities['chapter'];

        $this->assertNotNull($chapter);

        $bookPdfResponse = $this->get('/api/books/' . $book->id . '/export/pdf', $headers);

        $bookPdfResponse->assertStatus(200);
        $this->assertStringContainsString(
            $book->slug . '.pdf',
            (string) $bookPdfResponse->baseResponse->headers->get('Content-Disposition')
        );
        $this->assertNotEmpty($bookPdfResponse->getContent());

        $chapterPdfResponse = $this->get('/api/chapters/' . $chapter->id . '/export/pdf', $headers);

        $chapterPdfResponse->assertStatus(200);
        $this->assertStringContainsString(
            $chapter->slug . '.pdf',
            (string) $chapterPdfResponse->baseResponse->headers->get('Content-Disposition')
        );
        $this->assertNotEmpty($chapterPdfResponse->getContent());

        $bookZipResponse = $this->get('/api/books/' . $book->id . '/export/zip', $headers);

        $this->assertZipDownloadResponse($bookZipResponse, $book->slug . '.zip');

        $chapterZipResponse = $this->get('/api/chapters/' . $chapter->id . '/export/zip', $headers);

        $this->assertZipDownloadResponse($chapterZipResponse, $chapter->slug . '.zip');
    }

    /**
     * ST-03-16
     * Exportar libro y capítulo sin permiso content-export retorna 403.
     */
    public function test_st_03_16_exportar_libro_y_capitulo_sin_permiso_content_export_retorna_403(): void
    {
        $admin = $this->userWithRole('admin');
        $editor = $this->userWithRole('editor');

        $entities = $this->createBookWithChapterAndPages($admin);

        /** @var Book $book */
        $book = $entities['book'];

        /** @var Chapter $chapter */
        $chapter = $entities['chapter'];

        $this->assertNotNull($chapter);

        $this->permissions->removeUserRolePermissions($editor, ['content-export']);

        $headers = $this->apiTokenHeaderFor($editor);

        $bookResponse = $this->getJson('/api/books/' . $book->id . '/export/html', $headers);

        $this->assertForbiddenExportResponse($bookResponse);

        $chapterResponse = $this->getJson('/api/chapters/' . $chapter->id . '/export/plaintext', $headers);

        $this->assertForbiddenExportResponse($chapterResponse);
    }

    /**
     * ST-03-17
     * Exportar página con contenido Markdown guardado retorna la fuente Markdown original.
     */
    public function test_st_03_17_exportar_pagina_markdown_con_fuente_markdown_retorna_markdown_original(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página Markdown Fuente ST-03',
            '<p>Contenido HTML alternativo ST-03</p>'
        );

        $page->markdown = "## Subtítulo Markdown ST-03\n\nContenido escrito desde Markdown original.";
        $page->save();

        $response = $this->get('/api/pages/' . $page->id . '/export/markdown', $headers);

        $response->assertStatus(200);

        $this->assertStringContainsString(
            $page->slug . '.md',
            (string) $response->baseResponse->headers->get('Content-Disposition')
        );

        $response->assertSee('# ' . $page->name);
        $response->assertSee('## Subtítulo Markdown ST-03');
        $response->assertSee('Contenido escrito desde Markdown original.');
    }

    /**
     * ST-03-18
     * Exportar página HTML con enlaces internos y externos preserva los enlaces.
     */
    public function test_st_03_18_exportar_pagina_html_con_enlaces_preserva_enlaces(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página HTML con enlaces ST-03',
            '<p><a href="/books">Enlace interno</a> <a href="https://example.com/docs">Enlace externo</a></p>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/html', $headers);

        $response->assertStatus(200);

        $this->assertStringContainsString(
            $page->slug . '.html',
            (string) $response->baseResponse->headers->get('Content-Disposition')
        );

        $response->assertSee('href="/books"', false);
        $response->assertSee('Enlace interno', false);
        $response->assertSee('href="https://example.com/docs"', false);
        $response->assertSee('Enlace externo', false);
    }

    /**
     * ST-03-19
     * Exportar página PDF con details e iframe genera archivo PDF descargable.
     */
    public function test_st_03_19_exportar_pagina_pdf_con_details_e_iframe_genera_pdf_descargable(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $page = $this->createSearchablePageAs(
            $admin,
            'Página PDF avanzada ST-03',
            '<details><summary>Más información</summary><p>Contenido oculto ST-03</p></details>
             <iframe src="//example.com/video"></iframe>'
        );

        $response = $this->get('/api/pages/' . $page->id . '/export/pdf', $headers);

        $response->assertStatus(200);

        $this->assertStringContainsString(
            $page->slug . '.pdf',
            (string) $response->baseResponse->headers->get('Content-Disposition')
        );

        $this->assertNotEmpty($response->getContent());
    }

    /**
     * ST-03-20
     * Exportar libro y capítulo inexistentes retorna 404.
     */
    public function test_st_03_20_exportar_libro_y_capitulo_inexistentes_retorna_404(): void
    {
        $admin = $this->userWithRole('admin');
        $headers = $this->apiTokenHeaderFor($admin);

        $missingId = 999999999;

        $bookResponse = $this->getJson('/api/books/' . $missingId . '/export/html', $headers);

        $bookResponse->assertStatus(404);

        $this->assertFalse(
            $bookResponse->baseResponse->headers->has('Content-Disposition'),
            'No debería generarse descarga para un libro inexistente.'
        );

        $chapterResponse = $this->getJson('/api/chapters/' . $missingId . '/export/zip', $headers);

        $chapterResponse->assertStatus(404);

        $this->assertFalse(
            $chapterResponse->baseResponse->headers->has('Content-Disposition'),
            'No debería generarse descarga para un capítulo inexistente.'
        );
    }
}