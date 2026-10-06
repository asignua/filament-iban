<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => ':attribute alanı geçerli bir IBAN olmalıdır.',
        'format' => ':attribute alanı geçerli bir IBAN değil.',
        'unknown_country' => ':attribute alanında bilinmeyen bir ülke kodu var.',
        'length' => ':attribute alanının uzunluğu ülkesi için :length olmalıdır.',
        'structure' => ':attribute alanı ülkesinin hesap biçimine uymuyor.',
        'checksum' => ':attribute alanının kontrol basamakları yanlış, lütfen numarayı kontrol edin.',
        'country_not_allowed' => ':attribute alanı şu ülkelerden birine ait bir IBAN olmalıdır: :countries.',
    ],
];
