<?php

namespace Tests\Unit\Api;

use BookStack\Api\ApiDocsController;
use BookStack\Api\ApiDocsGenerator;
use BookStack\Api\ApiEntityListFormatter;
use BookStack\App\AppVersion;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Password;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ApiDocsAndFormatterTest extends TestCase
{
    use DatabaseTransactions;

    protected function callProtected(object $object, string $methodName, array $arguments = []): mixed
    {
        $method = new ReflectionMethod($object, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    protected function putDocsInProductionCache(Collection $docs): void
    {
        config(['app.env' => 'production']);

        $cacheKey = 'api-docs::' . AppVersion::get();

        Cache::forget($cacheKey);
        Cache::put($cacheKey, $docs, 60);
    }

    protected function userForEntities(): User
    {
        return User::factory()->create([
            'name' => 'API Formatter User ' . uniqid(),
            'email' => 'api-formatter-' . uniqid() . '@example.com',
        ]);
    }

    protected function bookFor(User $user): Book
    {
        return Book::factory()->create([
            'name' => 'API Formatter Book ' . uniqid(),
            'description' => 'Book used for API formatter tests.',
            'description_html' => '<p>Book used for API formatter tests.</p>',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ]);
    }

    public function test_api_docs_controller_display_retorna_vista_con_docs(): void
    {
        $docs = collect([
            'books' => [
                [
                    'name' => 'books-list',
                    'uri' => 'api/books',
                ],
            ],
        ]);

        $this->putDocsInProductionCache($docs);

        $response = app(ApiDocsController::class)->display();

        $this->assertSame('api-docs.index', $response->name());
        $this->assertArrayHasKey('docs', $response->getData());
        $this->assertEquals($docs, $response->getData()['docs']);
    }

    public function test_api_docs_controller_json_retorna_docs_en_formato_json(): void
    {
        $docs = collect([
            'books' => [
                [
                    'name' => 'books-list',
                    'uri' => 'api/books',
                ],
            ],
        ]);

        $this->putDocsInProductionCache($docs);

        $response = app(ApiDocsController::class)->json();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($docs->toArray(), $response->getData(true));
    }

    public function test_api_docs_controller_redirect_envia_a_api_docs(): void
    {
        $response = app(ApiDocsController::class)->redirect();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/api/docs', $response->headers->get('Location'));
    }

    public function test_api_docs_generator_usa_cache_en_produccion_si_existe(): void
    {
        $docs = collect([
            'cached' => [
                [
                    'name' => 'cached-docs',
                ],
            ],
        ]);

        $this->putDocsInProductionCache($docs);

        $result = ApiDocsGenerator::generateConsideringCache();

        $this->assertEquals($docs, $result);
    }

    public function test_api_docs_generator_generacion_real_retorna_coleccion_agrupada(): void
    {
        config(['app.env' => 'testing']);

        $docs = ApiDocsGenerator::generateConsideringCache();

        $this->assertInstanceOf(Collection::class, $docs);
        $this->assertTrue($docs->isNotEmpty());
        $this->assertTrue($docs->has('books') || $docs->has('docs'));
    }

    public function test_api_docs_generator_parsea_descripcion_desde_docblock(): void
    {
        $generator = new ApiDocsGenerator();

        $comment = <<<'DOC'
/**
 * Primera línea de descripción.
 * Segunda línea de descripción.
 *
 * @param string $value
 * @return void
 */
DOC;

        $description = $this->callProtected($generator, 'parseDescriptionFromDocBlockComment', [$comment]);

        $this->assertStringContainsString('Primera línea de descripción.', $description);
        $this->assertStringContainsString('Segunda línea de descripción.', $description);
        $this->assertStringNotContainsString('@param', $description);
        $this->assertStringNotContainsString('@return', $description);
    }

    public function test_api_docs_generator_convierte_reglas_de_validacion_a_texto(): void
    {
        $generator = new ApiDocsGenerator();

        $this->assertSame(
            'required|string',
            $this->callProtected($generator, 'getValidationAsString', ['required|string'])
        );

        $this->assertSame(
            'stringable-rule',
            $this->callProtected($generator, 'getValidationAsString', [new StringableApiValidationRule()])
        );

        $this->assertSame(
            'min:8',
            $this->callProtected($generator, 'getValidationAsString', [Password::min(8)])
        );
    }

    public function test_api_docs_generator_lanza_error_con_regla_no_convertible(): void
    {
        $generator = new ApiDocsGenerator();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot provide string representation of rule for class');

        $this->callProtected($generator, 'getValidationAsString', [new class () {
        }]);
    }

    public function test_api_docs_generator_obtiene_rutas_api_en_formato_plano(): void
    {
        $generator = new ApiDocsGenerator();

        $routes = $this->callProtected($generator, 'getFlatApiRoutes');

        $this->assertInstanceOf(Collection::class, $routes);
        $this->assertTrue($routes->isNotEmpty());

        $route = $routes->first(fn(array $route) => $route['base_model'] === 'books');

        $this->assertNotNull($route);
        $this->assertArrayHasKey('name', $route);
        $this->assertArrayHasKey('uri', $route);
        $this->assertArrayHasKey('method', $route);
        $this->assertArrayHasKey('controller', $route);
        $this->assertArrayHasKey('controller_method', $route);
        $this->assertArrayHasKey('controller_method_kebab', $route);
        $this->assertArrayHasKey('base_model', $route);
    }

    public function test_api_docs_generator_carga_ejemplos_desde_archivos_si_existen(): void
    {
        $generator = new ApiDocsGenerator();

        $name = 'unit-api-example-' . uniqid();

        $requestDir = base_path('dev/api/requests');
        $responseDir = base_path('dev/api/responses');

        if (!is_dir($requestDir)) {
            mkdir($requestDir, 0777, true);
        }

        if (!is_dir($responseDir)) {
            mkdir($responseDir, 0777, true);
        }

        $requestFile = $requestDir . DIRECTORY_SEPARATOR . $name . '.json';
        $responseFile = $responseDir . DIRECTORY_SEPARATOR . $name . '.http';

        file_put_contents($requestFile, '{"request": true}');
        file_put_contents($responseFile, "HTTP/1.1 200 OK\n\n{\"response\": true}");

        try {
            $routes = collect([
                [
                    'name' => $name,
                ],
            ]);

            $result = $this->callProtected($generator, 'loadDetailsFromFiles', [$routes])->first();

            $this->assertSame('{"request": true}', $result['example_request']);
            $this->assertSame("HTTP/1.1 200 OK\n\n{\"response\": true}", $result['example_response']);
        } finally {
            if (file_exists($requestFile)) {
                unlink($requestFile);
            }

            if (file_exists($responseFile)) {
                unlink($responseFile);
            }
        }
    }

    public function test_api_docs_generator_coloca_null_si_no_hay_archivos_de_ejemplo(): void
    {
        $generator = new ApiDocsGenerator();

        $routes = collect([
            [
                'name' => 'archivo-inexistente-' . uniqid(),
            ],
        ]);

        $result = $this->callProtected($generator, 'loadDetailsFromFiles', [$routes])->first();

        $this->assertNull($result['example_request']);
        $this->assertNull($result['example_response']);
    }

    public function test_api_entity_list_formatter_formatea_entidad_con_campos_basicos_tipo_tags_y_campo_custom(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $book->setRelation('tags', collect([
            [
                'name' => 'Categoria',
                'value' => 'API',
            ],
        ]));

        $result = (new ApiEntityListFormatter([$book]))
            ->withType()
            ->withTags()
            ->withField('custom_value', fn(Book $book) => 'book-' . $book->id)
            ->withField('omitido', fn() => null)
            ->format();

        $this->assertCount(1, $result);

        $formatted = $result[0];

        $this->assertSame($book->id, $formatted['id']);
        $this->assertSame($book->name, $formatted['name']);
        $this->assertSame($book->slug, $formatted['slug']);
        $this->assertSame('book', $formatted['type']);
        $this->assertSame('book-' . $book->id, $formatted['custom_value']);
        $this->assertArrayHasKey('url', $formatted);
        $this->assertArrayHasKey('tags', $formatted);
        $this->assertArrayNotHasKey('omitido', $formatted);
    }

    public function test_api_entity_list_formatter_incluye_padres_si_las_relaciones_estan_cargadas(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $chapter = Chapter::factory()->create([
            'name' => 'API Formatter Chapter ' . uniqid(),
            'book_id' => $book->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ]);

        $page = Page::factory()->create([
            'name' => 'API Formatter Page ' . uniqid(),
            'book_id' => $book->id,
            'chapter_id' => $chapter->id,
            'html' => '<p>Contenido de prueba</p>',
            'text' => 'Contenido de prueba',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ]);

        $page->setRelation('book', $book);
        $page->setRelation('chapter', $chapter);

        $result = (new ApiEntityListFormatter([$page]))
            ->withParents()
            ->withType()
            ->format();

        $formatted = $result[0];

        $this->assertSame('page', $formatted['type']);
        $this->assertSame($book->only(['id', 'name', 'slug']), $formatted['book']);
        $this->assertSame($chapter->only(['id', 'name', 'slug']), $formatted['chapter']);
    }

    public function test_api_entity_list_formatter_omite_padres_si_las_relaciones_no_estan_cargadas(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $page = Page::factory()->create([
            'name' => 'API Formatter Page Sin Relaciones ' . uniqid(),
            'book_id' => $book->id,
            'chapter_id' => null,
            'html' => '<p>Contenido sin relaciones cargadas</p>',
            'text' => 'Contenido sin relaciones cargadas',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ]);

        $page = Page::query()->without(['book', 'chapter'])->findOrFail($page->id);
        $page->setRelations([]);

        $formatter = (new ApiEntityListFormatter([$page]))
            ->withParents();

        // Quitamos el campo url porque getUrl() puede cargar el book internamente.
        // Así validamos específicamente que withParents() no incluya padres si no están cargados.
        $property = new ReflectionProperty($formatter, 'fields');
        $property->setAccessible(true);

        $fields = $property->getValue($formatter);
        unset($fields['url']);

        $property->setValue($formatter, $fields);

        $result = $formatter->format();

        $formatted = $result[0];

        $this->assertArrayNotHasKey('book', $formatted);
        $this->assertArrayNotHasKey('chapter', $formatted);
    }
    public function test_api_entity_list_formatter_retorna_lista_vacia_si_no_hay_entidades(): void
    {
        $result = (new ApiEntityListFormatter([]))->format();

        $this->assertSame([], $result);
    }

    public function test_api_entity_list_formatter_omite_campos_que_no_existen_en_el_modelo(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $formatter = new ApiEntityListFormatter([$book]);

        // El formatter soporta campos string internamente, pero withField() solo acepta callbacks.
        // Por eso agregamos el campo inexistente directamente a la propiedad protegida fields.
        $property = new ReflectionProperty($formatter, 'fields');
        $property->setAccessible(true);

        $fields = $property->getValue($formatter);
        $fields[] = 'campo_inexistente';

        $property->setValue($formatter, $fields);

        $result = $formatter->format();

        $formatted = $result[0];

        $this->assertArrayNotHasKey('campo_inexistente', $formatted);
        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('name', $formatted);
        $this->assertArrayHasKey('url', $formatted);
    }

    public function test_api_entity_list_formatter_incluye_book_pero_omite_chapter_si_pagina_no_tiene_capitulo(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $page = Page::factory()->create([
            'name' => 'API Formatter Page Solo Libro ' . uniqid(),
            'book_id' => $book->id,
            'chapter_id' => null,
            'html' => '<p>Contenido con libro pero sin capítulo</p>',
            'text' => 'Contenido con libro pero sin capítulo',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ]);

        $page->setRelation('book', $book);
        $page->unsetRelation('chapter');

        $result = (new ApiEntityListFormatter([$page]))
            ->withParents()
            ->withType()
            ->format();

        $formatted = $result[0];

        $this->assertSame('page', $formatted['type']);
        $this->assertSame($book->only(['id', 'name', 'slug']), $formatted['book']);
        $this->assertArrayNotHasKey('chapter', $formatted);
    }

    public function test_api_entity_list_formatter_formatea_multiples_entidades(): void
    {
        $user = $this->userForEntities();

        $firstBook = $this->bookFor($user);
        $secondBook = $this->bookFor($user);

        $result = (new ApiEntityListFormatter([$firstBook, $secondBook]))
            ->withType()
            ->format();

        $this->assertCount(2, $result);

        $this->assertSame($firstBook->id, $result[0]['id']);
        $this->assertSame($secondBook->id, $result[1]['id']);

        $this->assertSame('book', $result[0]['type']);
        $this->assertSame('book', $result[1]['type']);
    }

    public function test_api_docs_generator_reflection_class_y_method_se_resuelven_correctamente(): void
    {
        $generator = new ApiDocsGenerator();

        $class = $this->callProtected($generator, 'getReflectionClass', [ApiDocsController::class]);
        $method = $this->callProtected($generator, 'getReflectionMethod', [ApiDocsController::class, 'json']);

        $this->assertSame(ApiDocsController::class, $class->getName());
        $this->assertSame('json', $method->getName());
    }

    public function test_api_docs_generator_en_testing_ignora_cache_de_produccion(): void
    {
        $cachedDocs = collect([
            'cached' => [
                [
                    'name' => 'cached-docs-should-not-be-used',
                ],
            ],
        ]);

        $cacheKey = 'api-docs::' . AppVersion::get();

        Cache::forget($cacheKey);
        Cache::put($cacheKey, $cachedDocs, 60);

        config(['app.env' => 'testing']);

        $docs = ApiDocsGenerator::generateConsideringCache();

        $this->assertInstanceOf(Collection::class, $docs);
        $this->assertNotEquals($cachedDocs, $docs);
        $this->assertFalse($docs->has('cached'));
    }
    public function test_api_entity_list_formatter_callback_puede_usar_nombre_de_propiedad_personalizado(): void
    {
        $user = $this->userForEntities();
        $book = $this->bookFor($user);

        $result = (new ApiEntityListFormatter([$book]))
            ->withField('display_name', fn(Book $book) => strtoupper($book->name))
            ->format();

        $formatted = $result[0];

        $this->assertSame(strtoupper($book->name), $formatted['display_name']);
    }

    public function test_api_docs_generator_cachea_resultado_generado_en_testing(): void
    {
        config(['app.env' => 'testing']);

        $cacheKey = 'api-docs::' . AppVersion::get();

        Cache::forget($cacheKey);

        $docs = ApiDocsGenerator::generateConsideringCache();

        $this->assertInstanceOf(Collection::class, $docs);
        $this->assertTrue(Cache::has($cacheKey));
    }
}

class StringableApiValidationRule
{
    public function __toString(): string
    {
        return 'stringable-rule';
    }
}