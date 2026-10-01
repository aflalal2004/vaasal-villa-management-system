<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Tests run against the dedicated MySQL database `vaasal_villa_hms28_test` (see phpunit.xml).
 * The schema is migrated and fully seeded (including demo operations) once per run;
 * every test then runs inside a transaction that is rolled back.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function as(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->actingAs($user);
        return $user;
    }
}
