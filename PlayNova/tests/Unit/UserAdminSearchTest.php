<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserAdminSearchTest extends TestCase
{
    public function test_ascii_digits_converts_persian_and_arabic_numerals(): void
    {
        $this->assertSame('09121234567', User::asciiDigits('۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('09121234567', User::asciiDigits('٠٩١٢١٢٣٤٥٦٧'));
        $this->assertSame('Player1', User::asciiDigits('Player۱'));
    }

    public function test_mobile_search_variants_cover_local_and_international_forms(): void
    {
        $variants = User::mobileSearchVariants('۰۹۱۲۱۲۳۴۵۶۷');

        $this->assertContains('09121234567', $variants);
        $this->assertContains('9121234567', $variants);
        $this->assertContains('989121234567', $variants);
    }

    public function test_mobile_search_variants_from_number_without_zero(): void
    {
        $variants = User::mobileSearchVariants('9121234567');

        $this->assertContains('09121234567', $variants);
        $this->assertContains('9121234567', $variants);
    }
}
