<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Le champ :attribute doit être un IBAN valide.',
        'format' => 'Le champ :attribute n\'est pas un IBAN valide.',
        'unknown_country' => 'Le champ :attribute contient un code pays inconnu.',
        'length' => 'La longueur du champ :attribute pour son pays doit être de :length.',
        'structure' => 'Le champ :attribute ne correspond pas au format de compte de son pays.',
        'checksum' => 'Le champ :attribute a une clé de contrôle incorrecte : vérifiez le numéro.',
        'country_not_allowed' => 'Le champ :attribute doit être un IBAN de l\'un de ces pays : :countries.',
    ],
];
