<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Community-Driven Honda Knowledgebase');
    }

    public function test_locale_cookie_switches_ui_to_portuguese(): void
    {
        $this->withCookie(SetLocale::COOKIE, 'pt')
            ->get('/')
            ->assertOk()
            ->assertSee('lang="pt-PT"', false)
            ->assertSee('Base de Conhecimento Honda da Comunidade')
            ->assertDontSee('Community-Driven Honda Knowledgebase');
    }

    public function test_accept_language_header_is_honoured_when_no_cookie(): void
    {
        $this->get('/', ['Accept-Language' => 'pt-PT,pt;q=0.9,en;q=0.8'])
            ->assertOk()
            ->assertSee('lang="pt-PT"', false);
    }

    public function test_switch_route_persists_a_supported_locale(): void
    {
        $this->get('/locale/pt', ['Referer' => '/'])
            ->assertRedirect('/')
            ->assertCookie(SetLocale::COOKIE, 'pt');
    }

    public function test_switch_route_ignores_an_unsupported_locale(): void
    {
        $this->get('/locale/xx', ['Referer' => '/'])
            ->assertRedirect('/')
            ->assertCookieMissing(SetLocale::COOKIE);
    }
}
