<?php

namespace App\Controller;

use App\Application\LanChallenge\GetCurrentLan\CurrentLanView;
use App\Application\LanChallenge\GetMyRun\MyRunView;
use App\Application\LanChallenge\GetMyRun\SabotageTarget;
use App\Application\LanChallenge\GetStandings\StandingEntry;
use App\Application\LanChallenge\GetStandings\StandingsView;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\Rarity;
use App\Domain\LanChallenge\RoundResult;

/**
 * Forme JSON des défis de LAN, telle que décrite dans docs/front/defis-lan.md.
 * Une carte est toujours représentée de la même façon, où qu'elle apparaisse.
 */
final class LanChallengePresenter
{
    /** @return array<string, mixed> */
    public static function card(Challenge $card): array
    {
        return [
            'code' => $card->value,
            'label' => $card->label(),
            'description' => $card->description(),
            'rarity' => $card->rarity()->value,
            'theme' => $card->theme()?->value,
            'points' => $card->points(),
            'secret' => $card->isSecret(),
            'needsTarget' => $card->targetsPlayer(),
        ];
    }

    /** @return array<string, mixed> */
    public static function catalogue(int $initialRerolls, int $roundBonusPercent): array
    {
        return [
            'cards' => array_map(self::card(...), Challenge::cases()),
            'rarities' => array_map(
                static fn(Rarity $rarity) => ['rarity' => $rarity->value, 'points' => $rarity->points(), 'drawWeight' => $rarity->drawWeight()],
                Rarity::cases(),
            ),
            'rules' => [
                'initialRerolls' => $initialRerolls,
                'roundBonusPercent' => $roundBonusPercent,
                'cursePercent' => PlayerRun::CURSE_PERCENT,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function myRun(MyRunView $view): array
    {
        return [
            'lan' => self::lan($view->lan),
            'riotId' => $view->riotId,
            'joined' => $view->run !== null,
            'run' => $view->run === null ? null : self::run($view->run),
            // Choix possibles pour une carte qui vise un joueur ; vide sinon.
            'sabotageTargets' => array_map(
                static fn(SabotageTarget $target) => ['riotId' => $target->riotId, 'canBePickpocketed' => $target->canBePickpocketed],
                $view->sabotageTargets,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public static function standings(StandingsView $view): array
    {
        return [
            'lan' => self::lan($view->lan),
            'entries' => array_map(
                static fn(StandingEntry $entry, int $index) => [
                    'rank' => $index + 1,
                    'riotId' => $entry->riotId,
                    'joined' => $entry->joined,
                    'score' => $entry->score,
                    'successes' => $entry->successes,
                    'roundsPlayed' => $entry->roundsPlayed,
                    'rerollsLeft' => $entry->rerollsLeft,
                    'cardChosen' => $entry->cardChosen,
                    'cardLocked' => $entry->cardLocked,
                ],
                $view->entries,
                array_keys($view->entries),
            ),
        ];
    }

    /**
     * @param FeedEntry[] $entries
     * @return array<string, mixed>
     */
    public static function feed(array $entries): array
    {
        return [
            'entries' => array_map(
                static fn(FeedEntry $entry) => [
                    'kind' => $entry->kind->value,
                    'riotId' => $entry->riotId,
                    'card' => self::card($entry->card),
                    'gameId' => $entry->gameId,
                    'verdict' => $entry->verdict?->value,
                    'points' => $entry->points,
                    'roundBonus' => $entry->roundBonus,
                    'curseApplied' => $entry->curseApplied,
                    'occurredAt' => $entry->occurredAt->format(\DateTimeInterface::ATOM),
                ],
                $entries,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public static function currentLan(?CurrentLanView $view): array
    {
        if ($view === null) {
            return ['lan' => null, 'riotIds' => []];
        }

        return [
            'lan' => [
                ...self::lan($view->lan),
                'status' => $view->inProgress ? 'in_progress' : 'upcoming',
                'queues' => array_map(
                    static fn(LanQueue $queue) => ['id' => $queue->value, 'label' => $queue->label()],
                    $view->lan->acceptedQueues,
                ),
            ],
            'riotIds' => $view->riotIds,
        ];
    }

    /** @return array<string, mixed> */
    private static function lan(LanSummary $lan): array
    {
        return [
            'id' => $lan->lanId,
            'name' => $lan->name,
            'startsAt' => $lan->startsAt->format(\DateTimeInterface::ATOM),
            'endsAt' => $lan->endsAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private static function run(PlayerRun $run): array
    {
        return [
            'score' => $run->score,
            'rerollsLeft' => $run->rerollsLeft,
            'offer' => $run->offer === null ? null : array_map(self::card(...), $run->offer->cards),
            'activeCard' => $run->activeCard === null ? null : self::card($run->activeCard),
            'cardLocked' => $run->lockedGameId !== null,
            'results' => array_map(
                static fn(RoundResult $result) => [
                    'gameId' => $result->gameId,
                    'card' => self::card($result->card),
                    'verdict' => $result->verdict->value,
                    'points' => $result->points,
                    'roundBonus' => $result->roundBonus,
                    'curseApplied' => $result->curseApplied,
                ],
                $run->results,
            ),
        ];
    }
}
