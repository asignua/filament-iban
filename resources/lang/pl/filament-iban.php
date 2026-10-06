<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Pole :attribute musi być prawidłowym numerem IBAN.',
        'format' => 'Pole :attribute nie jest prawidłowym numerem IBAN.',
        'unknown_country' => 'Pole :attribute zawiera nieznany kod kraju.',
        'length' => 'Pole :attribute musi mieć dla swojego kraju :length znaków.',
        'structure' => 'Pole :attribute nie pasuje do formatu rachunku swojego kraju.',
        'checksum' => 'Pole :attribute ma błędne cyfry kontrolne – sprawdź numer.',
        'country_not_allowed' => 'Pole :attribute musi być numerem IBAN z jednego z krajów: :countries.',
    ],
];
