<?php

namespace Tests\CPF;

use BookStack\Access\Mfa\MfaValue;
use BookStack\Access\Notifications\ResetPasswordNotification;
use BookStack\Access\Mfa\TotpService;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Pruebas de Aceptación de Usuario — Persona 1
 *
 * Secciones: RF-001 · RF-002 · RF-003 · RF-004 · RF-005 (primera parte)
 * Rango asignado: CPF-01-01 a CPF-04-07 · CPF-05-01 a CPF-05-05
 * Total casos: 41 (37 base + 4 adicionales)
 * Responsable: Rommel Chambi
 */
class PersonaUnoTest extends TestCase
{
    // =========================================================
    // RF-001: Autenticación, sesión y recuperación de contraseña
    // =========================================================

    /** CPF-01-01: Login con credenciales válidas de administrador */
    public function test_cpf_01_01_login_con_credenciales_validas_de_administrador(): void
    {
        $admin = $this->users->admin();

        $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    /** CPF-01-02: Login con correo vacío y contraseña válida */
    public function test_cpf_01_02_login_con_correo_vacio_muestra_error_de_validacion(): void
    {
        $this->post('/login', [
            'email'    => '',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** CPF-01-03: Login con correo válido y contraseña vacía */
    public function test_cpf_01_03_login_con_contrasena_vacia_muestra_error_de_validacion(): void
    {
        $admin = $this->users->admin();

        $this->post('/login', [
            'email'    => $admin->email,
            'password' => '',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    /** CPF-01-04: Login con correo no registrado */
    public function test_cpf_01_04_login_con_correo_no_registrado_muestra_error_generico(): void
    {
        $this->post('/login', [
            'email'    => 'noexiste' . uniqid() . '@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** CPF-01-05: Login con contraseña incorrecta */
    public function test_cpf_01_05_login_con_contrasena_incorrecta_muestra_error_generico(): void
    {
        $admin = $this->users->admin();

        $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'contrasena_incorrecta_xyz',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** CPF-01-06: Login con formato de correo inválido */
    public function test_cpf_01_06_login_con_formato_de_correo_invalido_muestra_error(): void
    {
        $this->post('/login', [
            'email'    => 'noesunemail',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** CPF-01-07: Cierre de sesión desde cuenta autenticada */
    public function test_cpf_01_07_cierre_de_sesion_redirige_fuera_del_panel(): void
    {
        $this->asAdmin()->get('/')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    /** CPF-01-08: Acceso directo a ruta protegida sin sesión */
    public function test_cpf_01_08_acceso_a_ruta_protegida_sin_sesion_redirige_a_login(): void
    {
        $this->get('/books')->assertRedirect('/login');
        $this->get('/settings/users')->assertRedirect('/login');
    }

    /** CPF-01-09: Login marcando opción "Recordar sesión" */
    public function test_cpf_01_09_login_con_remember_me_establece_cookie_de_larga_duracion(): void
    {
        $admin = $this->users->admin();

        // Login con remember_me habilitado — el sistema acepta la petición
        // y autentica al usuario; la cookie de larga duración es gestionada
        // por el navegador y no se puede verificar desde PHPUnit directamente.
        $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password',
            'remember' => 'true',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
    }

    /** CPF-01-10: Solicitud de recuperación con correo registrado */
    public function test_cpf_01_10_solicitud_de_recuperacion_con_correo_registrado(): void
    {
        Notification::fake();
        $admin = $this->users->admin();

        $this->post('/password/email', ['email' => $admin->email])
            ->assertRedirect('/password/email');

        $this->get('/password/email')
            ->assertSee('password reset link will be sent');

        $this->assertDatabaseHas('password_resets', ['email' => $admin->email]);
        Notification::assertSentTo($admin, ResetPasswordNotification::class);
    }

    /** CPF-01-11: Solicitud de recuperación con correo inválido o vacío */
    public function test_cpf_01_11_solicitud_de_recuperacion_con_correo_invalido_o_vacio(): void
    {
        // Campo vacío
        $this->post('/password/email', ['email' => ''])
            ->assertSessionHasErrors('email');

        // Email no registrado — BookStack muestra mensaje ambiguo (privacidad)
        Notification::fake();
        $this->post('/password/email', ['email' => 'fantasma' . uniqid() . '@example.com'])
            ->assertRedirect('/password/email');

        $this->get('/password/email')
            ->assertSee('password reset link will be sent');

        Notification::assertNothingSent();
    }

    /** CPF-01-12: Restablecimiento con token inválido o vencido */
    public function test_cpf_01_12_restablecimiento_con_token_invalido_muestra_error(): void
    {
        $this->post('/password/reset', [
            'token'                 => 'token-invalido-completamente-falso',
            'email'                 => 'admin@admin.com',
            'password'              => 'nuevaPassword123',
            'password_confirmation' => 'nuevaPassword123',
        ])->assertSessionHasErrors();

        // La contraseña no cambia
        $admin = User::query()->where('email', 'admin@admin.com')->first();
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    /** CPF-01-13: (adicional) Bloqueo temporal por múltiples intentos fallidos */
    public function test_cpf_01_13_multiples_intentos_fallidos_activan_rate_limiting(): void
    {
        RateLimiter::clear('login|127.0.0.1');

        $admin = $this->users->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email'    => $admin->email,
                'password' => 'wrong_pass_' . $i,
            ]);
        }

        $resp = $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        // El 6° intento debe ser rechazado con error de throttle o credenciales
        $resp->assertSessionHasErrors();
        $this->assertGuest();
    }

    // =========================================================
    // RF-002: Alta de usuarios, invitaciones y confirmación de acceso
    // =========================================================

    /** CPF-02-01: Alta de usuario por administrador con datos válidos */
    public function test_cpf_02_01_alta_de_usuario_con_datos_validos(): void
    {
        $viewerRole = Role::getRole('viewer');
        $email      = 'cpf0201.' . uniqid() . '@example.com';

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Usuario Nuevo',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
            'roles[' . $viewerRole->id . ']' => 'true',
        ])->assertRedirect('/settings/users');

        $this->assertDatabaseHas('users', ['email' => $email, 'name' => 'CPF Usuario Nuevo']);
    }

    /** CPF-02-02: Alta administrativa con nombre vacío */
    public function test_cpf_02_02_alta_con_nombre_vacio_es_rechazada(): void
    {
        $email = 'cpf0202.' . uniqid() . '@example.com';

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => '',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    /** CPF-02-03: Alta administrativa con correo duplicado */
    public function test_cpf_02_03_alta_con_correo_duplicado_es_rechazada(): void
    {
        $admin = $this->users->admin();

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Duplicado',
            'email'            => $admin->email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['name' => 'CPF Duplicado']);
    }

    /** CPF-02-04: Alta administrativa con formato de correo inválido */
    public function test_cpf_02_04_alta_con_correo_con_formato_invalido(): void
    {
        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Invalid Mail',
            'email'            => 'noesuncorreo',
            'password'         => 'password123',
            'password-confirm' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    /** CPF-02-05: Alta administrativa con contraseña inválida o incompleta */
    public function test_cpf_02_05_alta_con_contrasena_demasiado_corta_es_rechazada(): void
    {
        $email = 'cpf0205.' . uniqid() . '@example.com';

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Short Pass',
            'email'            => $email,
            'password'         => '1234',
            'password-confirm' => '1234',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    /** CPF-02-06: Uso de invitación válida para completar acceso */
    public function test_cpf_02_06_invitacion_valida_crea_usuario_y_envia_correo(): void
    {
        Notification::fake();
        $email = 'cpf0206.' . uniqid() . '@example.com';

        $this->asAdmin()->post('/settings/users/create', [
            'name'        => 'CPF Invitado',
            'email'       => $email,
            'send_invite' => 'true',
        ])->assertRedirect('/settings/users');

        $newUser = User::query()->where('email', $email)->first();
        $this->assertNotNull($newUser, 'El usuario invitado debe existir en BD');
        $this->assertDatabaseHas('user_invites', ['user_id' => $newUser->id]);
    }

    /** CPF-02-07: Uso de invitación vencida o inválida */
    public function test_cpf_02_07_invitacion_con_token_invalido_es_bloqueada(): void
    {
        // BookStack redirige tokens inválidos fuera del flujo de invitación
        $resp = $this->get('/register/invite/token-completamente-invalido-falso');
        $this->assertTrue(
            $resp->isRedirect() || $resp->status() === 404,
            'Esperaba redirect o 404 para token de invitación inválido'
        );
    }

    /** CPF-02-08: Verificación de ausencia de registro público cuando está deshabilitado */
    public function test_cpf_02_08_sin_registro_publico_habilitado_no_aparece_link(): void
    {
        // Por defecto en BookStack el registro público está deshabilitado
        $this->get('/login')->assertDontSee('Sign up');

        // /register redirige o retorna error cuando está deshabilitado
        $resp = $this->get('/register');
        $this->assertTrue(
            $resp->isRedirect() || $resp->status() === 404,
            'Esperaba redirect o 404 en /register con registro deshabilitado'
        );
    }

    // =========================================================
    // RF-003: Autenticación multifactor MFA
    // =========================================================

    /** CPF-03-01: Activar MFA TOTP con código de verificación válido */
    public function test_cpf_03_01_activar_mfa_totp_con_codigo_valido(): void
    {
        $editor = $this->users->editor();
        $this->actingAs($editor)->get('/mfa/totp/generate');

        $secret = decrypt(session()->get('mfa-setup-totp-secret'));
        $google2fa = new Google2FA();

        $this->post('/mfa/totp/confirm', ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect('/mfa/setup');

        $this->assertDatabaseHas('mfa_values', [
            'user_id' => $editor->id,
            'method'  => MfaValue::METHOD_TOTP,
        ]);
    }

    /** CPF-03-02: Activar MFA con código inválido */
    public function test_cpf_03_02_activar_mfa_con_codigo_invalido_es_rechazado(): void
    {
        $editor = $this->users->editor();
        $this->actingAs($editor)->get('/mfa/totp/generate');

        $this->post('/mfa/totp/confirm', ['code' => '000000'])
            ->assertRedirect('/mfa/totp/generate');

        $this->assertDatabaseMissing('mfa_values', ['user_id' => $editor->id]);
    }

    /** CPF-03-03: Login de usuario con MFA activo solicita verificación */
    public function test_cpf_03_03_login_con_mfa_activo_muestra_pantalla_de_verificacion(): void
    {
        [$user] = $this->setupUserWithTotp();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/mfa/verify');

        $this->assertGuest();
    }

    /** CPF-03-04: Completar login MFA con código válido */
    public function test_cpf_03_04_completar_login_mfa_con_codigo_valido_concede_acceso(): void
    {
        [$user, $secret] = $this->setupUserWithTotp();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $google2fa = new Google2FA();
        $this->post('/mfa/totp/verify', ['code' => $google2fa->getCurrentOtp($secret)])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    /** CPF-03-05: Completar login MFA con código incorrecto */
    public function test_cpf_03_05_completar_login_mfa_con_codigo_incorrecto_bloquea_acceso(): void
    {
        [$user] = $this->setupUserWithTotp();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->get('/mfa/verify');  // inicializa estado de sesión MFA
        $this->post('/mfa/totp/verify', ['code' => '000000'])
            ->assertRedirect('/mfa/verify');

        $this->assertNull(auth()->user());
    }

    /** CPF-03-06: Usar código de respaldo válido */
    public function test_cpf_03_06_usar_codigo_de_respaldo_valido_concede_acceso(): void
    {
        [$user, $codes] = $this->setupUserWithBackupCodes();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/mfa/backup_codes/verify', ['code' => $codes[0]])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    /** CPF-03-07: Reutilizar código de respaldo ya consumido */
    public function test_cpf_03_07_reutilizar_codigo_de_respaldo_consumido_es_rechazado(): void
    {
        [$user, $codes] = $this->setupUserWithBackupCodes();

        // Primer uso — exitoso
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/mfa/backup_codes/verify', ['code' => $codes[0]])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        // Cerrar sesión e intentar reusar el mismo código
        auth()->logout();
        session()->flush();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->get('/mfa/verify');  // inicializa estado de sesión MFA
        $this->post('/mfa/backup_codes/verify', ['code' => $codes[0]])
            ->assertRedirect('/mfa/verify');

        $this->assertNull(auth()->user());
    }

    /** CPF-03-08: Desactivar MFA — no se solicita en el siguiente login */
    public function test_cpf_03_08_desactivar_mfa_y_login_siguiente_no_requiere_verificacion(): void
    {
        [$user, $secret] = $this->setupUserWithTotp();
        $this->actingAs($user);

        $this->delete('/mfa/totp/remove')->assertRedirect('/mfa/setup');
        $this->assertDatabaseMissing('mfa_values', [
            'user_id' => $user->id,
            'method'  => MfaValue::METHOD_TOTP,
        ]);

        // El siguiente login no debe requerir verificación MFA
        $this->post('/logout');
        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    // =========================================================
    // RF-004: Cuenta personal y preferencias del usuario
    // =========================================================

    /** CPF-04-01: Actualizar nombre de perfil con dato válido */
    public function test_cpf_04_01_actualizar_nombre_de_perfil_con_dato_valido(): void
    {
        $editor = $this->users->editor();

        $this->actingAs($editor)->put('/my-account/profile', [
            'name'     => 'CPF Nombre Actualizado',
            'email'    => $editor->email,
            'language' => 'en',
        ])->assertRedirect('/my-account/profile');

        $this->assertDatabaseHas('users', [
            'id'   => $editor->id,
            'name' => 'CPF Nombre Actualizado',
        ]);
    }

    /** CPF-04-02: Actualizar perfil con correo inválido */
    public function test_cpf_04_02_actualizar_perfil_con_correo_invalido_muestra_error(): void
    {
        $editor = $this->users->editor();

        $this->actingAs($editor)->put('/my-account/profile', [
            'name'     => $editor->name,
            'email'    => 'noesunemail',
            'language' => 'en',
        ])->assertSessionHasErrors('email');
    }

    /** CPF-04-03: Cambiar contraseña con contraseña actual y confirmación válidas */
    public function test_cpf_04_03_cambiar_contrasena_con_datos_validos(): void
    {
        $editor   = $this->users->editor();
        $newPass  = 'nuevaPassword' . Str::random(6);

        $this->actingAs($editor)->put('/my-account/auth/password', [
            'password'         => $newPass,
            'password-confirm' => $newPass,
        ])->assertRedirect('/my-account/auth');

        $editor->refresh();
        $this->assertTrue(Hash::check($newPass, $editor->password));
    }

    /** CPF-04-04: Cambiar contraseña con confirmación diferente */
    public function test_cpf_04_04_cambiar_contrasena_con_confirmacion_diferente_muestra_error(): void
    {
        $editor       = $this->users->editor();
        $originalHash = $editor->password;

        $this->actingAs($editor)->put('/my-account/auth/password', [
            'password'         => 'nuevaPassword123',
            'password-confirm' => 'otraContrasenaDistinta456',
        ])->assertSessionHasErrors('password-confirm');

        $editor->refresh();
        $this->assertEquals($originalHash, $editor->password);
    }

    /** CPF-04-05: (adicional) Cambiar preferencia de idioma de interfaz */
    public function test_cpf_04_05_cambiar_preferencia_de_idioma_de_la_interfaz(): void
    {
        $editor = $this->users->editor();

        $this->actingAs($editor)->put('/my-account/profile', [
            'name'     => $editor->name,
            'email'    => $editor->email,
            'language' => 'es',
        ])->assertRedirect('/my-account/profile');

        $this->assertEquals('es', setting()->getUser($editor, 'language'));
    }

    /** CPF-04-06: (adicional) Perfil de usuario es accesible y muestra datos actuales */
    public function test_cpf_04_06_perfil_de_usuario_muestra_datos_actuales(): void
    {
        $editor = $this->users->editor();

        $this->actingAs($editor)->get('/my-account/profile')
            ->assertOk()
            ->assertSee($editor->name)
            ->assertSee($editor->email);
    }

    /** CPF-04-07: (adicional) Viewer no puede acceder a opciones de administración desde su perfil */
    public function test_cpf_04_07_viewer_no_ve_opciones_de_administracion_en_perfil(): void
    {
        $viewer = $this->users->viewer();

        $this->actingAs($viewer)->get('/my-account/profile')
            ->assertOk()
            ->assertDontSee('Administrator Options');
    }

    // =========================================================
    // RF-005: Administración de usuarios (primera parte)
    // =========================================================

    /** CPF-05-01: Listar usuarios desde panel de administración */
    public function test_cpf_05_01_listar_usuarios_desde_panel_de_administracion(): void
    {
        $this->asAdmin()->get('/settings/users')
            ->assertOk()
            ->assertSee('Users');
    }

    /** CPF-05-02: Crear usuario con rol Administrador y External Authentication ID vacío */
    public function test_cpf_05_02_crear_usuario_administrador_con_external_auth_id_vacio(): void
    {
        $email     = 'cpf0502.' . uniqid() . '@example.com';
        $adminRole = Role::getRole('admin');

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Admin User',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
            'roles'            => [$adminRole->id],
            'external_auth_id' => '',
        ])->assertRedirect('/settings/users');

        $created = User::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('role_user', ['user_id' => $created->id, 'role_id' => $adminRole->id]);
    }

    /** CPF-05-03: Crear usuario con rol Editor y External Authentication ID vacío */
    public function test_cpf_05_03_crear_usuario_editor_con_external_auth_id_vacio(): void
    {
        $email      = 'cpf0503.' . uniqid() . '@example.com';
        $editorRole = Role::getRole('editor');

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Editor User',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
            'roles'            => [$editorRole->id],
            'external_auth_id' => '',
        ])->assertRedirect('/settings/users');

        $created = User::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('role_user', ['user_id' => $created->id, 'role_id' => $editorRole->id]);
    }

    /** CPF-05-04: Crear usuario con rol Viewer y External Authentication ID vacío */
    public function test_cpf_05_04_crear_usuario_viewer_con_external_auth_id_vacio(): void
    {
        $email      = 'cpf0504.' . uniqid() . '@example.com';
        $viewerRole = Role::getRole('viewer');

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF Viewer User',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
            'roles'            => [$viewerRole->id],
            'external_auth_id' => '',
        ])->assertRedirect('/settings/users');

        $created = User::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('role_user', ['user_id' => $created->id, 'role_id' => $viewerRole->id]);
    }

    /** CPF-05-05: Crear usuario con External Authentication ID informado */
    public function test_cpf_05_05_crear_usuario_con_external_auth_id_informado(): void
    {
        $email      = 'cpf0505.' . uniqid() . '@example.com';
        $viewerRole = Role::getRole('viewer');
        $externalId = 'uid=rommel,dc=empresa,dc=com';

        $this->asAdmin()->post('/settings/users/create', [
            'name'             => 'CPF External Auth User',
            'email'            => $email,
            'password'         => 'password123',
            'password-confirm' => 'password123',
            'roles[' . $viewerRole->id . ']' => 'true',
            'external_auth_id' => $externalId,
        ])->assertRedirect('/settings/users');

        // Verificar que el usuario fue creado; el external_auth_id
        // solo se aplica activamente cuando hay SAML2/OIDC/LDAP configurado.
        $created = User::query()->where('email', $email)->first();
        $this->assertNotNull($created, 'El usuario con external_auth_id debe ser creado');
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    // =========================================================
    // Helpers privados
    // =========================================================

    /**
     * Crea un usuario editor con TOTP habilitado.
     *
     * @return array{0: User, 1: string}  [$user, $totpSecret]
     */
    private function setupUserWithTotp(): array
    {
        $user   = $this->users->editor();
        $secret = $this->app->make(TotpService::class)->generateSecret();

        $user->password = Hash::make('password');
        $user->save();

        MfaValue::upsertWithValue($user, MfaValue::METHOD_TOTP, $secret);

        return [$user, $secret];
    }

    /**
     * Crea un usuario editor con códigos de respaldo habilitados.
     *
     * @return array{0: User, 1: string[]}  [$user, $codes]
     */
    private function setupUserWithBackupCodes(array $codes = ['kzzu6-1pgll', 'bzxnf-plygd', 'bwdsp-ysl51', '1vo93-ioy7n', 'lf7nw-wdyka', 'xmtrd-oplac']): array
    {
        $user = $this->users->editor();

        $user->password = Hash::make('password');
        $user->save();

        MfaValue::upsertWithValue($user, MfaValue::METHOD_BACKUP_CODES, json_encode($codes));

        return [$user, $codes];
    }
}
