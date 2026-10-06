<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Il campo :attribute deve essere un IBAN valido.',
        'format' => 'Il campo :attribute non è un IBAN valido.',
        'unknown_country' => 'Il campo :attribute contiene un codice paese sconosciuto.',
        'length' => 'La lunghezza del campo :attribute per il suo paese deve essere di :length.',
        'structure' => 'Il campo :attribute non corrisponde al formato del conto del suo paese.',
        'checksum' => 'Il campo :attribute ha cifre di controllo errate: verifica il numero.',
        'country_not_allowed' => 'Il campo :attribute deve essere un IBAN di uno di questi paesi: :countries.',
    ],
];
