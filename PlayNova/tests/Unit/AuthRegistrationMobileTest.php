<?php

namespace Tests\Unit;

use App\Modules\User\Services\AuthRegistrationService;
use PHPUnit\Framework\TestCase;

class AuthRegistrationMobileTest extends TestCase
{
    private AuthRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthRegistrationService();
    }

    public function test_registration_requires_leading_zero(): void
    {
        $this->assertNull($this->service->normalizeMobileForRegistration('9123456789'));
        $this->assertSame(
            'شماره موبایل باید با صفر شروع شود (مثال: 09123456789).',
            $this->service->registrationMobileError('9123456789')
        );
    }

    public function test_registration_accepts_leading_zero(): void
    {
        $this->assertSame('09123456789', $this->service->normalizeMobileForRegistration('09123456789'));
        $this->assertSame('09123456789', $this->service->normalizeMobileForRegistration('۰۹۱۲۳۴۵۶۷۸۹'));
    }

    public function test_registration_accepts_country_code(): void
    {
        $this->assertSame('09123456789', $this->service->normalizeMobileForRegistration('989123456789'));
        $this->assertSame('09123456789', $this->service->normalizeMobileForRegistration('+989123456789'));
    }

    public function test_lookup_still_accepts_number_without_zero(): void
    {
        $this->assertSame('09123456789', $this->service->normalizeMobileForLookup('9123456789'));
    }
}
