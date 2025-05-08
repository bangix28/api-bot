<?php

namespace App\Controller;

use App\Repository\RiotAccountRepository;
use App\Services\RiotApiServices\HistoryAccountLolServices;
use App\Services\RiotApiServices\RiotApiServices;
use RiotAPI\Base\BaseAPI;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\RequestParameterException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\LeagueAPI\Objects\ProviderRegistrationParameters;
use RiotAPI\LeagueAPI\Objects\TournamentCodeParameters;
use RiotAPI\LeagueAPI\Objects\TournamentRegistrationParameters;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;


class RefreshController extends AbstractController
{

    public function __construct(private RiotApiServices $riotApiService, private RiotAccountRepository $riotAccountRepository, private HistoryAccountLolServices $historyAccountLolServices)
    {
    }

    /**
     * @throws \Exception
     */
    #[Route('/refresh', name: 'app_refresh')]
    public function refreshSummoner(): JsonResponse
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        foreach ($listeAccount as $account) {
            $this->historyAccountLolServices->getHistoryAccountLol($account);
            $this->riotApiService->riotAccountFill($account);
        }

        return new JsonResponse($listeAccount, Response::HTTP_OK);
    }
    #[Route('/getDailyElo', name: 'app_daily_elo')]
    public function getDailyElo(): JsonResponse
    {
        $listeAccount = $this->riotAccountRepository->findAll();
        $dataToShow = [];
        foreach ($listeAccount as $account) {
            $this->riotApiService->getDailyElo($account);
            $dataToShow[] = $account;
        }
        return new JsonResponse($dataToShow, Response::HTTP_OK,);
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
