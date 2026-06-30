<?php

namespace Tests\Integration;

use BookStack\Activity\ActivityType;
use BookStack\Api\ApiToken;
use BookStack\Entities\Models\Book;
use BookStack\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiTest extends TestCase
{
    protected string $baseEndpoint = '/api/books';

    protected function apiTokenHeaderFor(User $user, string $secret = 'integration-api-secret'): array
    {
        $token = ApiToken::factory()->create([
            'user_id' => $user->id,
            'secret' => Hash::make($secret),
        ]);

        return [
            'Authorization' => "Token {$token->token_id}:{$secret}",
        ];
    }

    /**
     * IT-API-01
     * GET /api/books retorna lista JSON con estructura correcta.
     */
    public function test_it_api_01_get_books_retorna_lista_json_con_estructura_correcta(): void
    {
        $admin = $this->users->admin();
        $book = $this->entities->book();
        $headers = $this->apiTokenHeaderFor($admin);

        $response = $this->getJson($this->baseEndpoint . '?filter[id]=' . $book->id, $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'created_at',
                    'updated_at',
                    'created_by',
                    'updated_by',
                    'owned_by',
                ],
            ],
            'total',
        ]);

        $response->assertJsonPath('data.0.id', $book->id);
        $response->assertJsonPath('data.0.name', $book->name);
        $response->assertJsonPath('data.0.slug', $book->slug);
    }

    /**
     * IT-API-02
     * POST /api/books con token válido crea un libro correctamente.
     */
    public function test_it_api_02_post_books_con_token_valido_crea_libro_correctamente(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);

        $payload = [
            'name' => 'Libro creado por API Integration Test',
            'description' => 'Libro creado desde prueba de integración REST.',
        ];

        $response = $this->postJson($this->baseEndpoint, $payload, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'description_html' => '<p>Libro creado desde prueba de integración REST.</p>',
        ]);
        $response->assertJsonStructure([
            'id',
            'name',
            'slug',
            'description',
            'description_html',
            'created_at',
            'updated_at',
        ]);

        $bookId = $response->json('id');

        $this->assertDatabaseHasEntityData('book', [
            'id' => $bookId,
            'name' => $payload['name'],
            'description' => $payload['description'],
            'description_html' => '<p>Libro creado desde prueba de integración REST.</p>',
        ]);

        $this->assertActivityExists(ActivityType::BOOK_CREATE, Book::query()->findOrFail($bookId));
    }

    /**
     * IT-API-03
     * POST /api/books sin token retorna 401 Unauthorized.
     */
    public function test_it_api_03_post_books_sin_token_retorna_401_unauthorized(): void
    {
        $payload = [
            'name' => 'Libro sin token',
            'description' => 'No debería crearse sin autenticación API.',
        ];

        $response = $this->postJson($this->baseEndpoint, $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'message' => 'No authorization token found on the request',
                'code' => 401,
            ],
        ]);

        $this->assertDatabaseMissing('entities', [
            'type' => 'book',
            'name' => $payload['name'],
        ]);
    }

    /**
     * IT-API-04
     * GET /api/books/{id} de libro inexistente retorna 404.
     */
    public function test_it_api_04_get_book_inexistente_retorna_404(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $missingBookId = 99999999;

        $response = $this->getJson($this->baseEndpoint . '/' . $missingBookId, $headers);

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'code',
            ],
        ]);
        $response->assertJsonPath('error.code', 404);
    }

    /**
     * IT-API-05
     * PUT /api/books/{id} actualiza nombre y retorna entidad actualizada.
     */
    public function test_it_api_05_put_book_actualiza_nombre_y_retorna_entidad_actualizada(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $book = $this->entities->book();

        $payload = [
            'name' => 'Libro actualizado por API Integration Test',
            'description' => 'Descripción actualizada desde API REST.',
        ];

        $response = $this->putJson($this->baseEndpoint . '/' . $book->id, $payload, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $book->id,
            'name' => $payload['name'],
            'description' => $payload['description'],
            'description_html' => '<p>Descripción actualizada desde API REST.</p>',
        ]);

        $book->refresh();

        $this->assertSame($payload['name'], $book->name);
        $this->assertSame($payload['description'], $book->description);

        $this->assertDatabaseHasEntityData('book', [
            'id' => $book->id,
            'name' => $payload['name'],
            'description' => $payload['description'],
            'description_html' => '<p>Descripción actualizada desde API REST.</p>',
        ]);

        $this->assertActivityExists(ActivityType::BOOK_UPDATE, $book);
    }

    /**
     * IT-API-06
     * DELETE /api/books/{id} retorna 204 y el libro deja de estar visible.
     */
    public function test_it_api_06_delete_book_retorna_204_y_libro_deja_de_estar_visible(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $book = $this->entities->book();

        $response = $this->deleteJson($this->baseEndpoint . '/' . $book->id, [], $headers);

        $response->assertStatus(204);

        $this->assertNull(Book::query()->find($book->id));

        $this->assertSoftDeleted('entities', [
            'id' => $book->id,
            'type' => 'book',
        ]);

        $this->assertDatabaseHas('deletions', [
            'deletable_type' => 'book',
            'deletable_id' => $book->id,
        ]);

        $this->assertActivityExists(ActivityType::BOOK_DELETE, $book);
    }
    /**
     * IT-API-07
     * GET /api/books con token con formato inválido retorna 401.
     */
    public function test_it_api_07_get_books_con_token_con_formato_invalido_retorna_401(): void
    {
        $response = $this->getJson($this->baseEndpoint, [
            'Authorization' => 'Token token-sin-secret',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'message' => 'An authorization token was found on the request but the format appeared incorrect',
                'code' => 401,
            ],
        ]);
    }

    /**
     * IT-API-08
     * GET /api/books con secret incorrecto retorna 401.
     */
    public function test_it_api_08_get_books_con_secret_incorrecto_retorna_401(): void
    {
        $admin = $this->users->admin();

        $token = ApiToken::factory()->create([
            'user_id' => $admin->id,
            'token_id' => 'decisiontoken',
            'secret' => Hash::make('secret-correcto'),
        ]);

        $response = $this->getJson($this->baseEndpoint, [
            'Authorization' => "Token {$token->token_id}:secret-incorrecto",
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'message' => 'The secret provided for the given used API token is incorrect',
                'code' => 401,
            ],
        ]);
    }

        /**
     * IT-API-09
     * POST /api/books sin campo name retorna error de validación 422.
     */
    public function test_it_api_09_post_books_sin_name_retorna_error_de_validacion_422(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);

        $booksBefore = Book::query()->count();

        $payload = [
            'description' => 'Libro enviado sin nombre para validar rama de error.',
        ];

        $response = $this->postJson($this->baseEndpoint, $payload, $headers);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'message' => 'The given data was invalid.',
                'validation' => [
                    'name' => ['The name field is required.'],
                ],
                'code' => 422,
            ],
        ]);

        $this->assertSame($booksBefore, Book::query()->count());
    }

    /**
     * IT-API-10
     * GET /api/books/{id} de libro existente retorna datos correctos.
     */
    public function test_it_api_10_get_book_existente_retorna_datos_correctos(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $book = $this->entities->book();

        $response = $this->getJson($this->baseEndpoint . '/' . $book->id, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $book->id,
            'name' => $book->name,
            'slug' => $book->slug,
        ]);
        $response->assertJsonStructure([
            'id',
            'name',
            'slug',
            'description',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'owned_by',
        ]);
    }
    /**
     * IT-API-11
     * PUT /api/books/{id} con name mayor a 255 caracteres retorna error de validación 422.
     */
    public function test_it_api_11_put_book_con_name_mayor_a_255_retorna_error_de_validacion_422(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $book = $this->entities->book();

        $originalName = $book->name;
        $originalDescription = $book->description;

        $payload = [
            'name' => str_repeat('A', 256),
            'description' => 'Intento de actualización con nombre inválido.',
        ];

        $response = $this->putJson($this->baseEndpoint . '/' . $book->id, $payload, $headers);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'validation' => [
                    'name',
                ],
                'code',
            ],
        ]);
        $response->assertJsonPath('error.code', 422);

        $book->refresh();

        $this->assertSame($originalName, $book->name);
        $this->assertSame($originalDescription, $book->description);
    }

    /**
     * IT-API-12
     * DELETE /api/books/{id} de libro inexistente retorna 404.
     */
    public function test_it_api_12_delete_book_inexistente_retorna_404(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);
        $missingBookId = 99999999;

        $response = $this->deleteJson($this->baseEndpoint . '/' . $missingBookId, [], $headers);

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'code',
            ],
        ]);
        $response->assertJsonPath('error.code', 404);
    }

    /**
     * IT-API-13
     * POST /api/books con usuario sin permisos retorna 403 Forbidden.
     */
    public function test_it_api_13_post_books_con_usuario_sin_permisos_retorna_403(): void
    {
        $viewer = $this->users->viewer();
        $headers = $this->apiTokenHeaderFor($viewer);

        $booksBefore = Book::query()->count();

        $payload = [
            'name' => 'Libro creado por usuario sin permisos',
            'description' => 'Este libro no debería crearse.',
        ];

        $response = $this->postJson($this->baseEndpoint, $payload, $headers);

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'error' => [
                'message',
                'code',
            ],
        ]);
        $response->assertJsonPath('error.code', 403);

        $this->assertSame($booksBefore, Book::query()->count());

        $this->assertDatabaseMissing('entities', [
            'type' => 'book',
            'name' => $payload['name'],
        ]);
    }
    /**
     * IT-API-14
     * GET /api/books con token_id inexistente retorna 401.
     */
    public function test_it_api_14_get_books_con_token_id_inexistente_retorna_401(): void
    {
        $response = $this->getJson($this->baseEndpoint, [
            'Authorization' => 'Token token-inexistente:secret-inexistente',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'message' => 'No matching API token was found for the provided authorization token',
                'code' => 401,
            ],
        ]);
    }

    /**
     * IT-API-15
     * GET /api/books con token expirado retorna 403.
     */
    public function test_it_api_15_get_books_con_token_expirado_retorna_403(): void
    {
        $admin = $this->users->admin();
        $secret = 'secret-expirado';

        $token = ApiToken::factory()->create([
            'user_id' => $admin->id,
            'token_id' => 'expiredtoken',
            'secret' => Hash::make($secret),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->getJson($this->baseEndpoint, [
            'Authorization' => "Token {$token->token_id}:{$secret}",
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'message' => 'The authorization token used has expired',
                'code' => 403,
            ],
        ]);
    }

    /**
     * IT-API-16
     * POST /api/books con description_html crea libro y genera texto plano.
     */
    public function test_it_api_16_post_books_con_description_html_crea_libro_y_genera_texto_plano(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);

        $payload = [
            'name' => 'Libro API con HTML',
            'description_html' => '<p>Libro <strong>creado</strong> con descripción HTML</p>',
        ];

        $response = $this->postJson($this->baseEndpoint, $payload, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'description_html' => $payload['description_html'],
            'description' => 'Libro creado con descripción HTML',
        ]);

        $bookId = $response->json('id');

        $this->assertDatabaseHasEntityData('book', [
            'id' => $bookId,
            'name' => $payload['name'],
            'description_html' => $payload['description_html'],
            'description' => 'Libro creado con descripción HTML',
        ]);

        $this->assertActivityExists(ActivityType::BOOK_CREATE, Book::query()->findOrFail($bookId));
    }

    /**
     * IT-API-17
     * POST /api/books con tags crea libro y persiste etiquetas.
     */
    public function test_it_api_17_post_books_con_tags_crea_libro_y_persiste_etiquetas(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);

        $payload = [
            'name' => 'Libro API con tags',
            'description' => 'Libro creado para validar etiquetas desde API.',
            'tags' => [
                [
                    'name' => 'Categoria',
                    'value' => 'Integracion',
                ],
            ],
        ];

        $response = $this->postJson($this->baseEndpoint, $payload, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => $payload['name'],
            'description' => $payload['description'],
            'description_html' => '<p>Libro creado para validar etiquetas desde API.</p>',
        ]);

        $book = Book::query()
            ->where('name', $payload['name'])
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertDatabaseHas('tags', [
            'entity_id' => $book->id,
            'entity_type' => $book->getMorphClass(),
            'name' => 'Categoria',
            'value' => 'Integracion',
        ]);

        $this->assertActivityExists(ActivityType::BOOK_CREATE, $book);
    }

    /**
     * IT-API-18
     * GET /api/books/{id} de libro con capítulos y páginas retorna contents.
     */
    public function test_it_api_18_get_book_con_capitulos_y_paginas_retorna_contents(): void
    {
        $admin = $this->users->admin();
        $headers = $this->apiTokenHeaderFor($admin);

        $book = $this->entities->bookHasChaptersAndPages();

        $response = $this->getJson($this->baseEndpoint . '/' . $book->id, $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $book->id,
            'name' => $book->name,
            'slug' => $book->slug,
        ]);

        $response->assertJsonStructure([
            'id',
            'name',
            'slug',
            'contents' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                ],
            ],
        ]);

        $this->assertNotEmpty($response->json('contents'));
    }
}