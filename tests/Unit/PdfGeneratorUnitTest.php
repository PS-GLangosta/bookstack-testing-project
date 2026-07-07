<?php

namespace Tests\Unit;

use BookStack\Exceptions\PdfExportException;
use BookStack\Exports\PdfGenerator;
use Tests\TestCase;

class PdfGeneratorUnitTest extends TestCase
{
    protected array $filesToDelete = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToDelete as $file) {
            if (is_string($file) && file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    protected function createTempPhpScript(string $contents): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'bs-pdf-test-script-');

        if ($basePath !== false && file_exists($basePath)) {
            unlink($basePath);
        }

        $file = $basePath . '.php';

        file_put_contents($file, "<?php\n" . $contents);

        $this->filesToDelete[] = $file;

        return $file;
    }

    protected function phpCommandForScript(string $scriptPath): string
    {
        return '"' . PHP_BINARY . '" ' . escapeshellarg($scriptPath) . ' {input_html_path} {output_pdf_path}';
    }

    public function test_get_active_engine_prioriza_comando_personalizado(): void
    {
        config([
            'exports.pdf_command' => 'fake-command',
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $generator = new PdfGenerator();

        $this->assertSame(PdfGenerator::ENGINE_COMMAND, $generator->getActiveEngine());
    }

    public function test_get_active_engine_usa_wkhtml_si_hay_binario_y_fetching_permitido(): void
    {
        config([
            'exports.pdf_command' => '',
            'exports.snappy.pdf_binary' => __FILE__,
            'app.allow_untrusted_server_fetching' => true,
        ]);

        $generator = new PdfGenerator();

        $this->assertSame(PdfGenerator::ENGINE_WKHTML, $generator->getActiveEngine());
    }

    public function test_get_active_engine_usa_dompdf_por_defecto(): void
    {
        config([
            'exports.pdf_command' => '',
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $generator = new PdfGenerator();

        $this->assertSame(PdfGenerator::ENGINE_DOMPDF, $generator->getActiveEngine());
    }

    public function test_from_html_usando_comando_devuelve_contenido_pdf(): void
    {
        $script = $this->createTempPhpScript(<<<'PHP'
$input = $argv[1];
$output = $argv[2];

$html = file_get_contents($input);

file_put_contents($output, '%PDF-COMMAND-OK%' . $html);

exit(0);
PHP);

        config([
            'exports.pdf_command' => $this->phpCommandForScript($script),
            'exports.pdf_command_timeout' => 10,
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $generator = new PdfGenerator();

        $result = $generator->fromHtml('<p>Contenido PDF por comando</p>');

        $this->assertStringStartsWith('%PDF-COMMAND-OK%', $result);
        $this->assertStringContainsString('<p>Contenido PDF por comando</p>', $result);
    }

    public function test_from_html_usando_comando_falla_si_el_comando_retorna_error(): void
    {
        $script = $this->createTempPhpScript(<<<'PHP'
fwrite(STDERR, 'fallo controlado pdf');
exit(7);
PHP);

        config([
            'exports.pdf_command' => $this->phpCommandForScript($script),
            'exports.pdf_command_timeout' => 10,
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $this->expectException(PdfExportException::class);
        $this->expectExceptionMessage('PDF Export via command failed with exit code 7');

        $generator = new PdfGenerator();

        $generator->fromHtml('<p>HTML que no debe exportarse</p>');
    }

    public function test_from_html_usando_comando_falla_si_el_archivo_pdf_sale_vacio(): void
    {
        $script = $this->createTempPhpScript(<<<'PHP'
$output = $argv[2];

file_put_contents($output, '');

exit(0);
PHP);

        config([
            'exports.pdf_command' => $this->phpCommandForScript($script),
            'exports.pdf_command_timeout' => 10,
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $this->expectException(PdfExportException::class);
        $this->expectExceptionMessage('PDF Export via command failed, PDF output file is empty');

        $generator = new PdfGenerator();

        $generator->fromHtml('<p>HTML con salida vacía</p>');
    }
}