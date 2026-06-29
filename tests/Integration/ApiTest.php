<?php

namespace Tests\Integration;

use BookStack\Activity\ActivityType;
use BookStack\Api\ApiToken;
use BookStack\Entities\Models\Book;
use BookStack\Users\Models\User;
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
}