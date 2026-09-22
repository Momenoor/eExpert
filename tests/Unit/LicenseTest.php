<?php

namespace Tests\Unit;

use App\Models\License;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    private function license(array $attributes = []): License
    {
        return new License(array_merge([
            'status' => 'active',
            'expires_at' => null,
            'last_valid_at' => now(),
        ], $attributes));
    }

    public function test_valid_with_a_recent_check_and_no_expiry(): void
    {
        $this->assertTrue($this->license()->isValid());
    }

    public function test_valid_within_the_grace_period_despite_a_stale_check(): void
    {
        config(['license.grace_days' => 7]);

        $license = $this->license(['last_valid_at' => now()->subDays(3)]);

        $this->assertTrue($license->isValid());
    }

    public function test_invalid_once_the_grace_period_has_elapsed(): void
    {
        config(['license.grace_days' => 7]);

        $license = $this->license(['last_valid_at' => now()->subDays(10)]);

        $this->assertFalse($license->isValid());
    }

    public function test_invalid_with_no_successful_check_ever_recorded(): void
    {
        $license = $this->license(['last_valid_at' => null]);

        $this->assertFalse($license->isValid());
    }

    public function test_invalid_when_suspended_regardless_of_a_recent_check(): void
    {
        $license = $this->license(['status' => 'suspended']);

        $this->assertFalse($license->isValid());
    }

    public function test_invalid_when_revoked_regardless_of_a_recent_check(): void
    {
        $license = $this->license(['status' => 'revoked']);

        $this->assertFalse($license->isValid());
    }

    public function test_invalid_once_expired_regardless_of_a_recent_check(): void
    {
        $license = $this->license(['expires_at' => now()->subDay()]);

        $this->assertFalse($license->isValid());
    }

    public function test_valid_with_a_future_expiry(): void
    {
        $license = $this->license(['expires_at' => now()->addYear()]);

        $this->assertTrue($license->isValid());
    }
}
