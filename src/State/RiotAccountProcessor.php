<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\EloSnapshot\SnapshotDailyElo\SnapshotDailyEloHandler;
use App\Services\RiotApiServices\RiotApiServices;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RiotAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private RiotApiServices $riotApiServices,
        private SnapshotDailyEloHandler $snapshotDailyElo,
        private LoggerInterface $refreshLogger = new NullLogger(),
    ) {
    }

    /**
     * @throws Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $data = $this->riotApiServices->riotAccountFill($data);
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // Snapshot initial : le joueur entre dans la Ranked Race dès son inscription,
        // sans attendre le cron de 3h. Best effort : un échec ne doit pas annuler l'inscription.
        try {
            $this->snapshotDailyElo->handleOne($data->getPuuid());
        } catch (Exception $e) {
            $this->refreshLogger->warning('Snapshot elo initial impossible à l\'inscription', [
                'puuid' => $data->getPuuid(),
                'exception' => $e,
            ]);
        }

        return $result;
    }
}
