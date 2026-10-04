<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha',
            'email' => 'Asha@Example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'admin', // must be ignored
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'asha@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'asha@example.com', 'role' => 'customer']);
    }

    public function test_login_rejects_wrong_password_with_generic_message(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'right-pass']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-pass'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_login_user_and_logout_flow(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'right-pass']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'right-pass'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'a@example.com');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_user_endpoint_returns_json_401(): void
    {
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
        $this->get('/api/v1/auth/user')->assertUnauthorized(); // no redirect to a missing login route
    }
}
