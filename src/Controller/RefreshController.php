<?php

namespace App\Controller;

use App\Application\MatchHistory\RefreshData\RefreshMatchHistoryCommand;
use App\Application\MatchHistory\RefreshData\RefreshRiotMatchDataHandler;
use App\Application\RiotAccount\RefreshData\RefreshRiotAccountDataHandler;
use App\Infrastructure\RiotAccount\RefreshViewPresenter;
use App\Repository\RiotAccountRepository;
use App\Services\RiotApiServices\RiotApiServices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RefreshController extends AbstractController
{

    public function __construct(
        private RiotApiServices $riotApiService,
        private RiotAccountRepository $riotAccountRepository
    )
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/refresh', name: 'app_refresh')]
    public function refreshSummoner(
        RefreshRiotAccountDataHandler $handler,
        RefreshRiotMatchDataHandler $matchDataHandler
    ): Response
    {
        $presenter = new RefreshViewPresenter();
        $handler->handle($presenter);

        foreach ( $this->riotAccountRepository->findAll() as $account) {
            $matchDataHandler->handle(new RefreshMatchHistoryCommand(
               $account->getPuuid(),
               $account->getLastUpdate()?->getTimestamp()
            ));
        }

        return $this->render('refresh/refresh.html.twig', [
            'page_title' => 'Refresh des comptes',
            'accounts' => $presenter->viewModel(),
        ]);
    }
    #[Route('/getDailyElo', name: 'app_daily_elo')]
    public function getDailyElo(): Response
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        $dataToShow = [];
        foreach ($listeAccount as $account) {
            $this->riotApiService->getDailyElo($account);
            $dataToShow[] = $account;
        }

        return $this->render('refresh/daily_elo.html.twig', [
            'page_title' => 'Daily Elo',
            'accounts' => $dataToShow,
        ]);
    }

    /**
     * @todo Besoins d'attendre la clée de production de RIOT
     */
   /* #[Route('/test', name: 'app_test')]
    public function test(): JsonResponse
    {
        $providerRegistrationParameters = new ProviderRegistrationParameters(['region' => 'EUW','url' => 'test'],null);
        $codeTournamentProvider = $this->riotApi->riotApiInit()->createTournamentProvider($providerRegistrationParameters);

        $tournamentRegisterParameters = new TournamentRegistrationParameters(['providerId' => $codeTournamentProvider,'name' => 'bctg'],null);
        $codeTournament = $this->riotApi->riotApiInit()->createTournament($tournamentRegisterParameters);


        $createCodeTournamentParameter = new TournamentCodeParameters([
            "allowedParticipants" => [],
            "enoughPlayers" => false,
            "mapType" => "HOWLING_ABYSS",
            "metadata" => "",
            "pickType" => "BLIND_PICK",
            "spectatorType" => "LOBBYONLY",
            "teamSize" => 1
        ],null);
        $test = $this->riotApi->riotApiInit()->createTournamentCodes($codeTournament,1,$createCodeTournamentParameter);

        return new JsonResponse([''], Response::HTTP_OK);
    }*/

}
