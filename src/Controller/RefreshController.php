<?php

namespace App\Controller;

use App\Services\RiotApiServices\RiotApiServices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;


class RefreshController extends AbstractController
{

    public function __construct(RiotApiServices $riotApiService)
    {
        $this->riotApiService = $riotApiService;
    }

    /**
     * @throws \Exception
     */
    #[Route('/refresh', name: 'app_refresh')]
    public function refreshSummoner(): JsonResponse
    {
        $listeAccount = $this->riotApiService->getListRanked();
        return new JsonResponse($listeAccount);
    }
}
