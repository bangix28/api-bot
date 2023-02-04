<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Exception\DiscordNotFoundException;
use App\Repository\UserRepository;
use Exception;

class RiotAccountProcessor implements ProcessorInterface
{
    public function __construct(private ProcessorInterface $persistProcessor,private UserRepository $userRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->userRepository->findOneBy(array('discordId' => 'string'));
        if (empty($user)) {
            $data->getUser($user->getId());
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }
        throw new DiscordNotFoundException(sprintf('Le compte discord "%s" existe déja.', $user->getDiscordId()));

    }
}
