<?php

namespace Tests\Unit\Services\Predictions;

use App\Services\Predictions\ThirdPlaceAllocation;
use PHPUnit\Framework\TestCase;

class ThirdPlaceAllocationTest extends TestCase
{
    private ThirdPlaceAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocation = new ThirdPlaceAllocation;
    }

    public function test_it_contains_every_one_of_the_495_combinations_exactly_once(): void
    {
        $all = $this->allocation->all();

        $this->assertCount(495, $all); // C(12,8) = 495

        $this->assertSame(
            $this->everyCombinationOfEightGroups(),
            array_keys($all),
        );
    }

    public function test_every_row_is_a_perfect_matching_of_the_qualifying_groups(): void
    {
        foreach ($this->allocation->all() as $combination => $assignment) {
            // Exactly the eight slots are filled.
            $this->assertSame(ThirdPlaceAllocation::SLOT_MATCH_NUMBERS, array_keys($assignment));

            // The assigned groups are precisely the qualifying groups (a bijection).
            $assigned = array_values($assignment);
            sort($assigned);

            $this->assertSame(str_split($combination), $assigned, "Row {$combination} is not a perfect matching.");
        }
    }

    public function test_every_assignment_respects_the_eligible_groups(): void
    {
        foreach ($this->allocation->all() as $combination => $assignment) {
            foreach ($assignment as $matchNumber => $group) {
                $this->assertContains(
                    $group,
                    ThirdPlaceAllocation::ELIGIBLE_GROUPS[$matchNumber],
                    "Row {$combination}: 3{$group} cannot be drawn into match {$matchNumber}.",
                );
            }
        }
    }

    public function test_eligible_groups_define_eight_distinct_five_group_slots(): void
    {
        $sets = ThirdPlaceAllocation::ELIGIBLE_GROUPS;

        $this->assertCount(8, $sets);
        $this->assertSame(ThirdPlaceAllocation::SLOT_MATCH_NUMBERS, array_keys($sets));

        foreach ($sets as $groups) {
            $this->assertCount(5, $groups);
        }

        // Each slot's eligible set is unique, so a slot is identifiable from its set.
        $encoded = array_map(fn (array $groups): string => implode('', $groups), $sets);
        $this->assertCount(8, array_unique($encoded));
    }

    public function test_assign_returns_the_official_matchups_for_a_known_combination(): void
    {
        $assignment = $this->allocation->assign(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

        $this->assertSame(
            [74 => 'C', 77 => 'F', 79 => 'H', 80 => 'E', 81 => 'B', 82 => 'A', 85 => 'G', 87 => 'D'],
            $assignment,
        );
    }

    public function test_assign_is_order_independent(): void
    {
        $this->assertSame(
            $this->allocation->assign(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            $this->allocation->assign(['H', 'G', 'F', 'E', 'D', 'C', 'B', 'A']),
        );
    }

    public function test_assign_returns_null_for_an_unknown_or_wrong_sized_combination(): void
    {
        $this->assertNull($this->allocation->assign(['A', 'B', 'C'])); // too few
        $this->assertNull($this->allocation->assign(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'])); // too many
    }

    /**
     * @return list<string>
     */
    private function everyCombinationOfEightGroups(): array
    {
        $combinations = [];

        // 12 groups choose 8 -> iterate the 12-bit subsets with exactly 8 bits set.
        $groups = range('A', 'L');
        for ($mask = 0; $mask < (1 << 12); $mask++) {
            if (substr_count(decbin($mask), '1') !== 8) {
                continue;
            }

            $combination = '';
            for ($bit = 0; $bit < 12; $bit++) {
                if ($mask & (1 << $bit)) {
                    $combination .= $groups[$bit];
                }
            }

            $combinations[] = $combination;
        }

        sort($combinations);

        return $combinations;
    }
}
