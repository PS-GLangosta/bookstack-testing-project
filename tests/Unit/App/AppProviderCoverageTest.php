<?php

namespace Tests\Unit\App;

use BookStack\App\Providers\EventServiceProvider;
use BookStack\Translation\FileLoader;
use BookStack\Translation\MessageSelector;
use BookStack\Util\DateFormatter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\Translator;
use ReflectionClass;
use Tests\TestCase;

class AppProviderCoverageTest extends TestCase
{
    public function test_event_service_provider_no_descubre_eventos_automaticamente(): void
    {
        $provider = $this->app->getProvider(EventServiceProvider::class);

        $this->assertInstanceOf(EventServiceProvider::class, $provider);
        $this->assertFalse($provider->shouldDiscoverEvents());
    }

    public function test_event_service_provider_sobrescribe_configuracion_de_email_verification(): void
    {
        $provider = $this->app->getProvider(EventServiceProvider::class);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('configureEmailVerification');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($provider));
    }

    public function test_translation_service_provider_usa_loader_y_selector_personalizados(): void
    {
        $loader = $this->app->make('translation.loader');
        $translator = $this->app->make('translator');

        $this->assertInstanceOf(FileLoader::class, $loader);
        $this->assertInstanceOf(Translator::class, $translator);
        $this->assertInstanceOf(MessageSelector::class, $translator->getSelector());
    }

    public function test_view_tweaks_service_provider_registra_date_formatter_y_directiva_icon(): void
    {
        $dateFormatter = $this->app->make(DateFormatter::class);

        $this->assertInstanceOf(DateFormatter::class, $dateFormatter);

        $compiled = Blade::compileString('@icon("home")');

        $this->assertStringContainsString('BookStack\\Util\\SvgIcon', $compiled);
        $this->assertStringContainsString('toHtml', $compiled);
    }

    public function test_validation_rule_safe_url_bloquea_javascript_y_data_urls(): void
    {
        $javascriptValidator = Validator::make([
            'link' => 'javascript:alert(1)',
        ], [
            'link' => 'safe_url',
        ]);

        $dataValidator = Validator::make([
            'link' => 'data:text/html;base64,PHNjcmlwdA==',
        ], [
            'link' => 'safe_url',
        ]);

        $safeValidator = Validator::make([
            'link' => 'https://example.com/page',
        ], [
            'link' => 'safe_url',
        ]);

        $this->assertTrue($javascriptValidator->fails());
        $this->assertTrue($dataValidator->fails());
        $this->assertTrue($safeValidator->passes());
    }

    public function test_validation_rule_image_extension_acepta_imagen_y_rechaza_extension_no_soportada(): void
    {
        $validImage = UploadedFile::fake()->image('cover.jpg');

        $validValidator = Validator::make([
            'image' => $validImage,
        ], [
            'image' => 'image_extension',
        ]);

        $invalidFile = UploadedFile::fake()->create('document.txt', 5, 'text/plain');

        $invalidValidator = Validator::make([
            'image' => $invalidFile,
        ], [
            'image' => 'image_extension',
        ]);

        $this->assertTrue($validValidator->passes());
        $this->assertTrue($invalidValidator->fails());
    }

    public function test_route_service_provider_rate_limiters_estan_registrados(): void
    {
        $apiLimiter = RateLimiter::limiter('api');
        $publicLimiter = RateLimiter::limiter('public');

        $this->assertIsCallable($apiLimiter);
        $this->assertIsCallable($publicLimiter);

        $apiRequest = Request::create('/api/system', 'GET');
        $apiLimit = $apiLimiter($apiRequest);

        $publicRequest = Request::create('/login', 'GET');
        $publicLimit = $publicLimiter($publicRequest);

        $this->assertInstanceOf(Limit::class, $apiLimit);
        $this->assertInstanceOf(Limit::class, $publicLimit);
    }
}