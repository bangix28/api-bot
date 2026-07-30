<?php

namespace App\Tests\Domain\Logging;

use Psr\Log\AbstractLogger;

/**
 * Double de test PSR-3 : enregistre chaque appel de log pour que les tests
 * puissent vérifier ce qui a été logué (niveau, message, contexte).
 */
class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array}> */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Tous les logs enregistrés, ou seulement ceux d'un niveau donné.
     *
     * @return list<array{level: string, message: string, context: array}>
     */
    public function records(?string $level = null): array
    {
        if ($level === null) {
            return $this->records;
        }

        return array_values(array_filter(
            $this->records,
            fn (array $record) => $record['level'] === $level,
        ));
    }
}
