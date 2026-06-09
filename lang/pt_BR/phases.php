<?php

declare(strict_types=1);

/*
 * Tournament phase display names in Brazilian Portuguese, keyed by the `PhaseKey` enum value
 * (stored language-neutral on `phases.key`). The English `phases.name` stays canonical in the
 * database; the frontend resolves the display name from `phase_key` at render time.
 */

return [
    'group' => 'Fase de Grupos',
    'round_of_32' => 'Rodada de 32',
    'round_of_16' => 'Oitavas de final',
    'quarter_finals' => 'Quartas de final',
    'semi_finals' => 'Semifinais',
    'third_place' => 'Disputa de 3º lugar',
    'final' => 'Final',
];
