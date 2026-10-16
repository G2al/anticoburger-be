<?php

namespace App\Services\Reservations;

/**
 * Normalizes phone numbers to the E.164 format (+<country code><number>).
 *
 * Numbers without an international prefix are treated as Italian numbers and keep
 * their leading zero (landlines such as 06 1234567 become +39061234567).
 * "00" is accepted as an alternative to "+" for the international prefix.
 */
class PhoneNumberNormalizer
{
    private const ITALIAN_PREFIX = '39';

    private const ITALIAN_MIN_DIGITS = 6;

    private const ITALIAN_MAX_DIGITS = 11;

    private const E164_MIN_DIGITS = 8;

    private const E164_MAX_DIGITS = 15;

    /**
     * Returns the normalized number, or null when the input is not a plausible phone number.
     */
    public function normalize(string $phone): ?string
    {
        $compact = preg_replace('/[\s\-.\/()]+/', '', trim($phone));

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        if (str_starts_with($compact, '+')) {
            return $this->normalizeInternational(substr($compact, 1));
        }

        return $this->normalizeItalianNational($compact);
    }

    private function normalizeInternational(string $digits): ?string
    {
        if (! ctype_digit($digits) || str_starts_with($digits, '0')) {
            return null;
        }

        if (str_starts_with($digits, self::ITALIAN_PREFIX)) {
            return $this->normalizeItalianNational(substr($digits, strlen(self::ITALIAN_PREFIX)));
        }

        $length = strlen($digits);

        if ($length < self::E164_MIN_DIGITS || $length > self::E164_MAX_DIGITS) {
            return null;
        }

        return '+'.$digits;
    }

    private function normalizeItalianNational(string $digits): ?string
    {
        $length = strlen($digits);

        if (! ctype_digit($digits) || $length < self::ITALIAN_MIN_DIGITS || $length > self::ITALIAN_MAX_DIGITS) {
            return null;
        }

        return '+'.self::ITALIAN_PREFIX.$digits;
    }
}
