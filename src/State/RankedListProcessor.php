<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Exception\DiscordNotFoundException;
use App\Exception\RiotAccountExistException;
use App\Services\RiotApiServices\RiotApiServices;

class RankedListProcessor implements ProcessorInterface
{


    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
    }

}
