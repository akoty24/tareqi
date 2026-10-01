<?php

namespace App\Support;

/**
 * Normalizes free-text place names so that common Arabic spelling variants
 * ("الإسكندرية" / "الاسكندريه") and extra whitespace compare equal.
 */
class PlaceName
{
    private const REPLACEMENTS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
        'ـ' => '',
    ];

    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        // Strip Arabic diacritics (tashkeel).
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $value);
        $value = strtr($value, self::REPLACEMENTS);
        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_substr($value, 0, 120);
    }

    public static function clean(?string $value): string
    {
        return mb_substr(preg_replace('/\s+/u', ' ', trim((string) $value)), 0, 120);
    }
}
