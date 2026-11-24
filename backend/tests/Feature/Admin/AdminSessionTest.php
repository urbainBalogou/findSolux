<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_public_and_dashboard_redirects_guests(): void
    {
        $this->get('/admin/connexion')
            ->assertOk()
            ->assertSee('Administration MecaDispo');

        $this->get('/admin')
            ->assertRedirect('/admin/connexion');
    }

    public function test_valid_credentials_create_an_admin_session(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@findsolux.test',
            'password' => 'mot-de-passe-solide',
        ]);

        $this->post('/admin/connexion', [
            'email' => 'ADMIN@FINDSOLUX.TEST',
            'password' => 'mot-de-passe-solide',
            'remember' => false,
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_invalid_credentials_do_not_create_an_admin_session(): void
    {
        Admin::factory()->create([
            'email' => 'admin@findsolux.test',
            'password' => 'mot-de-passe-solide',
        ]);

        $this->from('/admin/connexion')->post('/admin/connexion', [
            'email' => 'admin@findsolux.test',
            'password' => 'incorrect',
            'remember' => false,
        ])
            ->assertRedirect('/admin/connexion')
            ->assertSessionHasErrors(['email' => 'Les identifiants sont incorrects.']);

        $this->assertGuest('admin');
    }

    public function test_admin_can_log_out(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->delete('/admin/session')
            ->assertRedirect('/admin/connexion');

        $this->assertGuest('admin');
    }
}
