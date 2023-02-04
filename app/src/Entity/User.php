<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(
    fields: 'discordId',
    message: 'Vous êtes déja inscrit avec cette identifiant Discord !'
)]
#[UniqueEntity(
    fields: 'riotAccount',
    message: 'Vous êtes déja inscrit avec cette identifiant Discord !'
)]
#[ApiResource]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[ApiProperty(
        identifier: true
    )]
    private ?string $discordId = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?RiotAccount $riotAccount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiscordId(): ?string
    {
        return $this->discordId;
    }

    public function setDiscordId(string $discordId): self
    {
        $this->discordId = $discordId;

        return $this;
    }

    public function getRiotAccount(): ?RiotAccount
    {
        return $this->riotAccount;
    }

    public function setRiotAccount(?RiotAccount $riotAccount): self
    {
        // unset the owning side of the relation if necessary
        if ($riotAccount === null && $this->riotAccount !== null) {
            $this->riotAccount->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($riotAccount !== null && $riotAccount->getUser() !== $this) {
            $riotAccount->setUser($this);
        }

        $this->riotAccount = $riotAccount;

        return $this;
    }


}
