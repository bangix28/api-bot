<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * LAN créée en administration. Pas de repositoryClass : la lecture applicative
 * passe par le port LanEventRepositoryInterface.
 * Les contraintes Assert sont évaluées par les formulaires EasyAdmin.
 */
#[ORM\Entity]
class LanEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la LAN est obligatoire.')]
    private ?string $name = null;

    // Heures civiles (Europe/Paris) : une LAN commence un samedi à 14h, pas à minuit.
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startsAt', message: 'La LAN doit finir après avoir commencé.')]
    private ?\DateTimeImmutable $endsAt = null;

    /** @var Collection<int, RiotAccount> */
    #[ORM\ManyToMany(targetEntity: RiotAccount::class)]
    #[ORM\JoinTable(name: 'lan_event_participant')]
    #[Assert\Count(min: 1, minMessage: 'Une LAN doit avoir au moins un participant.')]
    private Collection $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    /** @return Collection<int, RiotAccount> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(RiotAccount $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(RiotAccount $participant): static
    {
        $this->participants->removeElement($participant);

        return $this;
    }
}
