<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Controller\ValidationController;
use App\Exception\DiscordNotFoundException;
use App\Repository\RiotAccountRepository;
use App\Repository\UserRepository;
use App\Services\RiotApiServices\RiotApiServices;

class PutRiotAccountProcessor implements ProcessorInterface
{
    public function __construct(private ProcessorInterface $persistProcessor,private UserRepository $userRepository, private RiotAccountRepository $rioAccountRepository, private RiotApiServices $riotApiServices, private ValidationController $validationController)
    {

    }
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->validationController->verifyDiscordAccount($uriVariables['discordId']);
        $riotAccountData = $user->getRiotAccount();
        if (!$riotAccountData){
            throw new DiscordNotFoundException(sprintf('Aucune entrée De compte LOL, contacte Kénolane', $user->getDiscordId()));
        }
        $data = $this->riotApiServices->riotAccountFill($data,$data->getSummonerName());
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
