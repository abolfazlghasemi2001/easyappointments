<?php

namespace Tests\Unit\Localization;

use DateTime;
use Tests\TestCase;

/**
 * Localization helper tests.
 */
class LocalizationHelperTest extends TestCase
{
    /**
     * Make sure the localization helper is available for every test (the helpers of the application are not
     * necessarily autoloaded while the tests are executed).
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $CI = &get_instance();

        $CI->load->helper('localization');
    }

    public function testRtlLanguagesAreDetected(): void
    {
        $this->assertTrue(is_rtl('persian'));

        $this->assertTrue(is_rtl('arabic'));

        $this->assertTrue(is_rtl('hebrew'));

        $this->assertFalse(is_rtl('english'));

        $this->assertFalse(is_rtl('german'));
    }

    public function testTextDirectionFollowsTheApplicationLanguage(): void
    {
        $CI = &get_instance();

        $original_language = config('language');

        config(['language' => 'persian']);

        $this->assertSame('rtl', app_text_direction());

        config(['language' => 'english']);

        $this->assertSame('ltr', app_text_direction());

        config(['language' => $original_language]);
    }

    public function testDigitsAreLocalizedAccordingToTheSetting(): void
    {
        $digits = persian_digits_enabled();

        if ($digits) {
            $this->assertSame('۱۲۳۴۵', localize_digits('12345'));

            $this->assertSame('۱۲,۳۴۵', localize_number('12345'));

            $this->assertSame('۱۲۳۴۵', localize_phone('12345'));
        } else {
            $this->assertSame('12345', localize_digits('12345'));

            $this->assertSame('12,345', localize_number('12345'));
        }
    }

    public function testPersianDigitsCanAlwaysBeConvertedBackToLatinDigits(): void
    {
        $this->assertSame('1405/07/08', to_latin_digits('۱۴۰۵/۰۷/۰۸'));

        $this->assertSame('123', to_latin_digits('123'));
    }

    public function testLocalizeDateFormatsTheValueWithTheApplicationCalendar(): void
    {
        $date = new DateTime('2026-09-30 14:30:00');

        $formatted = localize_date($date);

        $this->assertNotEmpty($formatted);

        if (jalali_calendar_enabled()) {
            $this->assertStringContainsString('۱۴۰۵', $formatted, 'The Jalali year must be displayed.');
        } else {
            $this->assertStringContainsString('2026', $formatted);
        }

        $this->assertNotEmpty(localize_date_time($date));

        $this->assertNotEmpty(localize_time($date));
    }

    public function testLocalizeDateReturnsAnEmptyStringForInvalidValues(): void
    {
        $this->assertSame('', localize_date(null));

        $this->assertSame('', localize_date(''));
    }

    public function testAppTimezoneFallsBackToAValidIdentifier(): void
    {
        $this->assertContains(app_timezone(), timezone_identifiers_list());
    }

    public function testDateValuesAreValidated(): void
    {
        $this->assertTrue(is_valid_date_value('2026-09-30'));

        $this->assertFalse(is_valid_date_value('2026-02-30'));

        $this->assertFalse(is_valid_date_value('1405/07/08'));

        $this->assertFalse(is_valid_date_value(null));

        $this->assertFalse(is_valid_date_value(20260930));
    }
}
