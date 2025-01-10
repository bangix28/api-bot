<?php

namespace App\Controller;

use App\Repository\RiotAccountRepository;
use App\Services\RiotApiServices\HistoryAccountLolServices;
use App\Services\RiotApiServices\RiotApiServices;
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
}
