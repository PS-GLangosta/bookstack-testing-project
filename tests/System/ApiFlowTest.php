<?php

namespace Tests\System;

use BookStack\Api\ApiToken;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ST-06 - Flujo API completo.
 *
 * Los cinco casos siguen una secuencia lógica end-to-end sobre la API REST de
 * BookStack: autenticar con un token de API, crear un libro, crear una página
 * dentro de ese libro, leer el libro y finalmente eliminarlo, verificando en
 * cada paso el estado real de la base de datos.
 *
 * El token de API se genera en setUp() con el factory de ApiToken y todas las
 * peticiones viajan con el header Authorization: Token {id}:{secret}.
 */
class ApiFlowTest extends TestCase
{
    protected User $apiUser;
    protected string $apiSecret = 'st-06-api-secret';
    protected array $apiHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiUser = $this->users->admin();

        $token = ApiToken::factory()->create([
            'user_id' => $this->apiUser->id,
            'secret'  => Hash::make($this->apiSecret),
        ]);

        $this->apiHeaders = [
            'Authorization' => "Token {$token->token_id}:{$this->apiSecret}",
        ];
    }

    /**
     * Crea un libro a través de la API y devuelve la respuesta JSON.
     */
    protected function createBookViaApi(string $name, string $description): array
    {
        $response = $this->postJson('/api/books', [
            'name'        => $name,
            'description' => $description,
        ], $this->apiHeaders);

        $response->assertStatus(200);

        return $response->json();
    }

    /**
     * Crea una página dentro de un libro a través de la API y devuelve la respuesta JSON.
     */
    protected function createPageViaApi(int $bookId, string $name, string $html): array
    {
        $response = $this->postJson('/api/pages', [
            'book_id' => $bookId,
            'name'    => $name,
            'html'    => $html,
        ], $this->apiHeaders);

        $response->assertStatus(200);

        return $response->json();
    }

    /**
     * ST-06-01
     * Autenticar con token de API válido y recuperar los datos del usuario autenticado.
     *
     * Esta versión de BookStack no expone un endpoint /api/users/me, por lo que el
     * usuario autenticado se recupera a través de GET /api/users/{id} usando su propio id.
     */
    public function test_st_06_01_autenticar_con_token_valido_retorna_datos_del_usuario(): void
    {
        $response = $this->getJson('/api/users/' . $this->apiUser->id, $this->apiHeaders);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id'    => $this->apiUser->id,
            'name'  => $this->apiUser->name,
            'email' => $this->apiUser->email,
        ]);
        $response->assertJsonStructure([
            'id',
            'name',
            'slug',
            'email',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * ST-06-02
     * Crear un libro por API (POST /api/books) y verificar que existe en la base de datos.
     */
    public function test_st_06_02_crear_libro_por_api_existe_en_la_base_de_datos(): void
    {
        $name        = 'ST-06 Libro creado por API';
        $description = 'Libro creado dentro del flujo de sistema ST-06.';

        $book = $this->createBookViaApi($name, $description);

        $bookId = $book['id'];

        $this->assertDatabaseHasEntityData('book', [
            'id'          => $bookId,
            'name'        => $name,
            'description' => $description,
        ]);

        $persisted = Book::query()->find($bookId);

        $this->assertNotNull($persisted);
        $this->assertSame($name, $persisted->name);
        $this->assertSame($description, $persisted->description);
    }

    /**
     * ST-06-03
     * Crear una página dentro del libro creado (POST /api/pages) y verificar la relación en la base de datos.
     */
    public function test_st_06_03_crear_pagina_dentro_del_libro_verifica_relacion_en_bd(): void
    {
        $book = $this->createBookViaApi(
            'ST-06 Libro contenedor de página',
            'Libro que contendrá la página del flujo ST-06.'
        );
        $bookId = $book['id'];

        $page = $this->createPageViaApi(
            $bookId,
            'ST-06 Página creada por API',
            '<p>Contenido de la página del flujo ST-06.</p>'
        );
        $pageId = $page['id'];

        $this->assertSame($bookId, $page['book_id']);

        $persistedPage = Page::query()->find($pageId);

        $this->assertNotNull($persistedPage);
        $this->assertSame($bookId, $persistedPage->book_id);
        $this->assertSame($bookId, $persistedPage->book->id);

        $this->assertDatabaseHas($this->getTable('entities'), [
            'id'   => $pageId,
            'type' => 'page',
            'name' => 'ST-06 Página creada por API',
        ]);
    }

    /**
     * ST-06-04
     * Leer el libro por API (GET /api/books/{id}) y comprobar que los datos coinciden con lo creado.
     */
    public function test_st_06_04_leer_libro_por_api_los_datos_coinciden(): void
    {
        $name        = 'ST-06 Libro para lectura';
        $description = 'Libro que será leído por la API en el flujo ST-06.';

        $created = $this->createBookViaApi($name, $description);
        $bookId  = $created['id'];

        $response = $this->getJson('/api/books/' . $bookId, $this->apiHeaders);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id'          => $bookId,
            'name'        => $name,
            'slug'        => $created['slug'],
            'description' => $description,
        ]);

        $this->assertSame($created['id'], $response->json('id'));
        $this->assertSame($created['name'], $response->json('name'));
        $this->assertSame($created['slug'], $response->json('slug'));
        $this->assertSame($created['description'], $response->json('description'));
    }

    /**
     * ST-06-05
     * Eliminar el libro (DELETE /api/books/{id}) y verificar que el libro y su página ya no existen en la base de datos.
     */
    public function test_st_06_05_eliminar_libro_elimina_libro_y_pagina_de_bd(): void
    {
        $book = $this->createBookViaApi(
            'ST-06 Libro a eliminar',
            'Libro que será eliminado al final del flujo ST-06.'
        );
        $bookId = $book['id'];

        $page = $this->createPageViaApi(
            $bookId,
            'ST-06 Página a eliminar en cascada',
            '<p>Página que debe eliminarse junto con el libro.</p>'
        );
        $pageId = $page['id'];

        $this->assertNotNull(Book::query()->find($bookId));
        $this->assertNotNull(Page::query()->find($pageId));

        $response = $this->deleteJson('/api/books/' . $bookId, [], $this->apiHeaders);

        $response->assertStatus(204);

        $this->assertNull(Book::query()->find($bookId));
        $this->assertNull(Page::query()->find($pageId));

        $this->assertSoftDeleted($this->getTable('entities'), [
            'id'   => $bookId,
            'type' => 'book',
        ]);
        $this->assertSoftDeleted($this->getTable('entities'), [
            'id'   => $pageId,
            'type' => 'page',
        ]);
    }
}
