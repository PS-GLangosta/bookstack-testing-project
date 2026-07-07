<?php

namespace Tests\Unit;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Queries\BookQueries;
use BookStack\Entities\Queries\ChapterQueries;
use BookStack\Entities\Queries\PageQueries;
use BookStack\Exports\Controllers\BookExportApiController;
use BookStack\Exports\Controllers\BookExportController;
use BookStack\Exports\Controllers\ChapterExportApiController;
use BookStack\Exports\Controllers\ChapterExportController;
use BookStack\Exports\Controllers\PageExportApiController;
use BookStack\Exports\Controllers\PageExportController;
use BookStack\Exports\ExportFormatter;
use BookStack\Exports\ZipExports\ZipExportBuilder;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ExportControllersUnitTest extends TestCase
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

    protected function tempExportFile(string $content = 'ZIP-CONTENT'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'bs-export-controller-');

        file_put_contents($file, $content);

        $this->filesToDelete[] = $file;

        return $file;
    }

    protected function assertDirectDownload($response, string $expectedFileName, string $expectedContent): void
    {
        $this->assertSame(200, $response->getStatusCode());

        $this->assertStringContainsString(
            $expectedFileName,
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertStringContainsString($expectedContent, (string) $response->getContent());
    }

    protected function assertStreamedDownload($response, string $expectedFileName, string $expectedContent): void
    {
        $this->assertSame(200, $response->getStatusCode());

        $this->assertStringContainsString(
            $expectedFileName,
            (string) $response->headers->get('Content-Disposition')
        );

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $streamedContent = ob_get_clean();

        $this->assertStringContainsString($expectedContent, $streamedContent);
    }

    protected function simplePage(): Page
    {
        $page = new Page();
        $page->id = 10;
        $page->name = 'Página Export Controller Unit';
        $page->slug = 'pagina-export-controller-unit';
        $page->html = '<p>Contenido de página para export controller.</p>';
        $page->markdown = 'Contenido markdown de página.';

        return $page;
    }

    protected function simpleBook(): Book
    {
        $book = new Book();
        $book->id = 20;
        $book->name = 'Libro Export Controller Unit';
        $book->slug = 'libro-export-controller-unit';
        $book->description = 'Descripción del libro.';

        return $book;
    }

    protected function simpleChapter(): Chapter
    {
        $chapter = new Chapter();
        $chapter->id = 30;
        $chapter->name = 'Capítulo Export Controller Unit';
        $chapter->slug = 'capitulo-export-controller-unit';
        $chapter->description = 'Descripción del capítulo.';

        return $chapter;
    }

    public function test_page_export_api_controller_cubre_pdf_html_plaintext_markdown_y_zip(): void
    {
        $page = $this->simplePage();

        $queries = Mockery::mock(PageQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleByIdOrFail')
            ->times(5)
            ->with($page->id)
            ->andReturn($page);

        $formatter
            ->shouldReceive('pageToPdf')
            ->once()
            ->with($page)
            ->andReturn('%PDF-PAGE-API%');

        $formatter
            ->shouldReceive('pageToContainedHtml')
            ->once()
            ->with($page)
            ->andReturn('<html>PAGE API HTML</html>');

        $formatter
            ->shouldReceive('pageToPlainText')
            ->once()
            ->with($page)
            ->andReturn('PAGE API TEXT');

        $formatter
            ->shouldReceive('pageToMarkdown')
            ->once()
            ->with($page)
            ->andReturn('# PAGE API MARKDOWN');

        $zipPath = $this->tempExportFile('PAGE API ZIP');

        $builder
            ->shouldReceive('buildForPage')
            ->once()
            ->with($page)
            ->andReturn($zipPath);

        $controller = new PageExportApiController($formatter, $queries);

        $this->assertDirectDownload(
            $controller->exportPdf($page->id),
            $page->slug . '.pdf',
            '%PDF-PAGE-API%'
        );

        $this->assertDirectDownload(
            $controller->exportHtml($page->id),
            $page->slug . '.html',
            'PAGE API HTML'
        );

        $this->assertDirectDownload(
            $controller->exportPlainText($page->id),
            $page->slug . '.txt',
            'PAGE API TEXT'
        );

        $this->assertDirectDownload(
            $controller->exportMarkdown($page->id),
            $page->slug . '.md',
            '# PAGE API MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->exportZip($page->id, $builder),
            $page->slug . '.zip',
            'PAGE API ZIP'
        );
    }

    public function test_book_export_api_controller_cubre_pdf_html_plaintext_markdown_y_zip(): void
    {
        $book = $this->simpleBook();

        $queries = Mockery::mock(BookQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleByIdOrFail')
            ->times(5)
            ->with($book->id)
            ->andReturn($book);

        $formatter
            ->shouldReceive('bookToPdf')
            ->once()
            ->with($book)
            ->andReturn('%PDF-BOOK-API%');

        $formatter
            ->shouldReceive('bookToContainedHtml')
            ->once()
            ->with($book)
            ->andReturn('<html>BOOK API HTML</html>');

        $formatter
            ->shouldReceive('bookToPlainText')
            ->once()
            ->with($book)
            ->andReturn('BOOK API TEXT');

        $formatter
            ->shouldReceive('bookToMarkdown')
            ->once()
            ->with($book)
            ->andReturn('# BOOK API MARKDOWN');

        $zipPath = $this->tempExportFile('BOOK API ZIP');

        $builder
            ->shouldReceive('buildForBook')
            ->once()
            ->with($book)
            ->andReturn($zipPath);

        $controller = new BookExportApiController($formatter, $queries);

        $this->assertDirectDownload(
            $controller->exportPdf($book->id),
            $book->slug . '.pdf',
            '%PDF-BOOK-API%'
        );

        $this->assertDirectDownload(
            $controller->exportHtml($book->id),
            $book->slug . '.html',
            'BOOK API HTML'
        );

        $this->assertDirectDownload(
            $controller->exportPlainText($book->id),
            $book->slug . '.txt',
            'BOOK API TEXT'
        );

        $this->assertDirectDownload(
            $controller->exportMarkdown($book->id),
            $book->slug . '.md',
            '# BOOK API MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->exportZip($book->id, $builder),
            $book->slug . '.zip',
            'BOOK API ZIP'
        );
    }

    public function test_chapter_export_api_controller_cubre_pdf_html_plaintext_markdown_y_zip(): void
    {
        $chapter = $this->simpleChapter();

        $queries = Mockery::mock(ChapterQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleByIdOrFail')
            ->times(5)
            ->with($chapter->id)
            ->andReturn($chapter);

        $formatter
            ->shouldReceive('chapterToPdf')
            ->once()
            ->with($chapter)
            ->andReturn('%PDF-CHAPTER-API%');

        $formatter
            ->shouldReceive('chapterToContainedHtml')
            ->once()
            ->with($chapter)
            ->andReturn('<html>CHAPTER API HTML</html>');

        $formatter
            ->shouldReceive('chapterToPlainText')
            ->once()
            ->with($chapter)
            ->andReturn('CHAPTER API TEXT');

        $formatter
            ->shouldReceive('chapterToMarkdown')
            ->once()
            ->with($chapter)
            ->andReturn('# CHAPTER API MARKDOWN');

        $zipPath = $this->tempExportFile('CHAPTER API ZIP');

        $builder
            ->shouldReceive('buildForChapter')
            ->once()
            ->with($chapter)
            ->andReturn($zipPath);

        $controller = new ChapterExportApiController($formatter, $queries);

        $this->assertDirectDownload(
            $controller->exportPdf($chapter->id),
            $chapter->slug . '.pdf',
            '%PDF-CHAPTER-API%'
        );

        $this->assertDirectDownload(
            $controller->exportHtml($chapter->id),
            $chapter->slug . '.html',
            'CHAPTER API HTML'
        );

        $this->assertDirectDownload(
            $controller->exportPlainText($chapter->id),
            $chapter->slug . '.txt',
            'CHAPTER API TEXT'
        );

        $this->assertDirectDownload(
            $controller->exportMarkdown($chapter->id),
            $chapter->slug . '.md',
            '# CHAPTER API MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->exportZip($chapter->id, $builder),
            $chapter->slug . '.zip',
            'CHAPTER API ZIP'
        );
    }

    public function test_book_export_web_controller_cubre_pdf_html_plaintext_markdown_y_zip(): void
    {
        $book = $this->simpleBook();

        $queries = Mockery::mock(BookQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleBySlugOrFail')
            ->times(5)
            ->with($book->slug)
            ->andReturn($book);

        $formatter
            ->shouldReceive('bookToPdf')
            ->once()
            ->with($book)
            ->andReturn('%PDF-BOOK-WEB%');

        $formatter
            ->shouldReceive('bookToContainedHtml')
            ->once()
            ->with($book)
            ->andReturn('<html>BOOK WEB HTML</html>');

        $formatter
            ->shouldReceive('bookToPlainText')
            ->once()
            ->with($book)
            ->andReturn('BOOK WEB TEXT');

        $formatter
            ->shouldReceive('bookToMarkdown')
            ->once()
            ->with($book)
            ->andReturn('# BOOK WEB MARKDOWN');

        $zipPath = $this->tempExportFile('BOOK WEB ZIP');

        $builder
            ->shouldReceive('buildForBook')
            ->once()
            ->with($book)
            ->andReturn($zipPath);

        $controller = new BookExportController($queries, $formatter);

        $this->assertDirectDownload(
            $controller->pdf($book->slug),
            $book->slug . '.pdf',
            '%PDF-BOOK-WEB%'
        );

        $this->assertDirectDownload(
            $controller->html($book->slug),
            $book->slug . '.html',
            'BOOK WEB HTML'
        );

        $this->assertDirectDownload(
            $controller->plainText($book->slug),
            $book->slug . '.txt',
            'BOOK WEB TEXT'
        );

        $this->assertDirectDownload(
            $controller->markdown($book->slug),
            $book->slug . '.md',
            '# BOOK WEB MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->zip($book->slug, $builder),
            $book->slug . '.zip',
            'BOOK WEB ZIP'
        );
    }

    public function test_chapter_export_web_controller_cubre_pdf_html_plaintext_markdown_y_zip(): void
    {
        $chapter = $this->simpleChapter();
        $bookSlug = 'libro-padre-unit';

        $queries = Mockery::mock(ChapterQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleBySlugsOrFail')
            ->times(5)
            ->with($bookSlug, $chapter->slug)
            ->andReturn($chapter);

        $formatter
            ->shouldReceive('chapterToPdf')
            ->once()
            ->with($chapter)
            ->andReturn('%PDF-CHAPTER-WEB%');

        $formatter
            ->shouldReceive('chapterToContainedHtml')
            ->once()
            ->with($chapter)
            ->andReturn('<html>CHAPTER WEB HTML</html>');

        $formatter
            ->shouldReceive('chapterToPlainText')
            ->once()
            ->with($chapter)
            ->andReturn('CHAPTER WEB TEXT');

        $formatter
            ->shouldReceive('chapterToMarkdown')
            ->once()
            ->with($chapter)
            ->andReturn('# CHAPTER WEB MARKDOWN');

        $zipPath = $this->tempExportFile('CHAPTER WEB ZIP');

        $builder
            ->shouldReceive('buildForChapter')
            ->once()
            ->with($chapter)
            ->andReturn($zipPath);

        $controller = new ChapterExportController($queries, $formatter);

        $this->assertDirectDownload(
            $controller->pdf($bookSlug, $chapter->slug),
            $chapter->slug . '.pdf',
            '%PDF-CHAPTER-WEB%'
        );

        $this->assertDirectDownload(
            $controller->html($bookSlug, $chapter->slug),
            $chapter->slug . '.html',
            'CHAPTER WEB HTML'
        );

        $this->assertDirectDownload(
            $controller->plainText($bookSlug, $chapter->slug),
            $chapter->slug . '.txt',
            'CHAPTER WEB TEXT'
        );

        $this->assertDirectDownload(
            $controller->markdown($bookSlug, $chapter->slug),
            $chapter->slug . '.md',
            '# CHAPTER WEB MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->zip($bookSlug, $chapter->slug, $builder),
            $chapter->slug . '.zip',
            'CHAPTER WEB ZIP'
        );
    }

    public function test_page_export_web_controller_cubre_plaintext_markdown_y_zip(): void
    {
        $page = $this->simplePage();
        $bookSlug = 'libro-padre-unit';

        $queries = Mockery::mock(PageQueries::class);
        $formatter = Mockery::mock(ExportFormatter::class);
        $builder = Mockery::mock(ZipExportBuilder::class);

        $queries
            ->shouldReceive('findVisibleBySlugsOrFail')
            ->times(3)
            ->with($bookSlug, $page->slug)
            ->andReturn($page);

        $formatter
            ->shouldReceive('pageToPlainText')
            ->once()
            ->with($page)
            ->andReturn('PAGE WEB TEXT');

        $formatter
            ->shouldReceive('pageToMarkdown')
            ->once()
            ->with($page)
            ->andReturn('# PAGE WEB MARKDOWN');

        $zipPath = $this->tempExportFile('PAGE WEB ZIP');

        $builder
            ->shouldReceive('buildForPage')
            ->once()
            ->with($page)
            ->andReturn($zipPath);

        $controller = new PageExportController($queries, $formatter);

        $this->assertDirectDownload(
            $controller->plainText($bookSlug, $page->slug),
            $page->slug . '.txt',
            'PAGE WEB TEXT'
        );

        $this->assertDirectDownload(
            $controller->markdown($bookSlug, $page->slug),
            $page->slug . '.md',
            '# PAGE WEB MARKDOWN'
        );

        $this->assertStreamedDownload(
            $controller->zip($bookSlug, $page->slug, $builder),
            $page->slug . '.zip',
            'PAGE WEB ZIP'
        );
    }
}