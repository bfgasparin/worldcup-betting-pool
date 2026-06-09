<?php

declare(strict_types=1);

/*
 * Host venue display labels in Brazilian Portuguese, keyed by the full English venue string as
 * stored on `fixtures.venue`. Values mirror the English UI, which shows the city (the generic
 * " Stadium" suffix is dropped). A missing key falls back to the English label with " Stadium"
 * stripped.
 */

return [
    'Mexico City Stadium' => 'Cidade do México',
    'Guadalajara Stadium' => 'Guadalajara',
    'Monterrey Stadium' => 'Monterrey',
    'Atlanta Stadium' => 'Atlanta',
    'Toronto Stadium' => 'Toronto',
    'San Francisco Bay Stadium' => 'Baía de São Francisco',
    'Los Angeles Stadium' => 'Los Angeles',
    'BC Place Vancouver' => 'Vancouver',
    'Seattle Stadium' => 'Seattle',
    'New York New Jersey Stadium' => 'Nova York / Nova Jersey',
    'Boston Stadium' => 'Boston',
    'Philadelphia Stadium' => 'Filadélfia',
    'Miami Stadium' => 'Miami',
    'Houston Stadium' => 'Houston',
    'Kansas City Stadium' => 'Kansas City',
    'Dallas Stadium' => 'Dallas',
];
