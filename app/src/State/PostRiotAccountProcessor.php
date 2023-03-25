<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Controller\ValidationController;
use App\Exception\DiscordNotFoundException;
use App\Exception\RiotAccountExistException;
use App\Repository\RiotAccountRepository;
use App\Repository\UserRepository;
use App\Services\RiotApiServices\RiotApiServices;
use Exception;

class PostRiotAccountProcessor implements ProcessorInterface
{
    public function __construct(private ProcessorInterface $persistProcessor,private UserRepository $userRepository, private RiotAccountRepository $rioAccountRepository, private RiotApiServices $riotApiServices, private ValidationController $validationController)
    {

    }

    /**
     * @throws Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->validationController->verifyDiscordAccount($uriVariables['discordId']);
        if (!empty($user->getRiotAccount())) {
            throw new DiscordNotFoundException(sprintf('Le compte discord "%s" est deja lié a un utilisateur.', $user->getDiscordId()));
        }
        $data = $this->riotApiServices->riotAccountFill($data);
        $data->setUser($user);
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
