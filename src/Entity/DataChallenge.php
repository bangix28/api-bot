<?php

namespace App\Entity;

use App\Repository\DataChallengeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataChallengeRepository::class)]
class DataChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $queueType = null;

    #[ORM\Column(length: 255)]
    private ?string $challenge = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueueType(): ?int
    {
        return $this->queueType;
    }

    public function setQueueType(int $queueType): static
    {
        $this->queueType = $queueType;

        return $this;
    }

    public function getChallenge(): ?string
    {
        return $this->challenge;
    }

    public function setChallenge(string $challenge): static
    {
        $this->challenge = $challenge;

        return $this;
    }
}
