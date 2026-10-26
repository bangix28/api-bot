<?php

namespace App\Tests\Application\LanChallenge;

use App\Application\LanChallenge\PickCard\PickCardHandler;
use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\SabotageService;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Domain\LanChallenge\InMemoryLanSummaryReader;
use App\Tests\Domain\LanChallenge\InMemoryPlayerRunRepository;
use App\Tests\Domain\LanChallenge\InMemorySabotageLog;
use App\Tests\Domain\LanChallenge\ScriptedRandomizer;
use App\Tests\Domain\Player\InMemoryTrackedRiotAccountRepository;
use App\Tests\Domain\Shared\FixedClock;

/**
 * Une LAN en cours et trois joueurs Discord, chacun avec un compte participant :
 *   1001 → Alpha#EUW (puuid-a), 1002 → Bravo#EUW (puuid-b), 1003 → Charlie#EUW (puuid-c).
 *
 * Tirages par défaut (argent, première carte libre) : chaque offre vaut
 * [Doublé, Prudent, Esprit d'équipe].
 */
final class LanChallengeFixture
{
    public const int LAN = 7;
    public const string ALPHA = '1001';
    public const string BRAVO = '1002';
    public const string CHARLIE = '1003';

    public InMemoryLanSummaryReader $lans;
    public InMemoryTrackedRiotAccountRepository $accounts;
    public InMemoryPlayerRunRepository $runs;
    public CardDealer $dealer;
    public FixedClock $clock;
    public InMemorySabotageLog $sabotageLog;

    public function __construct(?\DateTimeImmutable $now = null)
    {
        $this->lans = (new InMemoryLanSummaryReader())->add(new LanSummary(
            self::LAN,
            'LAN d\'automne',
            new \DateTimeImmutable('2026-11-07 14:00'),
            new \DateTimeImmutable('2026-11-08 04:00'),
            ['puuid-a' => 'Alpha#EUW', 'puuid-b' => 'Bravo#EUW', 'puuid-c' => 'Charlie#EUW'],
        ));
        $this->accounts = (new InMemoryTrackedRiotAccountRepository())
            ->add(new TrackedRiotAccount('Alpha#EUW', 'puuid-a', self::ALPHA))
            ->add(new TrackedRiotAccount('Bravo#EUW', 'puuid-b', self::BRAVO))
            ->add(new TrackedRiotAccount('Charlie#EUW', 'puuid-c', self::CHARLIE));
        $this->runs = new InMemoryPlayerRunRepository();
        $this->dealer = new CardDealer(new ScriptedRandomizer());
        $this->sabotageLog = new InMemorySabotageLog();
        $this->clock = new FixedClock($now ?? new \DateTimeImmutable('2026-11-07 21:00'));
    }

    public function seats(): PlayerSeatResolver
    {
        return new PlayerSeatResolver($this->lans, $this->accounts, $this->clock);
    }

    public function pickCardHandler(): PickCardHandler
    {
        return new PickCardHandler($this->seats(), $this->runs, new SabotageService($this->dealer), $this->sabotageLog);
    }
}
