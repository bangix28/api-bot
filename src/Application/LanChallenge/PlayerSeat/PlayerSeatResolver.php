<?php

namespace App\Application\LanChallenge\PlayerSeat;

use App\Domain\LanChallenge\LanFinishedException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\LanChallenge\NotALanParticipantException;
use App\Domain\LanChallenge\SeveralAccountsInLanException;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Retrouve avec quel compte Riot un joueur Discord joue une LAN.
 *
 * Un joueur peut posséder plusieurs comptes, mais n'en fait participer qu'un à
 * une LAN donnée : sans cette règle, « choisis une carte » serait ambigu.
 */
final readonly class PlayerSeatResolver
{
    public function __construct(
        private LanSummaryReaderInterface $lans,
        private TrackedRiotAccountRepositoryInterface $accounts,
        private ClockInterface $clock,
    ) {
    }

    public function resolve(int $lanId, string $discordId): PlayerSeat
    {
        $lan = $this->lans->find($lanId)
            ?? throw new LanNotFoundException(sprintf('Aucune LAN pour l\'identifiant %d.', $lanId));

        $seats = array_values(array_filter(
            $this->accounts->findOwnedBy($discordId),
            static fn(TrackedRiotAccount $account) => $lan->hasParticipant($account->puuid),
        ));

        if ($seats === []) {
            throw new NotALanParticipantException(sprintf('Aucun de tes comptes ne participe à la LAN « %s ».', $lan->name));
        }

        if (count($seats) > 1) {
            throw new SeveralAccountsInLanException(sprintf(
                'Plusieurs de tes comptes participent à la LAN « %s » (%s) : un administrateur doit n\'en garder qu\'un.',
                $lan->name,
                implode(', ', array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $seats)),
            ));
        }

        return new PlayerSeat($lan, $seats[0]->puuid, $seats[0]->riotId);
    }

    /** Comme resolve(), pour une action qui modifie le parcours : refusée une fois la LAN terminée. */
    public function resolveForAction(int $lanId, string $discordId): PlayerSeat
    {
        $seat = $this->resolve($lanId, $discordId);

        if ($seat->lan->isFinishedAt($this->clock->now())) {
            throw new LanFinishedException(sprintf('La LAN « %s » est terminée.', $seat->lan->name));
        }

        return $seat;
    }
}
