<?php

namespace Tests\Unit\Services\Games;

use App\Models\Game;
use App\Services\Games\PrizePool;
use PHPUnit\Framework\TestCase;

class PrizePoolTest extends TestCase
{
    /**
     * @param  list<array{place: int, percentage: int}>|null  $prizes
     */
    private function game(float $entryPrice, float $feePercentage, ?array $prizes = null): Game
    {
        return new Game([
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

    public function test_it_computes_the_pool_net_and_per_place_amounts(): void
    {
        // 8 players × R$50 = R$400 pool; less 15% fee = R$340 net; split 70/20/10.
        $pool = PrizePool::forGame($this->game(50, 15), 8);

        $this->assertSame('BRL', $pool->currency);
        $this->assertSame(50.0, $pool->entryPrice);
        $this->assertSame(8, $pool->players);
        $this->assertSame(400.0, $pool->pool);
        $this->assertSame(340.0, $pool->net);

        $this->assertSame(1, $pool->prizes[0]['place']);
        $this->assertSame(238.0, $pool->prizes[0]['amount']);
        $this->assertSame(68.0, $pool->prizes[1]['amount']);
        $this->assertSame(34.0, $pool->prizes[2]['amount']);
    }

    public function test_place_amounts_always_sum_to_the_net_pool(): void
    {
        // R$33.33 net (1 player, no fee): 70/20/10 floors to 23.33/6.66/3.33 = 33.32, leaving a
        // 0.01 remainder that must be folded into first place so the parts sum back to the net.
        $pool = PrizePool::forGame($this->game(33.33, 0), 1);

        $this->assertSame(33.33, $pool->net);
        $this->assertSame(23.34, $pool->prizes[0]['amount']);
        $this->assertSame(6.66, $pool->prizes[1]['amount']);
        $this->assertSame(3.33, $pool->prizes[2]['amount']);
        $this->assertSame(33.33, round(array_sum(array_column($pool->prizes, 'amount')), 2));
    }

    public function test_an_empty_pool_yields_zero_amounts(): void
    {
        $pool = PrizePool::forGame($this->game(50, 15), 0);

        $this->assertSame(0.0, $pool->pool);
        $this->assertSame(0.0, $pool->net);
        $this->assertCount(3, $pool->prizes);

        foreach ($pool->prizes as $prize) {
            $this->assertSame(0.0, $prize['amount']);
        }
    }
}
