<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Entity;
use BookStack\Exceptions\ZipImportException;
use BookStack\Exceptions\ZipValidationException;
use BookStack\Exports\Controllers\ImportApiController;
use BookStack\Exports\Controllers\ImportController;
use BookStack\Exports\Import;
use BookStack\Exports\ImportRepo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class ImportControllersUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function fakeImport(string $type = 'book', int $id = 100): Import
    {
        $import = new Import();
        $import->id = $id;
        $import->name = 'Import Unit ' . $type;
        $import->type = $type;
        $import->path = 'uploads/files/imports/unit.zip';
        $import->size = 123456;
        $import->created_by = $this->users->admin()->id;

        $import->metadata = match ($type) {
            'page' => json_encode([
                'id' => 10,
                'name' => 'Página importada unit',
                'html' => null,
                'markdown' => null,
                'priority' => 1,
                'attachments' => [],
                'images' => [],
                'tags' => [],
            ]),
            'chapter' => json_encode([
                'id' => 20,
                'name' => 'Capítulo importado unit',
                'description_html' => null,
                'priority' => 1,
                'tags' => [],
                'pages' => [],
            ]),
            default => json_encode([
                'id' => 30,
                'name' => 'Libro importado unit',
                'description_html' => null,
                'cover' => null,
                'tags' => [],
                'pages' => [],
                'chapters' => [],
            ]),
        };

        return $import;
    }

    protected function fakeZipUpload(): UploadedFile
    {
        return UploadedFile::fake()->create(
            'import-unit.zip',
            12,
            'application/zip'
        );
    }

    protected function requestWithFile(string $uri = '/import'): Request
    {
        $request = Request::create($uri, 'POST');
        $request->files->set('file', $this->fakeZipUpload());

        return $request;
    }

    public function test_import_api_controller_list_retorna_listado_json(): void
    {
        $this->actingAs($this->users->admin());

        $import = new Import();
        $import->name = 'Import listado API';
        $import->type = 'book';
        $import->path = 'uploads/files/imports/list.zip';
        $import->size = 1000;
        $import->created_by = $this->users->admin()->id;
        $import->metadata = json_encode([
            'id' => 1,
            'name' => 'Libro listado',
            'description_html' => null,
            'cover' => null,
            'tags' => [],
            'pages' => [],
            'chapters' => [],
        ]);
        $import->save();

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('queryVisible')
            ->once()
            ->andReturn(Import::query());

        $this->app->instance('request', Request::create('/api/imports', 'GET'));

        $controller = new ImportApiController($repo);

        $response = $controller->list();

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertArrayHasKey('data', $json);
        $this->assertNotEmpty($json['data']);
    }

    public function test_import_api_controller_create_guarda_import_y_retorna_json(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('page', 101);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('storeFromUpload')
            ->once()
            ->with(Mockery::type(UploadedFile::class))
            ->andReturn($import);

        $controller = new ImportApiController($repo);

        $response = $controller->create($this->requestWithFile('/api/imports'));

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame(101, $json['id']);
        $this->assertSame('Import Unit page', $json['name']);
        $this->assertSame('page', $json['type']);
    }

    public function test_import_api_controller_create_retorna_422_si_zip_no_valida(): void
    {
        $this->actingAs($this->users->admin());

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('storeFromUpload')
            ->once()
            ->with(Mockery::type(UploadedFile::class))
            ->andThrow(new ZipValidationException([
                'format' => 'ZIP inválido unit',
                'page.name' => 'Nombre requerido',
            ]));

        $controller = new ImportApiController($repo);

        $response = $controller->create($this->requestWithFile('/api/imports'));

        $this->assertSame(422, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame('error', $json['status']);
        $this->assertStringContainsString('ZIP upload failed', $json['message']);
        $this->assertStringContainsString('[format] ZIP inválido unit', $json['message']);
        $this->assertStringContainsString('[page.name] Nombre requerido', $json['message']);
    }

    public function test_import_api_controller_read_agrega_details_al_json(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 102);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(102)
            ->andReturn($import);

        $controller = new ImportApiController($repo);

        $response = $controller->read(102);

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame(102, $json['id']);
        $this->assertSame('book', $json['type']);
        $this->assertArrayHasKey('details', $json);
        $this->assertSame('Libro importado unit', $json['details']['name']);
    }

    public function test_import_api_controller_run_book_sin_parent_retorna_entidad_importada(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 103);
        $book = $this->entities->book();

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(103)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, null)
            ->andReturn($book);

        $controller = new ImportApiController($repo);

        $response = $controller->run(103, Request::create('/api/imports/103/run', 'POST'));

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame($book->id, $json['id']);
        $this->assertSame($book->name, $json['name']);
    }

    public function test_import_api_controller_run_page_con_parent_retorna_entidad_importada(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('page', 104);
        $page = $this->entities->newPage([
            'name' => 'Página importada API final',
            'html' => '<p>Contenido API final</p>',
        ]);

        $request = Request::create('/api/imports/104/run', 'POST', [
            'parent_type' => 'book',
            'parent_id' => 55,
        ]);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(104)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, 'book:55')
            ->andReturn($page);

        $controller = new ImportApiController($repo);

        $response = $controller->run(104, $request);

        $this->assertSame(200, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame($page->id, $json['id']);
        $this->assertSame($page->name, $json['name']);
    }

    public function test_import_api_controller_run_retorna_error_si_import_falla(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 105);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(105)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, null)
            ->andThrow(new ZipImportException([
                'Fallo unitario de importación',
            ]));

        $controller = new ImportApiController($repo);

        $response = $controller->run(105, Request::create('/api/imports/105/run', 'POST'));

        $this->assertSame(500, $response->getStatusCode());

        $json = $response->getData(true);

        $this->assertSame('error', $json['status']);
        $this->assertStringContainsString('ZIP import failed', $json['message']);
        $this->assertStringContainsString('Fallo unitario de importación', $json['message']);
    }

    public function test_import_api_controller_delete_elimina_import_y_retorna_204(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 106);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(106)
            ->andReturn($import);

        $repo
            ->shouldReceive('deleteImport')
            ->once()
            ->with($import);

        $controller = new ImportApiController($repo);

        $response = $controller->delete(106);

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_import_web_controller_start_muestra_vista_con_imports(): void
    {
        $this->actingAs($this->users->admin());

        $imports = new Collection([
            $this->fakeImport('book', 201),
            $this->fakeImport('page', 202),
        ]);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('getVisibleImports')
            ->once()
            ->andReturn($imports);

        session()->flash('validation_errors', [
            'format' => 'ZIP inválido previo',
        ]);

        $controller = new ImportController($repo);

        $view = $controller->start();

        $data = $view->getData();

        $this->assertArrayHasKey('imports', $data);
        $this->assertArrayHasKey('zipErrors', $data);
        $this->assertSame($imports, $data['imports']);
        $this->assertSame(['format' => 'ZIP inválido previo'], $data['zipErrors']);
    }

    public function test_import_web_controller_upload_redirige_al_import_guardado(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 203);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('storeFromUpload')
            ->once()
            ->with(Mockery::type(UploadedFile::class))
            ->andReturn($import);

        $controller = new ImportController($repo);

        $response = $controller->upload($this->requestWithFile('/import'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($import->getUrl(), $response->headers->get('Location'));
    }

    public function test_import_web_controller_upload_redirige_con_errores_si_zip_no_valida(): void
    {
        $this->actingAs($this->users->admin());

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('storeFromUpload')
            ->once()
            ->with(Mockery::type(UploadedFile::class))
            ->andThrow(new ZipValidationException([
                'format' => 'ZIP inválido web',
            ]));

        $controller = new ImportController($repo);

        $response = $controller->upload($this->requestWithFile('/import'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/import', $response->headers->get('Location'));
        $this->assertSame(['format' => 'ZIP inválido web'], session('validation_errors'));
    }

    public function test_import_web_controller_show_muestra_import_y_metadata(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('chapter', 204);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(204)
            ->andReturn($import);

        $controller = new ImportController($repo);

        $view = $controller->show(204);

        $data = $view->getData();

        $this->assertSame($import, $data['import']);
        $this->assertSame('Capítulo importado unit', $data['data']->name);
    }

    public function test_import_web_controller_run_book_redirige_a_entidad_importada(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 205);
        $book = $this->entities->book();

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(205)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, null)
            ->andReturn($book);

        $controller = new ImportController($repo);

        $response = $controller->run(205, Request::create('/import/205/run', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($book->getUrl(), $response->headers->get('Location'));
    }

    public function test_import_web_controller_run_page_con_parent_redirige_a_entidad_importada(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('page', 206);

        $page = $this->entities->newPage([
            'name' => 'Página importada web final',
            'html' => '<p>Contenido web final</p>',
        ]);

        $request = Request::create('/import/206/run', 'POST', [
            'parent' => 'book:10',
        ]);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(206)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, 'book:10')
            ->andReturn($page);

        $controller = new ImportController($repo);

        $response = $controller->run(206, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($page->getUrl(), $response->headers->get('Location'));
    }

    public function test_import_web_controller_run_fallido_redirige_con_errores(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 207);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(207)
            ->andReturn($import);

        $repo
            ->shouldReceive('runImport')
            ->once()
            ->with($import, null)
            ->andThrow(new ZipImportException([
                'Fallo web unitario',
            ]));

        $controller = new ImportController($repo);

        $response = $controller->run(207, Request::create('/import/207/run', 'POST'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($import->getUrl(), $response->headers->get('Location'));
        $this->assertSame(['Fallo web unitario'], session('import_errors'));
    }

    public function test_import_web_controller_delete_elimina_y_redirige_a_import(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->fakeImport('book', 208);

        $repo = Mockery::mock(ImportRepo::class);

        $repo
            ->shouldReceive('findVisible')
            ->once()
            ->with(208)
            ->andReturn($import);

        $repo
            ->shouldReceive('deleteImport')
            ->once()
            ->with($import);

        $controller = new ImportController($repo);

        $response = $controller->delete(208);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/import', $response->headers->get('Location'));
    }
}