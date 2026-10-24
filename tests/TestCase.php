<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** actingAs represents a newly authenticated session, not a pre-deployment cookie. */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($user instanceof User && $user->role !== 'admin') {
            $this->withSession(['auth_version' => (int) ($user->fresh()?->auth_version ?? 0)]);
        }

        return parent::actingAs($user, $guard);
    }
}
