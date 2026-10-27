<?php

namespace App\Entity;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanGame\LanGameStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Une partie jouée pendant une LAN (Domain\LanGame\LanGame). */
#[ORM\Entity]
#[ORM\Table(name: 'lan_game')]
// Pas d'index sur players_fingerprint : trop long pour InnoDB, et une LAN ne compte que quelques dizaines de parties.
#[ORM\Index(name: 'idx_lan_game_lookup', columns: ['lan_event_id', 'queue_id'])]
#[ORM\Index(name: 'idx_lan_game_status', columns: ['status'])]
class LanGameRecord
{
    #[ORM\Id]
    #[ORM\Column(length: 80)]
    private string $gameId;

    #[ORM\ManyToOne(targetEntity: LanEvent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LanEvent $lanEvent;

    #[ORM\Column(name: 'queue_id', enumType: LanQueue::class)]
    private LanQueue $queue;

    /** Les 10 Riot ID triés, en minuscules : identifie la partie avec la file et l'heure de début. */
    #[ORM\Column(length: 1024)]
    private string $playersFingerprint;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $estimatedStartAt;

    #[ORM\Column(length: 20, enumType: LanGameStatus::class)]
    private LanGameStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastReportAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $settledScores = null;

    public function __construct(string $gameId, LanEvent $lanEvent, LanQueue $queue, string $playersFingerprint, \DateTimeImmutable $estimatedStartAt)
    {
        $this->gameId = $gameId;
        $this->lanEvent = $lanEvent;
        $this->queue = $queue;
        $this->playersFingerprint = $playersFingerprint;
        $this->estimatedStartAt = $estimatedStartAt;
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }

    public function getLanEvent(): LanEvent
    {
        return $this->lanEvent;
    }

    public function getQueue(): LanQueue
    {
        return $this->queue;
    }

    public function getPlayersFingerprint(): string
    {
        return $this->playersFingerprint;
    }

    public function getEstimatedStartAt(): \DateTimeImmutable
    {
        return $this->estimatedStartAt;
    }

    public function getStatus(): LanGameStatus
    {
        return $this->status;
    }

    public function getLastReportAt(): \DateTimeImmutable
    {
        return $this->lastReportAt;
    }

    public function getSettledScores(): ?string
    {
        return $this->settledScores;
    }

    public function update(LanGameStatus $status, \DateTimeImmutable $lastReportAt, ?string $settledScores): static
    {
        $this->status = $status;
        $this->lastReportAt = $lastReportAt;
        $this->settledScores = $settledScores;

        return $this;
    }
}
