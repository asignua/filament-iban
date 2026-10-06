<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'The :attribute must be a valid IBAN.',
        'format' => 'The :attribute is not a valid IBAN.',
        'unknown_country' => 'The :attribute has an unknown country code.',
        'length' => 'The :attribute must be :length characters long for its country.',
        'structure' => 'The :attribute does not match the account format of its country.',
        'checksum' => 'The :attribute has wrong check digits - please check the number.',
        'country_not_allowed' => 'The :attribute must be an IBAN of one of these countries: :countries.',
    ],
];
