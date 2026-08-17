<?php

namespace App\Support;

/**
 * The currencies a PairPay account can be denominated in. Kept small and
 * explicit rather than pulling the full ISO 4217 list — every entry here is one
 * the user can actually pick in profile settings.
 */
final class Currencies
{
    public const DEFAULT = 'USD';

    /**
     * @var array<string, string>
     */
    private const SUPPORTED = [
        'USD' => 'US Dollar',
        'PHP' => 'Philippine Peso',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'JPY' => 'Japanese Yen',
        'AUD' => 'Australian Dollar',
        'CAD' => 'Canadian Dollar',
        'SGD' => 'Singapore Dollar',
        'HKD' => 'Hong Kong Dollar',
        'MYR' => 'Malaysian Ringgit',
        'IDR' => 'Indonesian Rupiah',
        'THB' => 'Thai Baht',
        'VND' => 'Vietnamese Dong',
        'INR' => 'Indian Rupee',
        'CNY' => 'Chinese Yuan',
        'KRW' => 'South Korean Won',
        'AED' => 'UAE Dirham',
        'NZD' => 'New Zealand Dollar',
    ];

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::SUPPORTED);
    }

    /**
     * Shaped for a `<select>` on the front end.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $code, string $name): array => [
                'value' => $code,
                'label' => $code.' — '.$name,
            ],
            array_keys(self::SUPPORTED),
            array_values(self::SUPPORTED),
        );
    }
}
