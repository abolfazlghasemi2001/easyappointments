<?php

namespace Tests\Unit\Helper;

use Tests\TestCase;

/**
 * Iranian phone number helper tests.
 */
class PhoneHelperTest extends TestCase
{
    public function testMobileNumbersAreNormalized(): void
    {
        $this->assertSame('09123456789', normalize_iran_phone_number('09123456789'));
        $this->assertSame('09123456789', normalize_iran_phone_number('9123456789'));
        $this->assertSame('09123456789', normalize_iran_phone_number('+989123456789'));
        $this->assertSame('09123456789', normalize_iran_phone_number('00989123456789'));
        $this->assertSame('09123456789', normalize_iran_phone_number('0912 345 6789'));
        $this->assertSame('09123456789', normalize_iran_phone_number('۰۹۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('', normalize_iran_phone_number(null));
        $this->assertSame('', normalize_iran_phone_number('not a phone'));
    }

    public function testMobileNumbersAreValidated(): void
    {
        $this->assertTrue(is_valid_iran_mobile_number('09123456789'));
        $this->assertTrue(is_valid_iran_mobile_number('+989123456789'));
        $this->assertTrue(is_valid_iran_mobile_number('۰۹۱۲۳۴۵۶۷۸۹'));

        $this->assertFalse(is_valid_iran_mobile_number('08123456789'));
        $this->assertFalse(is_valid_iran_mobile_number('091234567'));
        $this->assertFalse(is_valid_iran_mobile_number(''));
        $this->assertFalse(is_valid_iran_mobile_number(null));

        // The general validator also accepts landline numbers.
        $this->assertTrue(is_valid_iran_phone_number('02112345678'));
        $this->assertTrue(is_valid_iran_phone_number('09123456789'));
        $this->assertFalse(is_valid_iran_phone_number('123'));
    }

    public function testPhoneNumbersCanBeMaskedForLogs(): void
    {
        $this->assertSame('0912***6789', mask_phone_number('09123456789'));
        $this->assertSame('******', mask_phone_number('123456'));
    }
}
