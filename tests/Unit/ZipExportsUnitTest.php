<?php

namespace Tests\Unit;

use BookStack\Activity\Models\Tag;
use BookStack\Exceptions\ZipExportException;
use BookStack\Exports\ZipExports\Models\ZipExportAttachment;
use BookStack\Exports\ZipExports\Models\ZipExportBook;
use BookStack\Exports\ZipExports\Models\ZipExportChapter;
use BookStack\Exports\ZipExports\Models\ZipExportImage;
use BookStack\Exports\ZipExports\Models\ZipExportPage;
use BookStack\Exports\ZipExports\Models\ZipExportTag;
use BookStack\Exports\ZipExports\ZipExportFiles;
use BookStack\Exports\ZipExports\ZipExportReader;
use BookStack\Exports\ZipExports\ZipExportValidator;
use BookStack\Exports\ZipExports\ZipValidationHelper;
use BookStack\Uploads\Attachment;
use BookStack\Uploads\AttachmentService;
use BookStack\Uploads\Image;
use BookStack\Uploads\ImageService;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class ZipExportsUnitTest extends TestCase
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

    protected function readerMock(): ZipExportReader
    {
        return Mockery::mock(ZipExportReader::class);
    }

    protected function failCollector(array &$messages): callable
    {
        return function (string $message) use (&$messages) {
            $messages[] = $message;

            return new class {
                public function translate(array $replace = [], ?string $locale = null): static
                {
                    return $this;
                }
            };
        };
    }

    protected function createZipFile(array $files): string
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive no está disponible en este entorno.');
        }

        $basePath = tempnam(sys_get_temp_dir(), 'bs-zip-unit-');

        if ($basePath !== false && file_exists($basePath)) {
            unlink($basePath);
        }

        $zipPath = $basePath . '.zip';

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->assertTrue($opened === true, 'No se pudo crear el ZIP temporal para la prueba.');

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        $this->filesToDelete[] = $zipPath;

        return $zipPath;
    }

    public function test_zip_export_models_from_array_children_metadata_only_y_json_serialize(): void
    {
        $book = ZipExportBook::fromArray([
            'id' => 1,
            'name' => 'Libro ZIP Unit',
            'description_html' => '<p>Descripción libro</p>',
            'cover' => 'cover.png',
            'tags' => [
                [
                    'name' => 'tipo',
                    'value' => 'manual',
                ],
            ],
            'pages' => [
                [
                    'id' => 3,
                    'name' => 'Página directa',
                    'html' => '<p>Contenido directo</p>',
                    'markdown' => 'Markdown directo',
                    'priority' => 20,
                    'attachments' => [
                        [
                            'id' => 10,
                            'name' => 'Adjunto PDF',
                            'file' => 'doc.pdf',
                        ],
                    ],
                    'images' => [
                        [
                            'id' => 11,
                            'name' => 'Imagen PNG',
                            'file' => 'image.png',
                            'type' => 'gallery',
                        ],
                    ],
                    'tags' => [
                        [
                            'name' => 'estado',
                            'value' => 'ok',
                        ],
                    ],
                ],
            ],
            'chapters' => [
                [
                    'id' => 2,
                    'name' => 'Capítulo ZIP',
                    'description_html' => '<p>Descripción capítulo</p>',
                    'priority' => 5,
                    'tags' => [],
                    'pages' => [
                        [
                            'id' => 4,
                            'name' => 'Página de capítulo',
                            'html' => '<p>Contenido capítulo</p>',
                            'markdown' => null,
                            'priority' => 1,
                            'attachments' => [],
                            'images' => [],
                            'tags' => [],
                        ],
                    ],
                ],
            ],
        ]);

        $children = $book->children();

        $this->assertSame(1, $book->id);
        $this->assertSame('Libro ZIP Unit', $book->name);

        $this->assertCount(2, $children);
        $this->assertInstanceOf(ZipExportChapter::class, $children[0]);
        $this->assertInstanceOf(ZipExportPage::class, $children[1]);
        $this->assertSame('Capítulo ZIP', $children[0]->name);
        $this->assertSame('Página directa', $children[1]->name);

        $serializedBeforeMetadata = $book->jsonSerialize();

        $this->assertArrayHasKey('description_html', $serializedBeforeMetadata);
        $this->assertArrayHasKey('cover', $serializedBeforeMetadata);

        $book->metadataOnly();

        $serializedAfterMetadata = $book->jsonSerialize();

        $this->assertArrayNotHasKey('description_html', $serializedAfterMetadata);
        $this->assertArrayNotHasKey('cover', $serializedAfterMetadata);

        $this->assertNull($book->description_html);
        $this->assertNull($book->cover);
        $this->assertNull($book->tags[0]->value);

        $this->assertNull($book->pages[0]->html);
        $this->assertNull($book->pages[0]->markdown);
        $this->assertNull($book->pages[0]->attachments[0]->file);
        $this->assertNull($book->pages[0]->tags[0]->value);

        $this->assertSame('image.png', $book->pages[0]->images[0]->file);

        $this->assertNull($book->chapters[0]->description_html);
        $this->assertNull($book->chapters[0]->pages[0]->html);
    }

    public function test_zip_export_attachment_from_model_cubre_adjunto_externo_y_archivo_local(): void
    {
        $files = Mockery::mock(ZipExportFiles::class);

        $files
            ->shouldReceive('referenceForAttachment')
            ->once()
            ->andReturn('local-file.pdf');

        $externalAttachment = new Attachment();
        $externalAttachment->id = 100;
        $externalAttachment->name = 'Adjunto externo';
        $externalAttachment->external = true;
        $externalAttachment->path = 'https://example.com/file.pdf';

        $localAttachment = new Attachment();
        $localAttachment->id = 101;
        $localAttachment->name = 'Adjunto local';
        $localAttachment->external = false;
        $localAttachment->extension = 'pdf';

        $externalExport = ZipExportAttachment::fromModel($externalAttachment, $files);
        $localExport = ZipExportAttachment::fromModel($localAttachment, $files);

        $this->assertSame('Adjunto externo', $externalExport->name);
        $this->assertSame('https://example.com/file.pdf', $externalExport->link);
        $this->assertNull($externalExport->file);

        $this->assertSame('Adjunto local', $localExport->name);
        $this->assertSame('local-file.pdf', $localExport->file);
        $this->assertNull($localExport->link);

        $localExport->metadataOnly();

        $this->assertNull($localExport->file);
        $this->assertNull($localExport->link);
    }

    public function test_zip_export_image_y_tag_from_model(): void
    {
        $files = Mockery::mock(ZipExportFiles::class);

        $files
            ->shouldReceive('referenceForImage')
            ->once()
            ->andReturn('image-ref.png');

        $image = new Image();
        $image->id = 200;
        $image->name = 'Imagen exportada';
        $image->type = 'gallery';
        $image->path = 'gallery/image.png';

        $tag = new Tag();
        $tag->name = 'categoria';
        $tag->value = 'documentacion';

        $imageExport = ZipExportImage::fromModel($image, $files);
        $tagExport = ZipExportTag::fromModel($tag);

        $this->assertSame(200, $imageExport->id);
        $this->assertSame('Imagen exportada', $imageExport->name);
        $this->assertSame('gallery', $imageExport->type);
        $this->assertSame('image-ref.png', $imageExport->file);

        $this->assertSame('categoria', $tagExport->name);
        $this->assertSame('documentacion', $tagExport->value);

        $tagExport->metadataOnly();

        $this->assertNull($tagExport->value);
    }

    public function test_zip_export_files_reutiliza_referencias_y_respeta_extensiones(): void
    {
        $attachmentService = Mockery::mock(AttachmentService::class);
        $imageService = Mockery::mock(ImageService::class);

        $files = new ZipExportFiles($attachmentService, $imageService);

        $attachment = new Attachment();
        $attachment->id = 301;
        $attachment->extension = 'pdf';

        $sameAttachment = new Attachment();
        $sameAttachment->id = 301;
        $sameAttachment->extension = 'pdf';

        $otherAttachment = new Attachment();
        $otherAttachment->id = 302;
        $otherAttachment->extension = 'txt';

        $image = new Image();
        $image->id = 401;
        $image->path = 'gallery/image-one.png';

        $sameImage = new Image();
        $sameImage->id = 401;
        $sameImage->path = 'gallery/image-one.png';

        $attachmentRef = $files->referenceForAttachment($attachment);
        $sameAttachmentRef = $files->referenceForAttachment($sameAttachment);
        $otherAttachmentRef = $files->referenceForAttachment($otherAttachment);

        $imageRef = $files->referenceForImage($image);
        $sameImageRef = $files->referenceForImage($sameImage);

        $this->assertStringEndsWith('.pdf', $attachmentRef);
        $this->assertStringEndsWith('.txt', $otherAttachmentRef);
        $this->assertStringEndsWith('.png', $imageRef);

        $this->assertSame($attachmentRef, $sameAttachmentRef);
        $this->assertSame($imageRef, $sameImageRef);
        $this->assertNotSame($attachmentRef, $otherAttachmentRef);
        $this->assertNotSame($attachmentRef, $imageRef);
    }

    public function test_zip_validation_helper_valida_data_ids_unicos_y_relaciones(): void
    {
        $reader = $this->readerMock();
        $helper = new ZipValidationHelper($reader);

        $this->assertFalse($helper->hasIdBeenUsed('page', 10));
        $this->assertTrue($helper->hasIdBeenUsed('page', 10));
        $this->assertFalse($helper->hasIdBeenUsed('chapter', 10));

        $validErrors = $helper->validateData(
            ['name' => 'Nombre válido'],
            ['name' => ['required', 'string', 'min:1']]
        );

        $invalidErrors = $helper->validateData(
            ['name' => ''],
            ['name' => ['required', 'string', 'min:1']]
        );

        $this->assertSame([], $validErrors);
        $this->assertArrayHasKey('name', $invalidErrors);

        $relationErrors = $helper->validateRelations([
            [
                'id' => 1,
                'name' => 'Página válida',
                'html' => '<p>Contenido</p>',
                'markdown' => null,
                'priority' => 1,
                'attachments' => [],
                'images' => [],
                'tags' => [],
            ],
            'relacion-invalida',
        ], ZipExportPage::class);

        $this->assertArrayHasKey(0, $relationErrors);
        $this->assertArrayHasKey(1, $relationErrors);
        $this->assertSame([], array_filter($relationErrors[0]));
        $this->assertNotEmpty($relationErrors[1]);
    }

    public function test_zip_unique_id_rule_y_file_reference_rule_cubren_exito_y_fallos(): void
    {
        $reader = $this->readerMock();
        $helper = new ZipValidationHelper($reader);

        $uniqueRule = $helper->uniqueIdRule('page');

        $uniqueMessages = [];

        $uniqueRule->validate('id', 77, $this->failCollector($uniqueMessages));
        $uniqueRule->validate('id', 77, $this->failCollector($uniqueMessages));

        $this->assertSame(['validation.zip_unique'], $uniqueMessages);

        $readerOk = $this->readerMock();

        $readerOk
            ->shouldReceive('fileExists')
            ->once()
            ->with('image.png')
            ->andReturn(true);

        $readerOk
            ->shouldReceive('fileWithinSizeLimit')
            ->once()
            ->with('image.png')
            ->andReturn(true);

        $readerOk
            ->shouldReceive('sniffFileMime')
            ->once()
            ->with('image.png')
            ->andReturn('image/png');

        $helperOk = new ZipValidationHelper($readerOk);
        $fileRuleOk = $helperOk->fileReferenceRule(['image/png']);

        $fileOkMessages = [];

        $fileRuleOk->validate('file', 'image.png', $this->failCollector($fileOkMessages));

        $this->assertSame([], $fileOkMessages);

        $readerFail = $this->readerMock();

        $readerFail
            ->shouldReceive('fileExists')
            ->once()
            ->with('bad.pdf')
            ->andReturn(false);

        $readerFail
            ->shouldReceive('fileWithinSizeLimit')
            ->once()
            ->with('bad.pdf')
            ->andReturn(false);

        $readerFail
            ->shouldReceive('sniffFileMime')
            ->once()
            ->with('bad.pdf')
            ->andReturn('application/pdf');

        $helperFail = new ZipValidationHelper($readerFail);
        $fileRuleFail = $helperFail->fileReferenceRule(['image/png']);

        $fileFailMessages = [];

        $fileRuleFail->validate('file', 'bad.pdf', $this->failCollector($fileFailMessages));

        $this->assertContains('validation.zip_file', $fileFailMessages);
        $this->assertContains('validation.zip_file_size', $fileFailMessages);
        $this->assertContains('validation.zip_file_mime', $fileFailMessages);
    }

    public function test_zip_export_validator_valida_book_page_chapter_y_errores_de_formato(): void
    {
        $bookReader = $this->readerMock();

        $bookReader
            ->shouldReceive('readData')
            ->once()
            ->andReturn([
                'book' => [
                    'id' => 1,
                    'name' => 'Libro válido',
                    'description_html' => '<p>Descripción</p>',
                    'cover' => null,
                    'tags' => [],
                    'pages' => [],
                    'chapters' => [],
                ],
            ]);

        $bookErrors = (new ZipExportValidator($bookReader))->validate();

        $this->assertSame([], $bookErrors);

        $chapterReader = $this->readerMock();

        $chapterReader
            ->shouldReceive('readData')
            ->once()
            ->andReturn([
                'chapter' => [
                    'id' => 2,
                    'name' => '',
                    'description_html' => '<p>Capítulo</p>',
                    'priority' => 1,
                    'tags' => [],
                    'pages' => [],
                ],
            ]);

        $chapterErrors = (new ZipExportValidator($chapterReader))->validate();

        $this->assertArrayHasKey('chapter.name', $chapterErrors);

        $pageReader = $this->readerMock();

        $pageReader
            ->shouldReceive('readData')
            ->once()
            ->andReturn([
                'page' => [
                    'id' => 3,
                    'name' => '',
                    'html' => '<p>Contenido</p>',
                    'markdown' => null,
                    'priority' => 1,
                    'attachments' => [],
                    'images' => [],
                    'tags' => [],
                ],
            ]);

        $pageErrors = (new ZipExportValidator($pageReader))->validate();

        $this->assertArrayHasKey('page.name', $pageErrors);

        $emptyReader = $this->readerMock();

        $emptyReader
            ->shouldReceive('readData')
            ->once()
            ->andReturn([]);

        $emptyErrors = (new ZipExportValidator($emptyReader))->validate();

        $this->assertArrayHasKey('format', $emptyErrors);

        $exceptionReader = $this->readerMock();

        $exceptionReader
            ->shouldReceive('readData')
            ->once()
            ->andThrow(new ZipExportException('ZIP inválido controlado'));

        $exceptionErrors = (new ZipExportValidator($exceptionReader))->validate();

        $this->assertSame(['format' => 'ZIP inválido controlado'], $exceptionErrors);
    }

    public function test_zip_export_reader_lee_data_archivos_y_decodifica_modelo(): void
    {
        $zipPath = $this->createZipFile([
            'data.json' => json_encode([
                'page' => [
                    'id' => 55,
                    'name' => 'Página desde ZIP real',
                    'html' => '<p>Contenido desde ZIP</p>',
                    'markdown' => null,
                    'priority' => 1,
                    'attachments' => [],
                    'images' => [],
                    'tags' => [],
                ],
            ]),
            'files/example.txt' => 'Contenido de archivo interno',
        ]);

        config([
            'app.upload_limit' => 1,
        ]);

        $reader = new ZipExportReader($zipPath);

        $data = $reader->readData();

        $this->assertArrayHasKey('page', $data);
        $this->assertSame('Página desde ZIP real', $data['page']['name']);

        $this->assertTrue($reader->fileExists('example.txt'));
        $this->assertFalse($reader->fileExists('missing.txt'));

        $this->assertTrue($reader->fileWithinSizeLimit('example.txt'));
        $this->assertFalse($reader->fileWithinSizeLimit('missing.txt'));

        $stream = $reader->streamFile('example.txt');

        $this->assertIsResource($stream);
        $this->assertSame('Contenido de archivo interno', stream_get_contents($stream));

        $mime = $reader->sniffFileMime('example.txt');

        $this->assertIsString($mime);
        $this->assertNotSame('', $mime);

        $model = $reader->decodeDataToExportModel();

        $this->assertInstanceOf(ZipExportPage::class, $model);
        $this->assertSame(55, $model->id);
        $this->assertSame('Página desde ZIP real', $model->name);

        $reader->close();
    }

    public function test_zip_export_reader_falla_con_zip_invalido(): void
    {
        $invalidZipPath = tempnam(sys_get_temp_dir(), 'bs-invalid-zip-');

        file_put_contents($invalidZipPath, 'contenido que no es zip');

        $this->filesToDelete[] = $invalidZipPath;

        $reader = new ZipExportReader($invalidZipPath);

        $this->expectException(ZipExportException::class);

        $reader->readData();
    }

    public function test_zip_export_reader_falla_si_no_puede_identificar_modelo(): void
    {
        $zipPath = $this->createZipFile([
            'data.json' => json_encode([
                'unknown' => [
                    'name' => 'Sin modelo válido',
                ],
            ]),
        ]);

        $reader = new ZipExportReader($zipPath);

        $this->expectException(ZipExportException::class);
        $this->expectExceptionMessage('Could not identify content in ZIP file data.');

        $reader->decodeDataToExportModel();
    }
}