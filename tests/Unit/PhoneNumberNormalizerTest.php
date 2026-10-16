<?php

namespace Tests\Unit;

use App\Services\Reservations\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentItalianMobiles(): array
    {
        return [
            'national with spaces' => ['333 123 4567', '+393331234567'],
            'plus prefix' => ['+39 3331234567', '+393331234567'],
            'double zero prefix' => ['0039 3331234567', '+393331234567'],
            'dashes and dots' => ['333-123.4567', '+393331234567'],
            'parentheses' => ['(+39) 333 1234567', '+393331234567'],
        ];
    }

    #[DataProvider('equivalentItalianMobiles')]
    public function test_italian_mobile_formats_are_normalized_to_the_same_number(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->normalize($input));
    }

    public function test_italian_landlines_keep_their_leading_zero(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        $this->assertSame('+39061234567', $normalizer->normalize('06 1234567'));
        $this->assertSame('+39061234567', $normalizer->normalize('+39 06 1234567'));
        $this->assertSame('+39061234567', $normalizer->normalize('0039 06 1234567'));
    }

    public function test_international_numbers_with_explicit_prefix_are_accepted(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        $this->assertSame('+442079460958', $normalizer->normalize('+44 20 7946 0958'));
        $this->assertSame('+4915112345678', $normalizer->normalize('0049 151 12345678'));
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        foreach (['', 'abc', '123', '+0123456789', '+12', '333 abc 4567', '+1234567890123456', '333123456789'] as $invalid) {
            $this->assertNull($normalizer->normalize($invalid), "'{$invalid}' should be rejected");
        }
    }
}
