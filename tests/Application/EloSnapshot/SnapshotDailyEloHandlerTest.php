<?php

namespace App\Tests\Application\EloSnapshot;

use App\Application\EloSnapshot\SnapshotDailyElo\SnapshotDailyEloHandler;
use App\Domain\EloSnapshot\DailyEloSnapshot;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Tests\Domain\EloSnapshot\FakeRankedQueuesProvider;
use App\Tests\Domain\EloSnapshot\InMemoryEloSnapshotRepository;
use App\Tests\Domain\Logging\SpyLogger;
use App\Tests\Domain\RiotAccount\InMemoryRiotAccountRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class SnapshotDailyEloHandlerTest extends TestCase
{
    private const string TODAY = '2026-08-05';

    public function testUnCompteClasseDansLesDeuxFilesProduitDeuxLignes(): void
    {
        // Arrange
        $accounts = new InMemoryRiotAccountRepository([$this->account('Toto#EUW', 'puuid-toto')]);
        $provider = new FakeRankedQueuesProvider([
            'puuid-toto' => new RankedQueuesSnapshot($this->gold(), $this->silver()),
        ]);
        $repository = new InMemoryEloSnapshotRepository();

        // Act
        $summary = $this->handler($accounts, $provider, $repository)->handleAll();

        // Assert : une ligne par file, datées du jour
        $this->assertSame(1, $summary->ok);
        $this->assertCount(2, $repository->all());
        $queues = array_map(static fn(DailyEloSnapshot $s) => $s->queue, $repository->all());
        $this->assertSame([RankedQueueType::SOLO, RankedQueueType::FLEX], $queues);
        $this->assertSame(self::TODAY, $repository->all()[0]->day->format('Y-m-d'));
    }

    public function testUnCompteNonClasseEnFlexNeProduitQuUneLigneSolo(): void
    {
        $accounts = new InMemoryRiotAccountRepository([$this->account('Toto#EUW', 'puuid-toto')]);
        $provider = new FakeRankedQueuesProvider([
            'puuid-toto' => new RankedQueuesSnapshot($this->gold(), null),
        ]);
        $repository = new InMemoryEloSnapshotRepository();

        $this->handler($accounts, $provider, $repository)->handleAll();

        $this->assertCount(1, $repository->all());
        $this->assertSame(RankedQueueType::SOLO, $repository->all()[0]->queue);
    }

    public function testUnCompteDejaSnapshoteAujourdhuiEstIgnoreSansAppelRiot(): void
    {
        $accounts = new InMemoryRiotAccountRepository([$this->account('Toto#EUW', 'puuid-toto')]);
        $provider = new FakeRankedQueuesProvider([
            'puuid-toto' => new RankedQueuesSnapshot($this->gold(), null),
        ]);
        $repository = new InMemoryEloSnapshotRepository([
            new DailyEloSnapshot('puuid-toto', new \DateTimeImmutable(self::TODAY), RankedQueueType::SOLO, $this->gold()),
        ]);

        $summary = $this->handler($accounts, $provider, $repository)->handleAll();

        // Idempotence : rien de créé, et surtout zéro appel API (quota Riot).
        $this->assertSame(1, $summary->skipped);
        $this->assertSame(0, $provider->callCount());
        $this->assertCount(1, $repository->all());
    }

    public function testLEchecDUnCompteNInterromptPasLesAutres(): void
    {
        $accounts = new InMemoryRiotAccountRepository([
            $this->account('KO#EUW', 'puuid-ko'),
            $this->account('OK#EUW', 'puuid-ok'),
        ]);
        $provider = new FakeRankedQueuesProvider(
            ['puuid-ok' => new RankedQueuesSnapshot($this->gold(), null)],
            failedPuuid: 'puuid-ko',
        );
        $repository = new InMemoryEloSnapshotRepository();
        $logger = new SpyLogger();

        $summary = $this->handler($accounts, $provider, $repository, $logger)->handleAll();

        // ADR-0002 : échec isolé -> warning + compteur, le reste du run continue.
        $this->assertSame(1, $summary->ok);
        $this->assertSame(1, $summary->failed);
        $this->assertCount(1, $repository->all());
        $this->assertSame('puuid-ok', $repository->all()[0]->puuid);
        $this->assertCount(1, $logger->records('warning'));
    }

    public function testUnCompteNonClasseNeProduitAucuneLigneMaisUnWarning(): void
    {
        // Pas de score = pas de course : l'absence de ligne est la règle métier,
        // le warning sert à distinguer « non classé » d'un trou de données.
        $accounts = new InMemoryRiotAccountRepository([$this->account('Toto#EUW', 'puuid-toto')]);
        $provider = new FakeRankedQueuesProvider([
            'puuid-toto' => new RankedQueuesSnapshot(null, null),
        ]);
        $repository = new InMemoryEloSnapshotRepository();
        $logger = new SpyLogger();

        $summary = $this->handler($accounts, $provider, $repository, $logger)->handleAll();

        $this->assertSame(1, $summary->skipped);
        $this->assertCount(0, $repository->all());
        $this->assertCount(1, $logger->records('warning'));
    }

    public function testHandleOneSnapshotUnSeulCompteALInscription(): void
    {
        $provider = new FakeRankedQueuesProvider([
            'puuid-nouveau' => new RankedQueuesSnapshot($this->gold(), $this->silver()),
        ]);
        $repository = new InMemoryEloSnapshotRepository();

        $this->handler(new InMemoryRiotAccountRepository(), $provider, $repository)
            ->handleOne('puuid-nouveau');

        $this->assertCount(2, $repository->all());
    }

    private function handler(
        InMemoryRiotAccountRepository $accounts,
        FakeRankedQueuesProvider $provider,
        InMemoryEloSnapshotRepository $repository,
        ?SpyLogger $logger = null,
    ): SnapshotDailyEloHandler {
        return new SnapshotDailyEloHandler(
            $accounts,
            $provider,
            $repository,
            new FixedClock(new \DateTimeImmutable(self::TODAY)),
            $logger ?? new SpyLogger(),
        );
    }

    private function account(string $riotId, string $puuid): RiotAccountEntity
    {
        return new RiotAccountEntity(
            $riotId,
            $puuid,
            explode('#', $riotId)[0],
            new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0),
            30,
            '685',
        );
    }

    private function gold(): RankedQueueEntity
    {
        return new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 40, 12, 10);
    }

    private function silver(): RankedQueueEntity
    {
        return new RankedQueueEntity(RankedRank::I, RankedTier::SILVER, 75, 8, 9);
    }
}
