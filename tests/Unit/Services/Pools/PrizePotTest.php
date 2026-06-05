<?php

namespace Tests\Unit\Services\Pools;

use App\Models\Pool;
use App\Services\Pools\PrizePot;
use PHPUnit\Framework\TestCase;

class PrizePotTest extends TestCase
{
    /**
     * @param  list<array{place: int, percentage: int}>|null  $prizes
     */
    private function pool(float $entryPrice, float $feePercentage, ?array $prizes = null): Pool
    {
        return new Pool([
            'entry_price' => $entryPrice,
            'currency' => 'BRL',
            'house_fee_percentage' => $feePercentage,
            'prize_structure' => $prizes ?? [
                ['place' => 1, 'percentage' => 70],
                ['place' => 2, 'percentage' => 20],
                ['place' => 3, 'percentage' => 10],
            ],
        ]);
    }

    public function test_it_computes_the_pot_net_and_per_place_amounts(): void
    {
        // 8 players × R$50 = R$400 pot; less 15% fee = R$340 net; split 70/20/10.
        $pot = PrizePot::forPool($this->pool(50, 15), 8);

        $this->assertSame('BRL', $pot->currency);
        $this->assertSame(50.0, $pot->entryPrice);
        $this->assertSame(8, $pot->players);
        $this->assertSame(400.0, $pot->pot);
        $this->assertSame(340.0, $pot->net);

        $this->assertSame(1, $pot->prizes[0]['place']);
        $this->assertSame(238.0, $pot->prizes[0]['amount']);
        $this->assertSame(68.0, $pot->prizes[1]['amount']);
        $this->assertSame(34.0, $pot->prizes[2]['amount']);
    }

    public function test_place_amounts_always_sum_to_the_net_pot(): void
    {
        // R$33.33 net (1 player, no fee): 70/20/10 floors to 23.33/6.66/3.33 = 33.32, leaving a
        // 0.01 remainder that must be folded into first place so the parts sum back to the net.
        $pot = PrizePot::forPool($this->pool(33.33, 0), 1);

        $this->assertSame(33.33, $pot->net);
        $this->assertSame(23.34, $pot->prizes[0]['amount']);
        $this->assertSame(6.66, $pot->prizes[1]['amount']);
        $this->assertSame(3.33, $pot->prizes[2]['amount']);
        $this->assertSame(33.33, round(array_sum(array_column($pot->prizes, 'amount')), 2));
    }

    public function test_an_empty_pool_yields_zero_amounts(): void
    {
        $pot = PrizePot::forPool($this->pool(50, 15), 0);

        $this->assertSame(0.0, $pot->pot);
        $this->assertSame(0.0, $pot->net);
        $this->assertCount(3, $pot->prizes);

        foreach ($pot->prizes as $prize) {
            $this->assertSame(0.0, $prize['amount']);
        }
    }
}
