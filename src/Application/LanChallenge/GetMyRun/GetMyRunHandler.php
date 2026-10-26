<?php

namespace App\Application\LanChallenge\GetMyRun;

use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

/**
 * Le parcours du joueur connecté dans une LAN, y compris après la fin de la LAN.
 *
 * Quand son offre contient une carte qui vise un joueur, la vue liste les
 * cibles possibles : les participants inscrits aux défis, sauf lui-même.
 */
final readonly class GetMyRunHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
    ) {
    }

    public function handle(int $lanId, string $discordId): MyRunView
    {
        $seat = $this->seats->resolve($lanId, $discordId);
        $run = $this->runs->find($lanId, $seat->puuid);

        return new MyRunView($seat->lan, $seat->riotId, $run, $this->sabotageTargets($seat->lan, $run));
    }

    /** @return SabotageTarget[] */
    private function sabotageTargets(LanSummary $lan, ?PlayerRun $saboteur): array
    {
        $canSabotage = $saboteur?->offer !== null && array_any(
            $saboteur->offer->cards,
            static fn(Challenge $card) => $card->targetsPlayer(),
        );
        if (!$canSabotage) {
            return [];
        }

        $targets = [];
        foreach ($this->runs->findByLan($lan->lanId) as $candidate) {
            $riotId = $lan->participants[$candidate->puuid] ?? null;
            if ($candidate->puuid === $saboteur->puuid || $riotId === null) {
                continue;
            }
            $targets[] = new SabotageTarget($riotId, $candidate->rerollsLeft > 0);
        }

        usort($targets, static fn(SabotageTarget $left, SabotageTarget $right) => strcasecmp($left->riotId, $right->riotId));

        return $targets;
    }
}
