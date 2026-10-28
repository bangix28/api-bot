<?php

namespace App\Infrastructure\LanGame;

use App\Application\LanGame\ReceiveReport\ReceiveReportCommand;
use App\Domain\Agent\AgentToken;
use App\Domain\LanGame\InvalidReportException;

/**
 * Lit le corps HTTP d'un relevé d'agent (contrat de l'agent, § 3) : le
 * décompresse s'il est en gzip, en borne la taille, valide l'enveloppe et
 * confie `liveClient` au LiveClientSnapshotParser.
 */
final class AgentReportRequestParser
{
    /** Taille maximale du corps décompressé. */
    public const int MAX_BYTES = 1_048_576;
    /** Lu par petits morceaux : une bombe gzip est arrêtée dès qu'elle dépasse la limite. */
    private const int INFLATE_CHUNK_BYTES = 1024;
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const array END_REASONS = ['game_end', 'client_closed'];
    /** ISO 8601 avec fuseau, avec ou sans fractions de seconde. */
    private const array DATE_FORMATS = [\DateTimeInterface::ATOM, 'Y-m-d\\TH:i:s.uP'];

    public static function parse(AgentToken $token, string $body, ?string $contentEncoding): ReceiveReportCommand
    {
        $json = self::decode($body, $contentEncoding);

        try {
            $envelope = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidReportException('Corps JSON illisible : ' . $exception->getMessage(), previous: $exception);
        }
        if (!is_array($envelope)) {
            throw new InvalidReportException('Le relevé doit être un objet JSON.');
        }

        $final = self::field($envelope, 'final', is_bool(...), 'un booléen');
        self::guardEnvelope($envelope, $final);

        return new ReceiveReportCommand(
            $token,
            strtolower($envelope['sessionId']),
            $envelope['sequence'],
            $final,
            LiveClientSnapshotParser::parse(
                self::field($envelope, 'liveClient', is_array(...), 'un objet'),
                // Absent ou null : l'agent n'a pas pu lire la file dans le client LoL.
                self::optionalField($envelope, 'queueId', is_int(...), 'un entier ou null'),
            ),
            $json,
        );
    }

    private static function decode(string $body, ?string $contentEncoding): string
    {
        $encoding = strtolower(trim((string) $contentEncoding));
        if ($encoding === '' || $encoding === 'identity') {
            if (strlen($body) > self::MAX_BYTES) {
                throw new ReportTooLargeException(self::tooLargeMessage());
            }

            return $body;
        }
        if ($encoding !== 'gzip') {
            throw new InvalidReportException(sprintf('Content-Encoding « %s » non pris en charge : gzip ou rien.', $encoding));
        }

        $inflater = inflate_init(ZLIB_ENCODING_GZIP);
        $decoded = '';
        foreach (str_split($body, self::INFLATE_CHUNK_BYTES) as $chunk) {
            $decoded .= self::inflate($inflater, $chunk);
            if (strlen($decoded) > self::MAX_BYTES) {
                throw new ReportTooLargeException(self::tooLargeMessage());
            }
        }

        return $decoded;
    }

    private static function inflate(\InflateContext $inflater, string $chunk): string
    {
        // inflate_add() signale un flux corrompu par un warning : le convertir en refus du relevé.
        set_error_handler(static fn() => true);
        try {
            $inflated = inflate_add($inflater, $chunk);
        } finally {
            restore_error_handler();
        }
        if ($inflated === false) {
            throw new InvalidReportException('Corps gzip corrompu.');
        }

        return $inflated;
    }

    /** @param array<mixed> $envelope */
    private static function guardEnvelope(array $envelope, bool $final): void
    {
        self::field($envelope, 'agentVersion', static fn(mixed $value) => is_string($value) && $value !== '', 'une version');
        self::field($envelope, 'sessionId', static fn(mixed $value) => is_string($value) && preg_match(self::UUID_PATTERN, $value) === 1, 'un UUID');
        self::field($envelope, 'sequence', static fn(mixed $value) => is_int($value) && $value >= 1, 'un entier ≥ 1');
        self::field($envelope, 'observedAt', self::isDateTime(...), 'une date ISO 8601');

        $endReason = $envelope['endReason'] ?? null;
        if ($final && !in_array($endReason, self::END_REASONS, true)) {
            throw new InvalidReportException('Un relevé final doit donner endReason : game_end ou client_closed.');
        }
        if (!$final && $endReason !== null) {
            throw new InvalidReportException('endReason doit être null pour un relevé intermédiaire.');
        }
    }

    /**
     * @param array<mixed> $envelope
     * @param callable(mixed): bool $isValid
     */
    private static function field(array $envelope, string $name, callable $isValid, string $expected): mixed
    {
        $value = $envelope[$name] ?? null;
        if (!$isValid($value)) {
            throw new InvalidReportException(sprintf('Champ « %s » absent ou invalide : %s attendu.', $name, $expected));
        }

        return $value;
    }

    /**
     * @param array<mixed> $envelope
     * @param callable(mixed): bool $isValid
     */
    private static function optionalField(array $envelope, string $name, callable $isValid, string $expected): mixed
    {
        $value = $envelope[$name] ?? null;

        return $value === null ? null : self::field($envelope, $name, $isValid, $expected);
    }

    private static function isDateTime(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        foreach (self::DATE_FORMATS as $format) {
            if (\DateTimeImmutable::createFromFormat($format, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function tooLargeMessage(): string
    {
        return sprintf('Relevé de plus de %d octets une fois décompressé.', self::MAX_BYTES);
    }
}
