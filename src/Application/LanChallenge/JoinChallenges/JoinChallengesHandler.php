<?php

namespace App\Application\LanChallenge\JoinChallenges;

use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

/**
 * Inscrit un joueur aux défis d'une LAN et lui distribue sa première offre.
 * Idempotent : rejoindre deux fois ne redistribue pas, sinon ce serait une
 * relance gratuite.
 */
final readonly class JoinChallengesHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
        private CardDealer $dealer,
    ) {
    }

    public function handle(JoinChallengesCommand $command): PlayerRun
    {
        $seat = $this->seats->resolveForAction($command->lanId, $command->discordId);

        $existing = $this->runs->find($command->lanId, $seat->puuid);
        if ($existing !== null) {
            return $existing;
        }

        $run = PlayerRun::start($command->lanId, $seat->puuid, $this->dealer->dealOffer());
        $this->runs->save($run);

        return $run;
    }
}
