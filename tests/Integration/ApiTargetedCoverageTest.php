<?php

namespace Tests\Integration\Api;

use BookStack\Api\ApiToken;
use BookStack\Api\ApiTokenGuard;
use BookStack\Api\ListingResponseBuilder;
use BookStack\Api\UserApiTokenController;
use BookStack\Entities\Models\Book;
use BookStack\Exceptions\ApiAuthException;
use BookStack\Users\Models\User;
use BookStack\Access\LoginService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ApiTargetedCoverageTest extends TestCase
{
    use DatabaseTransactions;

    protected function createTokenFor(User $user, string $secret = 'target-secret', array $attributes = []): ApiToken
    {
        return ApiToken::factory()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Target API Token ' . uniqid(),
            'token_id' => 'target-token-' . uniqid(),
            'secret' => Hash::make($secret),
            'expires_at' => Carbon::now()->addDay()->format('Y-m-d'),
        ], $attributes));
    }

    protected function createBookFor(User $user, array $attributes = []): Book
    {
        $book = Book::factory()->create(array_merge([
            'name' => 'Target API Book ' . uniqid(),
            'description' => 'Book for targeted API coverage.',
            'description_html' => '<p>Book for targeted API coverage.</p>',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'owned_by' => $user->id,
        ], $attributes));

        $book->rebuildPermissions();

        return $book;
    }

    protected function loginService(bool $awaitingEmailConfirmation = false): LoginService
    {
        $loginService = Mockery::mock(LoginService::class);

        $loginService->shouldReceive('awaitingEmailConfirmation')
            ->zeroOrMoreTimes()
            ->andReturn($awaitingEmailConfirmation);

        return $loginService;
    }

    protected function requestWithToken(ApiToken $token, string $secret): Request
    {
        $request = Request::create('/api/books', 'GET');
        $request->headers->set('Authorization', "Token {$token->token_id}:{$secret}");

        return $request;
    }

    protected function controller(): UserApiTokenController
    {
        return app(UserApiTokenController::class);
    }

    public function test_api_token_default_expiry_retorna_fecha_a_100_anios(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00'));

        try {
            $this->assertSame('2126-07-07', ApiToken::defaultExpiry());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_api_token_log_descriptor_y_get_url(): void
    {
        $admin = $this->users->admin();

        $token = $this->createTokenFor($admin, 'descriptor-secret', [
            'name' => 'Token Descriptor Coverage',
        ]);

        $descriptor = $token->logDescriptor();

        $this->assertStringContainsString((string) $token->id, $descriptor);
        $this->assertStringContainsString('Token Descriptor Coverage', $descriptor);
        $this->assertStringContainsString($admin->logDescriptor(), $descriptor);

        $this->assertStringEndsWith("/api-tokens/{$admin->id}/{$token->id}", $token->getUrl());
        $this->assertStringEndsWith("/api-tokens/{$admin->id}/{$token->id}/edit", $token->getUrl('edit'));
        $this->assertStringEndsWith("/api-tokens/{$admin->id}/{$token->id}/delete", $token->getUrl('/delete/'));
    }

    public function test_api_token_guard_validate_cubre_credenciales_validas_e_invalidas(): void
    {
        $admin = $this->users->admin();
        $secret = 'validate-target-secret';

        $token = $this->createTokenFor($admin, $secret);

        $guard = new ApiTokenGuard(
            Request::create('/api/books', 'GET'),
            $this->loginService()
        );

        $this->assertFalse($guard->validate([]));
        $this->assertFalse($guard->validate(['id' => $token->token_id]));
        $this->assertFalse($guard->validate(['secret' => $secret]));
        $this->assertFalse($guard->validate(['id' => 'token-inexistente', 'secret' => $secret]));
        $this->assertFalse($guard->validate(['id' => $token->token_id, 'secret' => 'secret-incorrecto']));
        $this->assertTrue($guard->validate(['id' => $token->token_id, 'secret' => $secret]));
    }

    public function test_api_token_guard_logout_limpia_usuario_y_permite_reautenticar(): void
    {
        $admin = $this->users->admin();
        $secret = 'logout-target-secret';

        $token = $this->createTokenFor($admin, $secret);

        $guard = new ApiTokenGuard(
            $this->requestWithToken($token, $secret),
            $this->loginService()
        );

        $this->assertSame($admin->id, $guard->user()->id);

        $guard->logout();

        $this->assertSame($admin->id, $guard->user()->id);
    }

    public function test_api_token_guard_authenticate_lanza_excepcion_si_no_hay_usuario(): void
    {
        $request = Request::create('/api/books', 'GET');

        $guard = new ApiTokenGuard($request, $this->loginService());

        $this->assertNull($guard->user());

        $this->expectException(ApiAuthException::class);

        $guard->authenticate();
    }

    public function test_listing_response_builder_modify_results_y_set_filterable_fields(): void
    {
        $admin = $this->users->admin();

        $bookA = $this->createBookFor($admin, [
            'name' => 'Coverage Listing A',
            'description' => 'grupo permitido',
        ]);

        $bookB = $this->createBookFor($admin, [
            'name' => 'Coverage Listing B',
            'description' => 'grupo bloqueado',
        ]);

        $request = Request::create('/api/books', 'GET', [
            'filter' => [
                'description' => 'grupo permitido',
                'name:like' => 'Coverage%',
            ],
            'sort' => '-name',
            'count' => 10,
        ]);

        $builder = new ListingResponseBuilder(
            Book::query()->whereIn('id', [$bookA->id, $bookB->id]),
            $request,
            ['id', 'name', 'slug', 'description']
        );

        $builder->setFilterableFields(['description']);

        $builder->modifyResults(function (Book $book): void {
            $book->description = 'modificado-' . $book->id;
        });

        $response = $builder->toResponse();
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $payload['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame($bookA->id, $payload['data'][0]['id']);
        $this->assertSame('modificado-' . $bookA->id, $payload['data'][0]['description']);
    }

    public function test_listing_response_builder_filtra_con_operadores_y_paginate(): void
    {
        $admin = $this->users->admin();

        $bookA = $this->createBookFor($admin, [
            'name' => 'Coverage Operator A',
        ]);

        $bookB = $this->createBookFor($admin, [
            'name' => 'Coverage Operator B',
        ]);

        $bookC = $this->createBookFor($admin, [
            'name' => 'Coverage Operator C',
        ]);

        $request = Request::create('/api/books', 'GET', [
            'filter' => [
                'id:gt' => $bookA->id,
            ],
            'sort' => '+id',
            'count' => 1,
            'offset' => 0,
        ]);

        $builder = new ListingResponseBuilder(
            Book::query()->whereIn('id', [$bookA->id, $bookB->id, $bookC->id]),
            $request,
            ['id', 'name', 'slug']
        );

        $payload = $builder->toResponse()->getData(true);

        $this->assertSame(2, $payload['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame($bookB->id, $payload['data'][0]['id']);
    }

    public function test_user_api_token_controller_create_muestra_vista_y_guarda_contexto(): void
    {
        $admin = $this->users->admin();

        $this->actingAs($admin);

        $request = Request::create("/api-tokens/{$admin->id}/create", 'GET', [
            'context' => 'settings',
        ]);

        $response = $this->controller()->create($request, $admin->id);
        $data = $response->getData();

        $this->assertSame('users.api-tokens.create', $response->name());
        $this->assertSame($admin->id, $data['user']->id);
        $this->assertStringContainsString('#api_tokens', $data['back']);
        $this->assertSame('settings', session()->get('api-token-context'));
    }

    public function test_user_api_token_controller_store_crea_token_y_guarda_secret_en_sesion(): void
    {
        $admin = $this->users->admin();

        $this->actingAs($admin);
        session()->forget('api-token-context');

        $request = Request::create("/api-tokens/{$admin->id}/create", 'POST', [
            'name' => 'Token Store Coverage',
            'expires_at' => '2030-01-01',
        ]);

        $response = $this->controller()->store($request, $admin->id);

        $token = ApiToken::query()
            ->where('user_id', $admin->id)
            ->where('name', 'Token Store Coverage')
            ->firstOrFail();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith("/api-tokens/{$admin->id}/{$token->id}", $response->headers->get('Location'));
        $this->assertSame('2030-01-01', $token->expires_at->format('Y-m-d'));
        $this->assertNotEmpty(session()->get('api-token-secret:' . $token->id));
    }

    public function test_user_api_token_controller_edit_muestra_token_y_extrae_secret_de_sesion(): void
    {
        $admin = $this->users->admin();

        $token = $this->createTokenFor($admin, 'edit-target-secret', [
            'name' => 'Token Edit Coverage',
        ]);

        $this->actingAs($admin);
        session()->put('api-token-secret:' . $token->id, 'edit-target-secret');

        $request = Request::create($token->getUrl(), 'GET');

        $response = $this->controller()->edit($request, $admin->id, $token->id);
        $data = $response->getData();

        $this->assertSame('users.api-tokens.edit', $response->name());
        $this->assertSame($admin->id, $data['user']->id);
        $this->assertSame($token->id, $data['token']->id);
        $this->assertSame($token->id, $data['model']->id);
        $this->assertSame('edit-target-secret', $data['secret']);
        $this->assertNull(session()->get('api-token-secret:' . $token->id));
    }

    public function test_user_api_token_controller_update_actualiza_token(): void
    {
        $admin = $this->users->admin();

        $token = $this->createTokenFor($admin, 'update-target-secret', [
            'name' => 'Token antes de actualizar',
        ]);

        $this->actingAs($admin);

        $request = Request::create($token->getUrl(), 'PUT', [
            'name' => 'Token actualizado coverage',
            'expires_at' => '2031-02-03',
        ]);

        $response = $this->controller()->update($request, $admin->id, $token->id);

        $token->refresh();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('Token actualizado coverage', $token->name);
        $this->assertSame('2031-02-03', $token->expires_at->format('Y-m-d'));
        $this->assertStringEndsWith("/api-tokens/{$admin->id}/{$token->id}", $response->headers->get('Location'));
    }

    public function test_user_api_token_controller_delete_muestra_vista_de_confirmacion(): void
    {
        $admin = $this->users->admin();

        $token = $this->createTokenFor($admin, 'delete-target-secret', [
            'name' => 'Token Delete Coverage',
        ]);

        $this->actingAs($admin);

        $response = $this->controller()->delete($admin->id, $token->id);
        $data = $response->getData();

        $this->assertSame('users.api-tokens.delete', $response->name());
        $this->assertSame($admin->id, $data['user']->id);
        $this->assertSame($token->id, $data['token']->id);
    }

    public function test_user_api_token_controller_destroy_elimina_token_y_redirige(): void
    {
        $admin = $this->users->admin();

        $token = $this->createTokenFor($admin, 'destroy-target-secret', [
            'name' => 'Token Destroy Coverage',
        ]);

        $this->actingAs($admin);
        session()->forget('api-token-context');

        $response = $this->controller()->destroy($admin->id, $token->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull(ApiToken::query()->find($token->id));
        $this->assertStringEndsWith('/my-account/auth#api_tokens', $response->headers->get('Location'));
    }
}