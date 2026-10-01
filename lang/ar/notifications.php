<?php

return [
    'greeting' => 'أهلاً :name،',
    'open' => 'فتح في مشوار',
    'seats' => '{1} مقعد واحد|{2} مقعدين|[3,10] :count مقاعد|[11,*] :count مقعد',

    'booking_created_pending' => [
        'title' => 'تم إرسال طلب الحجز',
        'message' => 'طلبك لحجز :seats في رحلة :origin ← :destination يوم :date الساعة :time في انتظار موافقة صاحب الرحلة.',
    ],
    'booking_created_confirmed' => [
        'title' => 'تم تأكيد حجزك',
        'message' => 'تم تأكيد حجز :seats في رحلة :origin ← :destination يوم :date الساعة :time.',
    ],
    'new_booking_request' => [
        'title' => 'طلب حجز جديد',
        'message' => ':passenger يطلب حجز :seats في رحلتك :origin ← :destination يوم :date. وافق أو ارفض الطلب.',
    ],
    'new_booking_confirmed' => [
        'title' => 'حجز جديد على رحلتك',
        'message' => ':passenger حجز :seats في رحلتك :origin ← :destination يوم :date.',
    ],
    'booking_confirmed' => [
        'title' => 'تمت الموافقة على حجزك',
        'message' => 'وافق صاحب الرحلة على حجزك في رحلة :origin ← :destination يوم :date الساعة :time.',
    ],
    'booking_rejected' => [
        'title' => 'تم رفض طلب الحجز',
        'message' => 'للأسف تم رفض طلبك في رحلة :origin ← :destination يوم :date. ابحث عن رحلة أخرى.',
    ],
    'booking_cancelled_by_passenger' => [
        'title' => 'تم إلغاء حجز',
        'message' => ':passenger ألغى حجز :seats في رحلتك :origin ← :destination يوم :date.',
    ],
    'booking_cancelled_by_owner' => [
        'title' => 'تم إلغاء حجزك',
        'message' => 'ألغى صاحب الرحلة حجزك في رحلة :origin ← :destination يوم :date.',
    ],
    'trip_cancelled' => [
        'title' => 'تم إلغاء الرحلة',
        'message' => 'تم إلغاء رحلة :origin ← :destination يوم :date الساعة :time. نأسف للإزعاج.',
    ],
    'trip_cancelled_by_admin' => [
        'title' => 'ألغت الإدارة رحلة',
        'message' => 'قامت إدارة المنصة بإلغاء رحلة :origin ← :destination يوم :date.',
    ],
    'trip_approaching' => [
        'title' => 'رحلتك قربت',
        'message' => 'رحلة :origin ← :destination تتحرك :date الساعة :time. استعد!',
    ],
    'return_trip_approaching' => [
        'title' => 'رحلة العودة قربت',
        'message' => 'رحلة العودة :origin ← :destination تتحرك يوم :date الساعة :time.',
    ],
    'trip_request_matched' => [
        'title' => 'لقينا رحلة مناسبة لطلبك',
        'message' => 'في رحلة :origin ← :destination يوم :date الساعة :time مناسبة لطلبك. احجز قبل ما المقاعد تخلص.',
    ],
];
