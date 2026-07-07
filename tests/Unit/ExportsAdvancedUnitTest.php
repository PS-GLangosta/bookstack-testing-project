<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Repos\BaseRepo;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Exceptions\ZipExportException;
use BookStack\Exports\Import;
use BookStack\Exports\ZipExports\Models\ZipExportBook;
use BookStack\Exports\ZipExports\Models\ZipExportChapter;
use BookStack\Exports\ZipExports\Models\ZipExportPage;
use BookStack\Exports\ZipExports\ZipExportBuilder;
use BookStack\Exports\ZipExports\ZipExportFiles;
use BookStack\Exports\ZipExports\ZipExportReferences;
use BookStack\Exports\ZipExports\ZipImportReferences;
use BookStack\Exports\ZipExports\ZipReferenceParser;
use BookStack\Uploads\Attachment;
use BookStack\Uploads\Image;
use BookStack\Uploads\ImageResizer;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class ExportsAdvancedUnitTest extends TestCase
{
    protected array $filesToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToDelete as $file) {
            if (is_string($file) && file_exists($file)) {
                unlink($file);
            }
        }

        Mockery::close();

        parent::tearDown();
    }

    protected function createZipBuilder(ZipExportFiles $files, ZipExportReferences $references): ZipExportBuilder
    {
        return new ZipExportBuilder($files, $references);
    }

    protected function assertZipContainsData(string $zipPath, string $rootKey, string $expectedName): void
    {
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);

        $this->assertTrue($opened === true, 'No se pudo abrir el ZIP generado.');

        $json = $zip->getFromName('data.json');

        $this->assertNotFalse($json);

        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey($rootKey, $data);
        $this->assertSame($expectedName, $data[$rootKey]['name']);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('instance', $data);

        $zip->close();

        $this->filesToDelete[] = $zipPath;
    }

    public function test_import_model_cubre_size_url_log_descriptor_y_decode_metadata(): void
    {
        $import = new Import();
        $import->id = 77;
        $import->name = 'Importación demo';
        $import->size = 2500000;

        $this->assertSame('2.5 MB', $import->getSizeString());
        $this->assertStringContainsString('/import/77/details', $import->getUrl('/details'));
        $this->assertSame('(77) Importación demo', $import->logDescriptor());

        $import->type = 'page';
        $import->metadata = json_encode([
            'id' => 10,
            'name' => 'Página importada',
            'html' => '<p>Contenido</p>',
            'markdown' => null,
            'priority' => 1,
            'attachments' => [],
            'images' => [],
            'tags' => [],
        ]);

        $pageMetadata = $import->decodeMetadata();

        $this->assertInstanceOf(ZipExportPage::class, $pageMetadata);
        $this->assertSame('Página importada', $pageMetadata->name);

        $import->type = 'chapter';
        $import->metadata = json_encode([
            'id' => 20,
            'name' => 'Capítulo importado',
            'description_html' => '<p>Descripción</p>',
            'priority' => 1,
            'tags' => [],
            'pages' => [],
        ]);

        $chapterMetadata = $import->decodeMetadata();

        $this->assertInstanceOf(ZipExportChapter::class, $chapterMetadata);
        $this->assertSame('Capítulo importado', $chapterMetadata->name);

        $import->type = 'book';
        $import->metadata = json_encode([
            'id' => 30,
            'name' => 'Libro importado',
            'description_html' => '<p>Descripción</p>',
            'cover' => null,
            'tags' => [],
            'pages' => [],
            'chapters' => [],
        ]);

        $bookMetadata = $import->decodeMetadata();

        $this->assertInstanceOf(ZipExportBook::class, $bookMetadata);
        $this->assertSame('Libro importado', $bookMetadata->name);

        $import->type = 'desconocido';

        $this->assertNull($import->decodeMetadata());
    }

    public function test_zip_reference_parser_parse_references_reemplaza_y_conserva_si_handler_retorna_null(): void
    {
        $queries = Mockery::mock(EntityQueries::class);
        $parser = new ZipReferenceParser($queries);

        $content = 'A [[bsexport:page:12]] B [[bsexport:image:99]] C [[bsexport:book:5]]';

        $result = $parser->parseReferences($content, function (string $type, int $id): ?string {
            if ($type === 'page' && $id === 12) {
                return '/books/demo/page';
            }

            if ($type === 'image' && $id === 99) {
                return '/uploads/images/demo.png';
            }

            return null;
        });

        $this->assertStringContainsString('/books/demo/page', $result);
        $this->assertStringContainsString('/uploads/images/demo.png', $result);
        $this->assertStringContainsString('[[bsexport:book:5]]', $result);
    }

    public function test_zip_reference_parser_parse_links_reemplaza_solo_modelos_resueltos(): void
    {
        $queries = Mockery::mock(EntityQueries::class);

        $page = new Page();
        $page->id = 500;
        $page->name = 'Página resuelta';

        $resolver = new class($page) {
            public function __construct(protected Page $page)
            {
            }

            public function resolve(string $link): ?Page
            {
                return str_contains($link, '/target-page') ? $this->page : null;
            }
        };

        $parser = new class($queries, [$resolver]) extends ZipReferenceParser {
            public function __construct(EntityQueries $queries, protected array $customResolvers)
            {
                parent::__construct($queries);
            }

            protected function getModelResolvers(): array
            {
                return $this->customResolvers;
            }
        };

        $targetUrl = url('/books/demo/page/target-page');
        $missingUrl = url('/books/demo/page/missing-page');

        $content = '<a href="' . $targetUrl . '">Target</a> <a href="' . $missingUrl . '">Missing</a>';

        $result = $parser->parseLinks($content, function (Page $model): ?string {
            return '[[bsexport:page:' . $model->id . ']]';
        });

        $this->assertStringContainsString('[[bsexport:page:500]]', $result);
        $this->assertStringContainsString($missingUrl, $result);
    }

    public function test_zip_export_builder_crea_zip_para_pagina_capitulo_y_libro(): void
    {
        $this->actingAs($this->users->admin());

        $files = Mockery::mock(ZipExportFiles::class);
        $references = Mockery::mock(ZipExportReferences::class);

        $files
            ->shouldReceive('extractEach')
            ->times(3)
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function (callable $callback): void {
                // No hay archivos adjuntos que extraer en esta prueba.
            });

        $references
            ->shouldReceive('addPage')
            ->once()
            ->with(Mockery::type(ZipExportPage::class));

        $references
            ->shouldReceive('addChapter')
            ->once()
            ->with(Mockery::type(ZipExportChapter::class));

        $references
            ->shouldReceive('addBook')
            ->once()
            ->with(Mockery::type(ZipExportBook::class));

        $references
            ->shouldReceive('buildReferences')
            ->times(3)
            ->with($files);

        $builder = $this->createZipBuilder($files, $references);

        $page = $this->entities->newPage([
            'name' => 'Página ZIP Builder Unit',
            'html' => '<p>Contenido ZIP Builder</p>',
        ]);

        $chapter = $this->entities->chapterHasPages();
        $book = $this->entities->bookHasChaptersAndPages();

        $pageZip = $builder->buildForPage($page);
        $this->assertZipContainsData($pageZip, 'page', 'Página ZIP Builder Unit');

        $chapterZip = $builder->buildForChapter($chapter);
        $this->assertZipContainsData($chapterZip, 'chapter', $chapter->name);

        $bookZip = $builder->buildForBook($book);
        $this->assertZipContainsData($bookZip, 'book', $book->name);
    }

    public function test_zip_export_builder_limpia_archivos_y_lanza_excepcion_si_falla_extract_each(): void
    {
        $this->actingAs($this->users->admin());

        $tempFile = tempnam(sys_get_temp_dir(), 'bs-builder-payload-');

        file_put_contents($tempFile, 'contenido temporal');

        $files = Mockery::mock(ZipExportFiles::class);
        $references = Mockery::mock(ZipExportReferences::class);

        $files
            ->shouldReceive('extractEach')
            ->once()
            ->with(Mockery::type('callable'))
            ->andReturnUsing(function (callable $callback) use ($tempFile): void {
                $callback($tempFile, 'payload.txt');

                throw new \Exception('fallo controlado al extraer archivo');
            });

        $references
            ->shouldReceive('addPage')
            ->once()
            ->with(Mockery::type(ZipExportPage::class));

        $references
            ->shouldReceive('buildReferences')
            ->once()
            ->with($files);

        $builder = $this->createZipBuilder($files, $references);

        $page = $this->entities->newPage([
            'name' => 'Página ZIP Builder Error Unit',
            'html' => '<p>Contenido ZIP Builder Error</p>',
        ]);

        try {
            $builder->buildForPage($page);
            $this->fail('Se esperaba ZipExportException.');
        } catch (ZipExportException $exception) {
            $this->assertStringContainsString('Failed to add files for ZIP export', $exception->getMessage());
            $this->assertFileDoesNotExist($tempFile);
        }
    }

    public function test_zip_import_references_reemplaza_referencias_en_book_chapter_page_y_registra_archivos(): void
    {
        $parser = Mockery::mock(ZipReferenceParser::class);
        $baseRepo = Mockery::mock(BaseRepo::class);
        $pageRepo = Mockery::mock(PageRepo::class);
        $imageResizer = Mockery::mock(ImageResizer::class);

        $references = new ZipImportReferences($parser, $baseRepo, $pageRepo, $imageResizer);

        $book = new Book();
        $book->id = 100;

        $chapter = new Chapter();
        $chapter->id = 200;

        $page = new Page();
        $page->id = 300;

        $exportBook = ZipExportBook::fromArray([
            'id' => 10,
            'name' => 'Libro exportado',
            'description_html' => '<p>Libro [[bsexport:page:30]]</p>',
            'cover' => null,
            'tags' => [],
            'pages' => [],
            'chapters' => [],
        ]);

        $exportChapter = ZipExportChapter::fromArray([
            'id' => 20,
            'name' => 'Capítulo exportado',
            'description_html' => '<p>Capítulo [[bsexport:book:10]]</p>',
            'priority' => 1,
            'tags' => [],
            'pages' => [],
        ]);

        $exportPage = ZipExportPage::fromArray([
            'id' => 30,
            'name' => 'Página exportada',
            'html' => '<p drawio-diagram="9">[[bsexport:image:9]]</p>',
            'markdown' => null,
            'priority' => 1,
            'attachments' => [],
            'images' => [],
            'tags' => [],
        ]);

        $drawioImage = new Image();
        $drawioImage->id = 999;
        $drawioImage->type = 'drawio';

        $attachment = new Attachment();
        $attachment->id = 888;

        $references->addBook($book, $exportBook);
        $references->addChapter($chapter, $exportChapter);
        $references->addPage($page, $exportPage);
        $references->addImage($drawioImage, 9);
        $references->addAttachment($attachment, 7);

        $parser
            ->shouldReceive('parseReferences')
            ->once()
            ->with('<p>Libro [[bsexport:page:30]]</p>', Mockery::type('callable'))
            ->andReturn('<p>Libro parseado</p>');

        $baseRepo
            ->shouldReceive('update')
            ->once()
            ->with($book, [
                'description_html' => '<p>Libro parseado</p>',
            ]);

        $parser
            ->shouldReceive('parseReferences')
            ->once()
            ->with('<p>Capítulo [[bsexport:book:10]]</p>', Mockery::type('callable'))
            ->andReturn('<p>Capítulo parseado</p>');

        $baseRepo
            ->shouldReceive('update')
            ->once()
            ->with($chapter, [
                'description_html' => '<p>Capítulo parseado</p>',
            ]);

        $parser
            ->shouldReceive('parseReferences')
            ->once()
            ->with('<p drawio-diagram="9">[[bsexport:image:9]]</p>', Mockery::type('callable'))
            ->andReturn('<p drawio-diagram="9">Imagen parseada</p>');

        $pageRepo
            ->shouldReceive('setContentFromInput')
            ->once()
            ->with($page, [
                'html' => '<p drawio-diagram="999">Imagen parseada</p>',
            ]);

        $references->replaceReferences();

        $this->assertSame([$drawioImage], $references->images());
        $this->assertSame([$attachment], $references->attachments());
    }
}