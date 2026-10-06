<?php

declare(strict_types=1);

return [
    'validation' => [
        'invalid' => 'Het veld :attribute moet een geldig IBAN zijn.',
        'format' => 'Het veld :attribute is geen geldig IBAN.',
        'unknown_country' => 'Het veld :attribute bevat een onbekende landcode.',
        'length' => 'Het veld :attribute moet voor zijn land :length tekens lang zijn.',
        'structure' => 'Het veld :attribute komt niet overeen met het rekeningformaat van zijn land.',
        'checksum' => 'Het veld :attribute heeft onjuiste controlecijfers - controleer het nummer.',
        'country_not_allowed' => 'Het veld :attribute moet een IBAN zijn uit een van deze landen: :countries.',
    ],
];
