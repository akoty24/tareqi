<?php

namespace Database\Factories\Support;

/**
 * Realistic local data for a village community in Menoufia governorate
 * and the cities its residents commute to.
 */
class EgyptianData
{
    public const VILLAGES = [
        'ميت خاقان', 'سرس الليان', 'البتانون', 'كمشيش', 'ميت أبو الكوم', 'الراهب', 'كفر شبرا زنجي',
    ];

    public const CITIES = [
        'شبين الكوم', 'القاهرة - رمسيس', 'طنطا', 'بنها', 'الجيزة - الدقي', 'منوف', 'الإسكندرية',
    ];

    public const MALE_NAMES = [
        'محمد', 'أحمد', 'محمود', 'مصطفى', 'إبراهيم', 'خالد', 'عمرو', 'يوسف', 'حسن', 'علي', 'طارق', 'كريم', 'عبد الرحمن', 'سامح',
    ];

    public const FEMALE_NAMES = [
        'فاطمة', 'مريم', 'سارة', 'نورا', 'هبة', 'آية', 'منى', 'دينا', 'ياسمين', 'رحاب',
    ];

    public const FAMILY_NAMES = [
        'عبد الله', 'السيد', 'الشافعي', 'منصور', 'عبد العزيز', 'حسانين', 'الجمال', 'سليمان', 'فرج', 'البحيري', 'عطية', 'غنيم',
    ];

    public const CAR_MODELS = [
        'هيونداي إلنترا', 'تويوتا كورولا', 'نيسان صني', 'شيفروليه أفيو', 'كيا سيراتو', 'فيات تيبو', 'سكودا أوكتافيا', 'رينو لوجان',
    ];

    public const COLORS = ['أبيض', 'أسود', 'فضي', 'أحمر', 'أزرق', 'رمادي', 'بيج'];

    public const PLATE_LETTERS = ['أ', 'ب', 'ج', 'د', 'ر', 'س', 'ص', 'ط', 'ع', 'ف', 'ق', 'ل', 'م', 'ن', 'ه', 'و', 'ى'];

    public const TRIP_NOTES = [
        'التحرك من أمام المسجد الكبير.',
        'ممنوع التدخين في العربية.',
        'ممكن نقف في الطريق لو حد محتاج.',
        'التجمع عند موقف الكوبري.',
        'شنط صغيرة فقط من فضلك.',
        null,
    ];

    public const REVIEWS = [
        'سواق محترم ومواعيده مظبوطة.',
        'رحلة مريحة جدًا، شكرًا.',
        'العربية نضيفة والسواقة هادية.',
        'اتأخر شوية بس كان ذوق جدًا.',
        'راكب محترم وجه في الميعاد.',
        null,
    ];

    public static function name(): string
    {
        $first = fake()->boolean(65)
            ? fake()->randomElement(self::MALE_NAMES)
            : fake()->randomElement(self::FEMALE_NAMES);

        return $first.' '.fake()->randomElement(self::MALE_NAMES).' '.fake()->randomElement(self::FAMILY_NAMES);
    }

    public static function phone(): string
    {
        return '01'.fake()->randomElement(['0', '1', '2', '5']).fake()->unique()->numerify('########');
    }

    public static function plate(): string
    {
        $letters = implode(' ', fake()->randomElements(self::PLATE_LETTERS, 3));

        return $letters.' '.fake()->numberBetween(100, 9999);
    }
}
