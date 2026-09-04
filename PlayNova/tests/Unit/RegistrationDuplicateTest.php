<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_is_taken_case_insensitively(): void
    {
        User::factory()->create(['username' => 'PlayerOne']);

        $this->assertTrue(User::usernameIsTaken('playerone'));
        $this->assertTrue(User::usernameIsTaken(' PlayerOne '));
        $this->assertFalse(User::usernameIsTaken('PlayerTwo'));
    }

    public function test_cod_id_is_taken_case_insensitively(): void
    {
        User::factory()->create(['cod_id' => 'COD-123']);

        $this->assertTrue(User::codIdIsTaken('cod-123'));
        $this->assertTrue(User::codIdIsTaken(' COD-123 '));
        $this->assertFalse(User::codIdIsTaken('COD-456'));
    }
}
