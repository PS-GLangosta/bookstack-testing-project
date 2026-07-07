<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Exceptions\ZipImportException;
use BookStack\Exceptions\ZipValidationException;
use BookStack\Exports\Import;
use BookStack\Exports\ImportRepo;
use BookStack\Exports\ZipExports\ZipImportRunner;
use BookStack\Uploads\FileStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ImportRepoUnitTest extends TestCase
{
    protected array $filesToDelete = [];

    protected function tearDown(): void
    {
        Mockery::close();

        gc_collect_cycles();

        foreach ($this->filesToDelete as $file) {
            if (!is_string($file)) {
                continue;
            }

            for ($attempt = 0; $attempt < 5; $attempt++) {
                clearstatcache(true, $file);

                if (!file_exists($file)) {
                    break;
                }

                if (@unlink($file)) {
                    break;
                }

                usleep(100000);
            }
        }

        $this->filesToDelete = [];

        parent::tearDown();
    }

    protected function repo(
        ?FileStorage $storage = null,
        ?ZipImportRunner $importer = null,
        ?EntityQueries $entityQueries = null,
    ): ImportRepo {
        $storage ??= Mockery::mock(FileStorage::class);
        $importer ??= Mockery::mock(ZipImportRunner::class);
        $entityQueries ??= Mockery::mock(EntityQueries::class);

        return new ImportRepo($storage, $importer, $entityQueries);
    }

    protected function createZipFile(array $data): string
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive no está disponible en este entorno.');
        }

        $basePath = tempnam(sys_get_temp_dir(), 'bs-import-repo-');

        if ($basePath !== false && file_exists($basePath)) {
            unlink($basePath);
        }

        $zipPath = $basePath . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->assertTrue($opened === true, 'No se pudo crear el ZIP temporal.');

        $zip->addFromString('data.json', json_encode($data));
        $zip->close();

        $this->filesToDelete[] = $zipPath;

        return $zipPath;
    }

    protected function uploadedZipFile(string $zipPath, string $name = 'import.zip'): UploadedFile
    {
        return new UploadedFile(
            $zipPath,
            $name,
            'application/zip',
            null,
            true
        );
    }

    protected function validPageZipData(string $name = 'Página importada desde ZIP'): array
    {
        return [
            'page' => [
                'id' => 10,
                'name' => $name,
                'html' => '<p>Contenido importado desde ZIP</p>',
                'markdown' => null,
                'priority' => 1,
                'attachments' => [],
                'images' => [],
                'tags' => [],
            ],
        ];
    }

    protected function createStoredImport(array $overrides = []): Import
    {
        $admin = $this->users->admin();

        $import = new Import();
        $import->name = $overrides['name'] ?? 'Import guardado';
        $import->type = $overrides['type'] ?? 'book';
        $import->path = $overrides['path'] ?? 'uploads/files/imports/demo.zip';
        $import->size = $overrides['size'] ?? 1234;
        $import->created_by = $overrides['created_by'] ?? $admin->id;
        $import->metadata = $overrides['metadata'] ?? json_encode([
            'id' => 1,
            'name' => 'Libro importado',
            'description_html' => '<p>Descripción</p>',
            'cover' => null,
            'tags' => [],
            'pages' => [],
            'chapters' => [],
        ]);

        $import->save();

        return $import;
    }

    public function test_get_visible_imports_filtra_por_usuario_si_no_tiene_permiso_settings_manage(): void
    {
        $admin = $this->users->admin();
        $editor = $this->users->editor();

        $ownImport = $this->createStoredImport([
            'name' => 'Import propio del editor',
            'created_by' => $editor->id,
        ]);

        $otherImport = $this->createStoredImport([
            'name' => 'Import de otro usuario',
            'created_by' => $admin->id,
        ]);

        $repo = $this->repo();

        $this->actingAs($editor);

        $editorVisibleIds = $repo->getVisibleImports()->pluck('id')->all();

        $this->assertContains($ownImport->id, $editorVisibleIds);
        $this->assertNotContains($otherImport->id, $editorVisibleIds);

        $this->actingAs($admin);

        $adminVisibleIds = $repo->getVisibleImports()->pluck('id')->all();

        $this->assertContains($ownImport->id, $adminVisibleIds);
        $this->assertContains($otherImport->id, $adminVisibleIds);
    }

    public function test_find_visible_respeta_visibilidad_del_usuario(): void
    {
        $admin = $this->users->admin();
        $editor = $this->users->editor();

        $ownImport = $this->createStoredImport([
            'created_by' => $editor->id,
        ]);

        $otherImport = $this->createStoredImport([
            'created_by' => $admin->id,
        ]);

        $repo = $this->repo();

        $this->actingAs($editor);

        $found = $repo->findVisible($ownImport->id);

        $this->assertSame($ownImport->id, $found->id);

        $this->expectException(ModelNotFoundException::class);

        $repo->findVisible($otherImport->id);
    }

    public function test_store_from_upload_guarda_import_valido_de_pagina(): void
    {
        $admin = $this->users->admin();

        $this->actingAs($admin);

        $zipPath = $this->createZipFile($this->validPageZipData('Página ZIP válida'));
        $uploadedFile = $this->uploadedZipFile($zipPath);

        $storage = Mockery::mock(FileStorage::class);

        $storage
            ->shouldReceive('uploadFile')
            ->once()
            ->with(
                Mockery::type(UploadedFile::class),
                'uploads/files/imports/',
                '',
                'zip'
            )
            ->andReturn('uploads/files/imports/stored-import.zip');

        $repo = $this->repo($storage);

        $import = $repo->storeFromUpload($uploadedFile);

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'name' => 'Página ZIP válida',
            'type' => 'page',
            'path' => 'uploads/files/imports/stored-import.zip',
            'created_by' => $admin->id,
        ]);

        $this->assertSame('Página ZIP válida', $import->name);
        $this->assertSame('page', $import->type);
        $this->assertSame(filesize($zipPath), $import->size);

        $metadata = $import->decodeMetadata();

        $this->assertSame('Página ZIP válida', $metadata->name);
        $this->assertNull($metadata->html);
        $this->assertNull($metadata->markdown);
    }

    public function test_store_from_upload_lanza_zip_validation_exception_si_zip_no_es_valido(): void
    {
        $this->actingAs($this->users->admin());

        $zipPath = $this->createZipFile([]);
        $uploadedFile = $this->uploadedZipFile($zipPath, 'invalid.zip');

        $storage = Mockery::mock(FileStorage::class);

        $storage->shouldNotReceive('uploadFile');

        $repo = $this->repo($storage);

        $this->expectException(ZipValidationException::class);

        $repo->storeFromUpload($uploadedFile);
    }

    public function test_run_import_de_libro_ejecuta_importer_elimina_import_y_retorna_entidad(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->createStoredImport([
            'type' => 'book',
            'path' => 'uploads/files/imports/book.zip',
        ]);

        $book = $this->entities->book();

        $storage = Mockery::mock(FileStorage::class);
        $importer = Mockery::mock(ZipImportRunner::class);
        $entityQueries = Mockery::mock(EntityQueries::class);

        $entityQueries->shouldNotReceive('findVisibleByStringIdentifier');

        $importer
            ->shouldReceive('run')
            ->once()
            ->with($import, null)
            ->andReturn($book);

        $storage
            ->shouldReceive('delete')
            ->once()
            ->with($import->path);

        $repo = $this->repo($storage, $importer, $entityQueries);

        $result = $repo->runImport($import);

        $this->assertSame($book->id, $result->id);
        $this->assertDatabaseMissing('imports', [
            'id' => $import->id,
        ]);
    }

    public function test_run_import_de_pagina_resuelve_parent_antes_de_importar(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->createStoredImport([
            'type' => 'page',
            'path' => 'uploads/files/imports/page.zip',
            'metadata' => json_encode([
                'id' => 1,
                'name' => 'Página importada',
                'html' => null,
                'markdown' => null,
                'priority' => 1,
                'attachments' => [],
                'images' => [],
                'tags' => [],
            ]),
        ]);

        $parentBook = $this->entities->book();

        $page = $this->entities->newPage([
            'name' => 'Página importada final',
            'html' => '<p>Contenido final</p>',
        ]);

        $storage = Mockery::mock(FileStorage::class);
        $importer = Mockery::mock(ZipImportRunner::class);
        $entityQueries = Mockery::mock(EntityQueries::class);

        $entityQueries
            ->shouldReceive('findVisibleByStringIdentifier')
            ->once()
            ->with('book:' . $parentBook->id)
            ->andReturn($parentBook);

        $importer
            ->shouldReceive('run')
            ->once()
            ->with($import, $parentBook)
            ->andReturn($page);

        $storage
            ->shouldReceive('delete')
            ->once()
            ->with($import->path);

        $repo = $this->repo($storage, $importer, $entityQueries);

        $result = $repo->runImport($import, 'book:' . $parentBook->id);

        $this->assertSame($page->id, $result->id);
        $this->assertDatabaseMissing('imports', [
            'id' => $import->id,
        ]);
    }

    public function test_run_import_hace_rollback_y_revierte_archivos_si_importer_falla(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->createStoredImport([
            'type' => 'book',
            'path' => 'uploads/files/imports/failing.zip',
        ]);

        $storage = Mockery::mock(FileStorage::class);
        $importer = Mockery::mock(ZipImportRunner::class);
        $entityQueries = Mockery::mock(EntityQueries::class);

        $storage->shouldNotReceive('delete');

        $entityQueries->shouldNotReceive('findVisibleByStringIdentifier');

        $importer
            ->shouldReceive('run')
            ->once()
            ->with($import, null)
            ->andThrow(new ZipImportException(['Fallo controlado en importer']));

        $importer
            ->shouldReceive('revertStoredFiles')
            ->once();

        $repo = $this->repo($storage, $importer, $entityQueries);

        try {
            $repo->runImport($import);
            $this->fail('Se esperaba ZipImportException.');
        } catch (ZipImportException $exception) {
            $this->assertSame(['Fallo controlado en importer'], $exception->errors);
        }

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
        ]);
    }

    public function test_delete_import_elimina_archivo_y_registro(): void
    {
        $this->actingAs($this->users->admin());

        $import = $this->createStoredImport([
            'path' => 'uploads/files/imports/delete-me.zip',
        ]);

        $storage = Mockery::mock(FileStorage::class);

        $storage
            ->shouldReceive('delete')
            ->once()
            ->with($import->path);

        $repo = $this->repo($storage);

        $repo->deleteImport($import);

        $this->assertDatabaseMissing('imports', [
            'id' => $import->id,
        ]);
    }
}