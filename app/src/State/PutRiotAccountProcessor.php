<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Controller\ValidationController;
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
        dump($user);
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
