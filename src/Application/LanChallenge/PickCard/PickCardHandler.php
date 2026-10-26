<?php

namespace App\Application\LanChallenge\PickCard;

use App\Application\LanChallenge\PlayerSeat\PlayerSeat;
use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\InvalidSabotageTargetException;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;
use App\Domain\LanChallenge\SabotageLogInterface;
use App\Domain\LanChallenge\SabotageService;
use App\Domain\LanChallenge\SabotageTargetNotAllowedException;
use App\Domain\LanChallenge\SabotageTargetRequiredException;

/**
 * Choisit une carte de l'offre. Une carte qui vise un joueur ne devient pas la
 * carte du match : son effet s'applique tout de suite à la cible, et le
 * saboteur reçoit une offre neuve.
 */
final readonly class PickCardHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
        private SabotageService $sabotages,
        private SabotageLogInterface $sabotageLog,
    ) {
    }

    public function handle(PickCardCommand $command): PlayerRun
    {
        $seat = $this->seats->resolveForAction($command->lanId, $command->discordId);

        $run = $this->runs->find($command->lanId, $seat->puuid)
            ?? throw new PlayerRunNotFoundException('Tu n\'as pas encore rejoint les défis de cette LAN.');

        if (!$run->cardInOffer($command->slot)->targetsPlayer()) {
            if ($command->targetRiotId !== null) {
                throw new SabotageTargetNotAllowedException('Cette carte ne vise personne : pas de cible à désigner.');
            }

            $run = $run->pick($command->slot);
            $this->runs->save($run);

            return $run;
        }

        return $this->sabotage($seat, $run, $command);
    }

    private function sabotage(PlayerSeat $seat, PlayerRun $saboteur, PickCardCommand $command): PlayerRun
    {
        if ($command->targetRiotId === null) {
            throw new SabotageTargetRequiredException('Cette carte vise un joueur : choisis ta cible.');
        }

        $targetPuuid = $seat->lan->puuidOf($command->targetRiotId)
            ?? throw new InvalidSabotageTargetException(sprintf('« %s » ne participe pas à cette LAN.', $command->targetRiotId));

        $target = $this->runs->find($command->lanId, $targetPuuid)
            ?? throw new InvalidSabotageTargetException(sprintf('« %s » n\'a pas encore rejoint les défis.', $command->targetRiotId));

        $outcome = $this->sabotages->sabotage($saboteur, $command->slot, $target);

        $this->runs->save($outcome->target);
        $this->runs->save($outcome->saboteur);
        $this->sabotageLog->record($command->lanId, $outcome->sabotage);

        return $outcome->saboteur;
    }
}
