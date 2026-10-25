<?php

namespace App\Tests\Application\Lan\RefreshMatches;

use App\Application\Lan\RefreshMatches\LanRefreshTargetsProvider;
use App\Domain\Lan\LanEvent;
use App\Domain\MatchHistory\MatchHistoryRefreshTarget;
use App\Domain\MatchHistory\MatchQueue;
use App\Tests\Domain\Lan\InMemoryLanEventRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class LanRefreshTargetsProviderTest extends TestCase
{
    private function provider(\DateTimeImmutable $now, LanEvent ...$lanEvents): LanRefreshTargetsProvider
    {
        return new LanRefreshTargetsProvider(new InMemoryLanEventRepository($lanEvents), new FixedClock($now));
    }

    public function testAucuneCibleHorsLan(): void
    {
        // Le cron tourne toutes les 2 minutes : hors LAN, il ne doit coûter aucun appel Riot.
        $lan = new LanEvent(1, 'LAN', new \DateTimeImmutable('2026-11-07 14:00'), new \DateTimeImmutable('2026-11-08 04:00'), ['puuid-1']);

        $this->assertSame([], $this->provider(new \DateTimeImmutable('2026-11-06 20:00'), $lan)->all());
    }

    public function testLesParticipantsDUneLanEnCoursSontCollectesSurLAramChaosDepuisLeDebut(): void
    {
        $start = new \DateTimeImmutable('2026-11-07 14:00');
        $lan = new LanEvent(1, 'LAN', $start, new \DateTimeImmutable('2026-11-08 04:00'), ['puuid-1', 'puuid-2']);

        $targets = $this->provider(new \DateTimeImmutable('2026-11-07 21:00'), $lan)->all();

        $this->assertEquals([
            new MatchHistoryRefreshTarget('puuid-1', $start->getTimestamp(), [MatchQueue::ARAM_MAYHEM]),
            new MatchHistoryRefreshTarget('puuid-2', $start->getTimestamp(), [MatchQueue::ARAM_MAYHEM]),
        ], $targets);
    }

    public function testUnParticipantDeDeuxLanQuiSeChevauchentNEstCollecteQuUneFoisDepuisLaPlusAncienne(): void
    {
        $earliest = new \DateTimeImmutable('2026-11-07 14:00');
        $first = new LanEvent(1, 'LAN 1', $earliest, new \DateTimeImmutable('2026-11-08 04:00'), ['puuid-1']);
        $second = new LanEvent(2, 'LAN 2', new \DateTimeImmutable('2026-11-07 18:00'), new \DateTimeImmutable('2026-11-07 23:00'), ['puuid-1']);

        $targets = $this->provider(new \DateTimeImmutable('2026-11-07 20:00'), $second, $first)->all();

        $this->assertCount(1, $targets);
        $this->assertSame($earliest->getTimestamp(), $targets[0]->since);
    }
}
