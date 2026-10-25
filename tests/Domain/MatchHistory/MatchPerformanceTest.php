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

    public function testFromChallengesReadsAramStats(): void
    {
        $performance = MatchPerformance::fromChallenges([
            'teamDamagePercentage' => 0.31,
            'damageTakenOnTeamPercentage' => 0.4,
            'snowballsHit' => 4,
            'skillshotsDodged' => 27.0,
            'soloKills' => 2,
            'outnumberedKills' => 1,
        ]);

        $this->assertSame(0.31, $performance->teamDamagePercentage);
        $this->assertSame(0.4, $performance->damageTakenOnTeamPercentage);
        $this->assertSame(4, $performance->snowballsHit);
        // Les compteurs restent des entiers même si Riot les sérialise en float.
        $this->assertSame(27, $performance->skillshotsDodged);
        $this->assertSame(2, $performance->soloKills);
        $this->assertSame(1, $performance->outnumberedKills);
    }

    public function testAramStatsAreNullWhenAbsent(): void
    {
        // Un compteur absent n'est pas un zéro : un défi « 3 boules de neige »
        // ne doit pas être déclaré échoué sur une donnée que Riot n'a pas fournie.
        $performance = MatchPerformance::fromChallenges([]);

        $this->assertNull($performance->snowballsHit);
        $this->assertNull($performance->teamDamagePercentage);
    }
}
