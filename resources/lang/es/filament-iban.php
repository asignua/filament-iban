<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'El campo :attribute debe ser un IBAN válido.',
        'format' => 'El campo :attribute no es un IBAN válido.',
        'unknown_country' => 'El campo :attribute tiene un código de país desconocido.',
        'length' => 'La longitud del campo :attribute para su país debe ser de :length.',
        'structure' => 'El campo :attribute no coincide con el formato de cuenta de su país.',
        'checksum' => 'El campo :attribute tiene dígitos de control incorrectos: compruebe el número.',
        'country_not_allowed' => 'El campo :attribute debe ser un IBAN de uno de estos países: :countries.',
    ],
];
