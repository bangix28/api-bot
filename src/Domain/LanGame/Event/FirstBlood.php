<?php

namespace App\Domain\LanGame\Event;

final readonly class FirstBlood implements ObservedEvent
{
    public function __construct(public string $recipientName)
    {
    }
}
