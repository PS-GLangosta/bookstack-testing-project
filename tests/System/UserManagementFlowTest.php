<?php

namespace Tests\System;

use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Pruebas de Sistema (E2E) - Flujo de Gestión de Usuarios (ST-04)
 * 
 * Issue: #42
 * Sprint: 3
 * Responsable: Ower Lopez
 * 
 * Casos contemplados (Flujo encadenado continuo):
 * - ST-04-01: Admin crea usuario nuevo con rol asignado.
 * - ST-04-02: Login exitoso con usuario recién creado.
 * - ST-04-03: Admin cambia el rol del usuario.
 * - ST-04-04: Admin desactiva (elimina) usuario, login es rechazado.
 * - ST-04-05: Prevención de creación de email duplicado.
 */
class UserManagementFlowTest extends TestCase
{
    /** @test */
    public function test_user_management_lifecycle_flow(): void
    {
        // ---------------------------------------------------------
        // ST-04-01: Admin crea usuario nuevo con rol desde el panel
        // ---------------------------------------------------------
        $this->asAdmin();
        $viewerRole = Role::where('display_name', 'Viewer')->first();

        $userData = [
            'name' => 'System Flow Test User',
            'email' => 'systemflow@example.com',
            'password' => 'password123',
            'password-confirm' => 'password123',
            'language' => 'en',
            'roles' => [$viewerRole->id],
        ];

        // Ejecutar creación
        $response = $this->post('/settings/users/create', $userData);
        $response->assertRedirect('/settings/users');
        
        // Verificar persistencia en BD
        $this->assertDatabaseHas('users', ['email' => 'systemflow@example.com']);
        $createdUser = User::where('email', 'systemflow@example.com')->first();
        $this->assertTrue($createdUser->hasRole($viewerRole->id));

        // ---------------------------------------------------------
        // ST-04-02: El usuario recién creado puede iniciar sesión
        // ---------------------------------------------------------
        // Deslogueamos al Admin
        Auth::logout();
        session()->flush();

        // Intento de login del usuario nuevo
        $loginResponse = $this->post('/login', [
            'email' => 'systemflow@example.com',
            'password' => 'password123',
        ]);
        
        // En BookStack, un login exitoso redirige al inicio '/'
        $loginResponse->assertRedirect('/');
        $this->assertAuthenticatedAs($createdUser);

        // ---------------------------------------------------------
        // ST-04-03: Admin cambia el rol de Viewer a Editor
        // ---------------------------------------------------------
        Auth::logout();
        session()->flush();
        $this->asAdmin();
        
        $editorRole = Role::where('display_name', 'Editor')->first();
        
        $updateData = [
            'name' => 'System Flow Test User Updated',
            'email' => 'systemflow@example.com',
            'roles' => [$editorRole->id],
        ];

        // Ejecutar actualización
        $updateResponse = $this->put("/settings/users/{$createdUser->id}", $updateData);
        $updateResponse->assertRedirect('/settings/users');
        
        // Refrescar entidad y validar cambio de rol efectivo
        $createdUser->refresh();
        $this->assertTrue($createdUser->hasRole($editorRole->id));
        $this->assertFalse($createdUser->hasRole($viewerRole->id));

        // ---------------------------------------------------------
        // ST-04-04: Admin desactiva (elimina) un usuario -> error de acceso
        // (En BookStack la "desactivación" se logra eliminando el usuario o sus roles)
        // ---------------------------------------------------------
        $deleteResponse = $this->delete("/settings/users/{$createdUser->id}");
        $deleteResponse->assertRedirect('/settings/users');
        $this->assertDatabaseMissing('users', ['email' => 'systemflow@example.com']);

        // Intentamos ingresar como el usuario desactivado/eliminado
        Auth::logout();
        session()->flush();
        
        $invalidLoginResponse = $this->post('/login', [
            'email' => 'systemflow@example.com',
            'password' => 'password123',
        ]);
        
        // Validamos que sea rechazado y permanezca como Guest
        $invalidLoginResponse->assertSessionHasErrors('email');
        $this->assertGuest();

        // ---------------------------------------------------------
        // ST-04-05: Crear usuario con email duplicado es rechazado
        // ---------------------------------------------------------
        $this->asAdmin();
        $adminUser = $this->users->admin();
        
        $duplicateData = [
            'name' => 'Duplicate Hacker User',
            'email' => $adminUser->email, // Usamos un email que ya existe
            'password' => 'password123',
            'password-confirm' => 'password123',
            'roles' => [$viewerRole->id],
        ];
        
        $duplicateResponse = $this->post('/settings/users/create', $duplicateData);
        
        // Verificamos que falle por validación de email
        $duplicateResponse->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['name' => 'Duplicate Hacker User']);
    }
}
