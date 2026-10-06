<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'O campo :attribute deve ser um IBAN válido.',
        'format' => 'O campo :attribute não é um IBAN válido.',
        'unknown_country' => 'O campo :attribute contém um código de país desconhecido.',
        'length' => 'O comprimento do campo :attribute para o seu país deve ser :length.',
        'structure' => 'O campo :attribute não corresponde ao formato de conta do seu país.',
        'checksum' => 'O campo :attribute tem dígitos de controle incorretos: confira o número.',
        'country_not_allowed' => 'O campo :attribute deve ser um IBAN de um destes países: :countries.',
    ],
];
