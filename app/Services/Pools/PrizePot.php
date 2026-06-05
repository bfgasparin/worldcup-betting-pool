<?php

namespace App\Services\Pools;

use App\Models\Pool;
use Illuminate\Support\Collection;

/**
 * The money side of a pool: the buy-in, the accumulated pot (buy-in × joined players), the
 * organizer's house cut, and the net pot split per place. Payment itself is handled externally;
 * this only computes the figures the UI shows.
 *
 * All amounts are in the pool's currency, rounded to whole cents. Each place's share is floored to
 * cents and the rounding remainder folded into first place, so the place amounts always sum to
 * exactly the net pot (no phantom or lost centavos from independent rounding).
 */
class PrizePot
{
    /**
     * @param  list<array{place: int, percentage: float, amount: float}>  $prizes
     */
    public function __construct(
        public readonly string $currency,
        public readonly float $entryPrice,
        public readonly int $players,
        public readonly float $pot,
        public readonly float $houseFeePercentage,
        public readonly float $net,
        public readonly array $prizes,
    ) {}

    public static function forPool(Pool $pool, int $players): self
    {
        $entryPrice = (float) $pool->entry_price;
        $feePercentage = (float) $pool->house_fee_percentage;

        $pot = round($entryPrice * $players, 2);
        $net = round($pot * (1 - $feePercentage / 100), 2);

        /** @var Collection<int, array{place: int, percentage: float}> $structure */
        $structure = collect($pool->prize_structure ?? [])
            ->map(fn (array $row): array => [
                'place' => (int) $row['place'],
                'percentage' => (float) $row['percentage'],
            ])
            ->sortBy('place')
            ->values();

        // Floor each share to cents first, then fold the leftover into the top place so the parts
        // always sum to exactly the net pot.
        $amounts = $structure
            ->map(fn (array $row): float => floor($net * $row['percentage'] / 100 * 100) / 100)
            ->all();

        if ($amounts !== []) {
            $amounts[0] = round($amounts[0] + ($net - array_sum($amounts)), 2);
        }

        $prizes = $structure
            ->map(fn (array $row, int $index): array => [
                'place' => $row['place'],
                'percentage' => $row['percentage'],
                'amount' => $amounts[$index],
            ])
            ->all();

        return new self($pool->currency, $entryPrice, $players, $pot, $feePercentage, $net, $prizes);
    }

    /**
     * @return array{currency: string, entry_price: float, players: int, pot: float, house_fee_percentage: float, net: float, prizes: list<array{place: int, percentage: float, amount: float}>}
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'entry_price' => $this->entryPrice,
            'players' => $this->players,
            'pot' => $this->pot,
            'house_fee_percentage' => $this->houseFeePercentage,
            'net' => $this->net,
            'prizes' => $this->prizes,
        ];
    }
}
