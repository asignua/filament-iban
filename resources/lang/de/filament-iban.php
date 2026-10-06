<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Das Feld :attribute muss eine gültige IBAN sein.',
        'format' => 'Das Feld :attribute ist keine gültige IBAN.',
        'unknown_country' => 'Das Feld :attribute enthält einen unbekannten Ländercode.',
        'length' => 'Die Länge des Feldes :attribute muss für sein Land :length betragen.',
        'structure' => 'Das Feld :attribute entspricht nicht dem Kontoformat seines Landes.',
        'checksum' => 'Das Feld :attribute hat falsche Prüfziffern – bitte die Nummer prüfen.',
        'country_not_allowed' => 'Das Feld :attribute muss eine IBAN aus einem dieser Länder sein: :countries.',
    ],
];
