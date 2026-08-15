<?php

namespace App\Application\RiotAccount\RefreshData;

use App\Domain\EloSnapshot\EloSnapshotRecorderInterface;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Domain\RiotAccount\RiotAccountRefreshData;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Domain\RiotAccount\RiotApiClientInterface;
use App\Domain\Shared\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RefreshRiotAccountDataHandler
{
    public function __construct(
        private RiotAccountRepositoryInterface $repositoryService,
        private RiotApiClientInterface         $riotApiService,
        private EloSnapshotRecorderInterface   $snapshotRecorder,
        private ClockInterface                 $clock,
        private LoggerInterface                $refreshLogger = new NullLogger(),
    ) {}

    public function handle(RefreshPresenterInterface $presenter): void
    {
        $listAccounts = $this->repositoryService->getListAccount();

        $refreshedAccounts = [];
        $failed = 0;
        foreach ($listAccounts as $account)
        {
            try {
                $refreshedAccounts[] = $this->refreshAccount($account);
            } catch (\Exception $exception){
                // L'échec d'un compte ne doit pas interrompre le refresh des autres.
                ++$failed;
                $this->refreshLogger->warning('Refresh du compte ignoré', [
                    'puuid' => $account->getPuuid(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->refreshLogger->info('Refresh des comptes terminé', [
            'ok' => count($refreshedAccounts),
            'failed' => $failed,
        ]);

        $presenter->present($refreshedAccounts);
    }

    /**
     * Refresh d'un seul compte — utilisé à la création pour que ses données Riot
     * soient présentes tout de suite, sans attendre le cron.
     *
     * Ne rattrape rien volontairement : l'appelant est synchrone (un humain devant
     * l'admin) et doit voir l'échec. Le best effort est la décision de l'adaptateur.
     *
     * @throws RiotAccountNotExistException si aucun compte ne porte ce puuid
     */
    public function handleOne(string $puuid): void
    {
        $account = $this->repositoryService->findByPuuid($puuid);

        if ($account === null) {
            throw new RiotAccountNotExistException("Aucun compte pour le puuid $puuid");
        }

        $this->refreshAccount($account);
    }

    private function refreshAccount(RiotAccountEntity $account): RiotAccountEntity
    {
        $refreshData = $this->riotApiService->getAccount($account->getPuuid());

        $updateAccount = $account
            ->withRankedSolo($refreshData->rankedSolo)
            ->withRankedFlex($refreshData->rankedFlex)
            ->withSummonerLevel($refreshData->summonerLevel)
            ->withLogoId($refreshData->logoId);

        $this->repositoryService->save($updateAccount);

        $this->recordRacePoint($account->getPuuid(), $refreshData);

        return $updateAccount;
    }

    /**
     * Point de course enregistré au passage : les rangs viennent d'être
     * récupérés, l'écriture ne coûte donc aucun appel Riot supplémentaire.
     *
     * Rattrapé volontairement : un échec ici ne doit pas priver le compte de
     * son refresh, ni interrompre la boucle (ADR-0002). L'appelant synchrone
     * de handleOne() n'a pas non plus à échouer pour ça.
     */
    private function recordRacePoint(string $puuid, RiotAccountRefreshData $refreshData): void
    {
        try {
            $this->snapshotRecorder->record(
                $puuid,
                new RankedQueuesSnapshot($refreshData->rankedSolo, $refreshData->rankedFlex),
                $this->clock->now(),
            );
        } catch (\Throwable $e) {
            $this->refreshLogger->warning('Point de course non enregistré', [
                'puuid' => $puuid,
                'exception' => $e,
            ]);
        }
    }
}
