<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Поле :attribute має бути дійсним IBAN.',
        'format' => 'Поле :attribute не є дійсним IBAN.',
        'unknown_country' => 'У полі :attribute невідомий код країни.',
        'length' => 'Поле :attribute для своєї країни має містити :length символів.',
        'structure' => 'Поле :attribute не відповідає формату рахунку своєї країни.',
        'checksum' => 'У полі :attribute неправильні контрольні цифри — перевірте номер.',
        'country_not_allowed' => 'Поле :attribute має бути IBAN однієї з країн: :countries.',
    ],
];
