<?php

namespace Tests\Unit;

use BookStack\Exceptions\PdfExportException;
use BookStack\Exports\PdfGenerator;
use Tests\TestCase;

class PdfGeneratorAdvancedUnitTest extends TestCase
{
    protected array $filesToDelete = [];
    protected bool $createdLocalWkhtmlBinary = false;

    protected function tearDown(): void
    {
        foreach ($this->filesToDelete as $file) {
            if (is_string($file) && file_exists($file)) {
                unlink($file);
            }
        }

        $localBinary = base_path('wkhtmltopdf');

        if ($this->createdLocalWkhtmlBinary && file_exists($localBinary)) {
            unlink($localBinary);
        }

        parent::tearDown();
    }

    protected function generator()
    {
        return new class extends PdfGenerator {
            public function exposedGetWkhtmlBinaryPath(): string
            {
                return $this->getWkhtmlBinaryPath();
            }

            public function exposedConvertEntities(string $html): string
            {
                return $this->convertEntities($html);
            }

            public function exposedGetUserDomPdfFontFamilies(): array
            {
                return $this->getUserDomPdfFontFamilies();
            }

            public function exposedRenderUsingDomPdf(string $html): string
            {
                return $this->renderUsingDomPdf($html);
            }
        };
    }

    protected function createTempPhpScript(string $contents): string
    {
        $basePath = tempnam(sys_get_temp_dir(), 'bs-pdf-advanced-script-');

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

    public function test_convert_entities_reemplaza_simbolos_de_moneda_para_dompdf(): void
    {
        $generator = $this->generator();

        $html = '<p>Precio en euros: 10€ y precio en libras: 5£</p>';

        $result = $generator->exposedConvertEntities($html);

        $this->assertStringContainsString('10&euro;', $result);
        $this->assertStringContainsString('5&pound;', $result);
        $this->assertStringNotContainsString('10€', $result);
        $this->assertStringNotContainsString('5£', $result);
    }

    public function test_get_wkhtml_binary_path_usa_config_si_no_existe_binario_local(): void
    {
        $localBinary = base_path('wkhtmltopdf');

        if (file_exists($localBinary)) {
            $this->markTestSkipped('Ya existe wkhtmltopdf local en la raíz del proyecto; no se puede probar la rama de config sin alterar el entorno.');
        }

        config([
            'exports.snappy.pdf_binary' => 'C:\fake-path\wkhtmltopdf.exe',
        ]);

        $generator = $this->generator();

        $this->assertSame('C:\fake-path\wkhtmltopdf.exe', $generator->exposedGetWkhtmlBinaryPath());
    }

    public function test_get_wkhtml_binary_path_prioriza_binario_local_sobre_config(): void
    {
        $localBinary = base_path('wkhtmltopdf');

        if (!file_exists($localBinary)) {
            file_put_contents($localBinary, 'fake wkhtml binary');
            $this->createdLocalWkhtmlBinary = true;
        }

        config([
            'exports.snappy.pdf_binary' => 'C:\fake-path\wkhtmltopdf.exe',
        ]);

        $generator = $this->generator();

        $this->assertSame($localBinary, $generator->exposedGetWkhtmlBinaryPath());
    }

    public function test_get_user_dompdf_font_families_retorna_array(): void
    {
        $generator = $this->generator();

        $families = $generator->exposedGetUserDomPdfFontFamilies();

        $this->assertIsArray($families);
    }

    public function test_render_using_dompdf_genera_contenido_pdf_real(): void
    {
        config([
            'exports.dompdf' => [
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ],
        ]);

        $generator = $this->generator();

        $result = $generator->exposedRenderUsingDomPdf(
            '<html><body><h1>PDF DOMPDF Unit</h1><p>Contenido con símbolo 10€ y 5£.</p></body></html>'
        );

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function test_from_html_usando_comando_lanza_excepcion_por_timeout(): void
    {
        $script = $this->createTempPhpScript(<<<'PHP'
sleep(3);
exit(0);
PHP);

        config([
            'exports.pdf_command' => $this->phpCommandForScript($script),
            'exports.pdf_command_timeout' => 1,
            'exports.snappy.pdf_binary' => '',
            'app.allow_untrusted_server_fetching' => false,
        ]);

        $this->expectException(PdfExportException::class);
        $this->expectExceptionMessage('PDF Export via command failed due to timeout at 1 second(s)');

        $generator = new PdfGenerator();

        $generator->fromHtml('<p>HTML que debe provocar timeout</p>');
    }
}