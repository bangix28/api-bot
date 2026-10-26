<?php

namespace App\Tests\Application\LanChallenge\GetFeed;

use App\Application\LanChallenge\GetFeed\GetFeedHandler;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\RoundResult;
use App\Domain\LanChallenge\Verdict;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use App\Tests\Domain\LanChallenge\InMemoryLanFeedQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GetFeedHandlerTest extends TestCase
{
    public function testLeFilRenvoieLesDernieresManchesDeLaLan(): void
    {
        $entry = FeedEntry::round(
            'Alpha#EUW',
            new RoundResult('game-1', Challenge::PENTA_KILL, Verdict::SUCCESS, 100, false),
            new \DateTimeImmutable('2026-11-07 21:30'),
        );
        $feed = new InMemoryLanFeedQuery([LanChallengeFixture::LAN => [$entry]]);

        $entries = (new GetFeedHandler((new LanChallengeFixture())->lans, $feed))->handle(LanChallengeFixture::LAN);

        $this->assertSame([$entry], $entries);
        $this->assertSame(GetFeedHandler::DEFAULT_LIMIT, $feed->requestedLimit);
    }

    /** @return iterable<string, array{int, int}> */
    public static function limits(): iterable
    {
        yield 'limite raisonnable' => [10, 10];
        yield 'au moins une manche' => [0, 1];
        yield 'jamais négative' => [-5, 1];
        yield 'plafonnée' => [500, GetFeedHandler::MAX_LIMIT];
    }

    #[DataProvider('limits')]
    public function testLaLimiteEstBornee(int $requested, int $expected): void
    {
        $feed = new InMemoryLanFeedQuery();

        (new GetFeedHandler((new LanChallengeFixture())->lans, $feed))->handle(LanChallengeFixture::LAN, $requested);

        $this->assertSame($expected, $feed->requestedLimit);
    }

    public function testUneLanInconnueEstSignalee(): void
    {
        $this->expectException(LanNotFoundException::class);

        (new GetFeedHandler((new LanChallengeFixture())->lans, new InMemoryLanFeedQuery()))->handle(999);
    }
}
