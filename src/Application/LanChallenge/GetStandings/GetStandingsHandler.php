<?php

namespace App\Application\LanChallenge\GetStandings;

use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;
use App\Domain\LanChallenge\RoundResult;
use App\Domain\LanChallenge\Verdict;

/**
 * Classement de la LAN : tous les participants, qu'ils aient rejoint les défis
 * ou non, pour que l'écran de la LAN montre aussi qui n'a pas encore commencé.
 *
 * Ordre : points, puis défis réussis (à points égaux, la régularité l'emporte),
 * puis Riot ID pour un ordre stable.
 */
final readonly class GetStandingsHandler
{
    public function __construct(
        private LanSummaryReaderInterface $lans,
        private PlayerRunRepositoryInterface $runs,
    ) {
    }

    public function handle(int $lanId): StandingsView
    {
        $lan = $this->lans->find($lanId)
            ?? throw new LanNotFoundException(sprintf('Aucune LAN pour l\'identifiant %d.', $lanId));

        $runsByPuuid = [];
        foreach ($this->runs->findByLan($lanId) as $run) {
            $runsByPuuid[$run->puuid] = $run;
        }

        $entries = [];
        foreach ($lan->participants as $puuid => $riotId) {
            $entries[] = self::entryFor($riotId, $runsByPuuid[$puuid] ?? null);
        }

        usort($entries, static fn(StandingEntry $left, StandingEntry $right) => [$right->score, $right->successes, $left->riotId]
            <=> [$left->score, $left->successes, $right->riotId]);

        return new StandingsView($lan, $entries);
    }

    private static function entryFor(string $riotId, ?PlayerRun $run): StandingEntry
    {
        if ($run === null) {
            return new StandingEntry($riotId, false, 0, 0, 0, 0, null, false);
        }

        $successes = count(array_filter($run->results, static fn(RoundResult $result) => $result->verdict === Verdict::SUCCESS));

        return new StandingEntry(
            $riotId,
            true,
            $run->score,
            $successes,
            count($run->results),
            $run->rerollsLeft,
            $run->activeCard,
            $run->lockedGameId !== null,
        );
    }
}
