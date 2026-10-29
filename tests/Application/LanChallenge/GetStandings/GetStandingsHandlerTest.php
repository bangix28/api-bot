<?php

namespace App\Tests\Application\LanChallenge\GetStandings;

use App\Application\LanChallenge\GetStandings\GetStandingsHandler;
use App\Application\LanChallenge\GetStandings\StandingEntry;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\RoundResult;
use App\Domain\LanChallenge\Verdict;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use PHPUnit\Framework\TestCase;

class GetStandingsHandlerTest extends TestCase
{
    private LanChallengeFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanChallengeFixture();
    }

    /** @param RoundResult[] $results */
    private function storeRun(string $puuid, int $score, array $results, ?Challenge $activeCard = null, ?string $lockedGameId = null): void
    {
        $offer = $activeCard === null ? new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]) : null;
        $this->fixture->runs->save(PlayerRun::restore(LanChallengeFixture::LAN, $puuid, 2, $score, $offer, $activeCard, $lockedGameId, $results));
    }

    private static function success(int $points): RoundResult
    {
        return new RoundResult('game', Challenge::DOUBLE_KILL, Verdict::SUCCESS, $points, false);
    }

    private static function failure(): RoundResult
    {
        return new RoundResult('game', Challenge::QUADRA_KILL, Verdict::FAILURE, 0, false);
    }

    /** @return StandingEntry[] */
    private function standings(): array
    {
        return (new GetStandingsHandler($this->fixture->lans, $this->fixture->runs))->handle(LanChallengeFixture::LAN)->entries;
    }

    public function testLesParticipantsSontClassesParPoints(): void
    {
        $this->storeRun('puuid-a', 10, [self::success(10)]);
        $this->storeRun('puuid-b', 60, [self::success(60)]);
        $this->storeRun('puuid-c', 25, [self::success(25)]);

        $this->assertSame(['Bravo#EUW', 'Charlie#EUW', 'Alpha#EUW'], array_map(
            static fn(StandingEntry $entry) => $entry->riotId,
            $this->standings(),
        ));
    }

    public function testAPointsEgauxLaRegulariteLEmporte(): void
    {
        // Même total, mais Charlie l'a fait en deux défis réussis, Alpha en un seul.
        $this->storeRun('puuid-a', 20, [self::success(20), self::failure()]);
        $this->storeRun('puuid-c', 20, [self::success(10), self::success(10)]);

        $entries = $this->standings();

        $this->assertSame('Charlie#EUW', $entries[0]->riotId);
        $this->assertSame(2, $entries[0]->successes);
        $this->assertSame('Alpha#EUW', $entries[1]->riotId);
        $this->assertSame(1, $entries[1]->successes);
        $this->assertSame(2, $entries[1]->roundsPlayed);
    }

    public function testAEgaliteParfaiteLOrdreEstAlphabetique(): void
    {
        $this->storeRun('puuid-c', 10, [self::success(10)]);
        $this->storeRun('puuid-a', 10, [self::success(10)]);

        $this->assertSame(['Alpha#EUW', 'Charlie#EUW', 'Bravo#EUW'], array_map(
            static fn(StandingEntry $entry) => $entry->riotId,
            $this->standings(),
        ));
    }

    public function testUnParticipantQuiNAPasRejointApparaitQuandMeme(): void
    {
        $this->storeRun('puuid-a', 10, [self::success(10)]);

        $bravo = array_values(array_filter($this->standings(), static fn(StandingEntry $entry) => $entry->riotId === 'Bravo#EUW'))[0];

        $this->assertFalse($bravo->joined);
        $this->assertSame(0, $bravo->score);
        $this->assertFalse($bravo->cardChosen);
    }

    public function testOnSaitQuUneCarteEstChoisieJamaisLaquelle(): void
    {
        $this->storeRun('puuid-a', 0, [], Challenge::BAD_LOSER, 'game-9');
        $this->storeRun('puuid-b', 0, [], Challenge::PRUDENT);
        $this->storeRun('puuid-c', 0, []);

        $entries = $this->standings();
        $byRiotId = array_column(array_map(static fn(StandingEntry $entry) => [$entry->riotId, $entry], $entries), 1, 0);

        // Une carte saboteur et une carte ordinaire se présentent pareil.
        $this->assertTrue($byRiotId['Alpha#EUW']->cardChosen);
        $this->assertTrue($byRiotId['Bravo#EUW']->cardChosen);
        $this->assertFalse($byRiotId['Charlie#EUW']->cardChosen, 'Offre en attente de choix.');
        $this->assertTrue($byRiotId['Alpha#EUW']->cardLocked);
        $this->assertFalse($byRiotId['Bravo#EUW']->cardLocked, 'Choisie mais pas encore en jeu.');
        $this->assertSame(2, $byRiotId['Bravo#EUW']->rerollsLeft);
    }

    public function testUneLanInconnueEstSignalee(): void
    {
        $this->expectException(LanNotFoundException::class);

        (new GetStandingsHandler($this->fixture->lans, $this->fixture->runs))->handle(999);
    }
}
