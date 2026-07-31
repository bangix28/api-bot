<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\MatchPerformance;
use PHPUnit\Framework\TestCase;

class MatchPerformanceTest extends TestCase
{
    public function testFromEmptyChallengesReturnsAllNull(): void
    {
        $performance = MatchPerformance::fromChallenges([]);

        $this->assertNull($performance->kda);
        $this->assertNull($performance->killParticipation);
        $this->assertNull($performance->damagePerMinute);
        $this->assertNull($performance->goldPerMinute);
        $this->assertNull($performance->visionScorePerMinute);
    }

    public function testFromPartialChallengesKeepsMissingKeysNull(): void
    {
        $performance = MatchPerformance::fromChallenges([
            'kda' => 3.5,
            'goldPerMinute' => 412.7,
        ]);

        $this->assertSame(3.5, $performance->kda);
        $this->assertSame(412.7, $performance->goldPerMinute);
        $this->assertNull($performance->killParticipation);
        $this->assertNull($performance->damagePerMinute);
        $this->assertNull($performance->visionScorePerMinute);
    }

    public function testFromChallengesCastsIntValuesToFloat(): void
    {
        // Riot renvoie parfois des int (ex. kda: 2 pour un KDA rond)
        $performance = MatchPerformance::fromChallenges(['kda' => 2]);

        $this->assertSame(2.0, $performance->kda);
    }
}
