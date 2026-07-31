<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\PlayerBuild;
use App\Domain\MatchHistory\Runes;
use PHPUnit\Framework\TestCase;

class PlayerBuildTest extends TestCase
{
    public function testAcceptsExactlySevenItemSlots(): void
    {
        $build = new PlayerBuild(
            [3078, 3111, 3071, 0, 0, 0, 3364],
            4,
            11,
            new Runes(8010, 8000, 8100, 5001, 5008, 5005),
        );

        $this->assertCount(7, $build->items);
    }

    public function testRejectsWrongNumberOfItemSlots(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PlayerBuild(
            [3078, 3111, 3071],
            4,
            11,
            new Runes(8010, 8000, 8100, 5001, 5008, 5005),
        );
    }
}
