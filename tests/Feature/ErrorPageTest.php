<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_error_page_renders_with_site_style_and_check_engine_light(): void
    {
        $response = $this->get('/non-existent-page-path-1234');

        $response->assertStatus(404);

        // Assert site layout headers and footers exist (standard site style)
        $response->assertSee('Honda<b>base</b>', false);
        $response->assertSee('Community-Driven Honda Knowledgebase');

        // Assert check-engine diagnostic error styling exists
        $response->assertSee('class="err-container"', false);
        $response->assertSee('class="err-graphic"', false);
        $response->assertSee('class="err-icon"', false);
        $response->assertSee('class="err-badge"', false);
        $response->assertSee('404');
        $response->assertSee('Page Not Found');
        $response->assertSee('The page you are looking for does not exist or has been moved.');
    }

    public function test_404_error_page_honours_portuguese_locale(): void
    {
        $response = $this->withCookie(SetLocale::COOKIE, 'pt')
            ->get('/non-existent-page-path-1234');

        $response->assertStatus(404);

        // Assert PT translations are rendered
        $response->assertSee('Página não encontrada');
        $response->assertSee('A página que procura não existe ou foi movida.');
        $response->assertSee('Voltar ao início');
    }
}
