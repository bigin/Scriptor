<?php

declare(strict_types=1);

namespace Scriptor\Boot\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * Append-only, single-file PSR-3 logger.
 *
 * The default Scriptor logger. Picks the path + min level from
 * `$config['logging']` and writes one line per record to a flat
 * text file under `data/logs/`. No rotation, no remote sinks; if
 * you need either, swap the container binding for Monolog or your
 * preferred PSR-3 implementation in `boot.php`.
 *
 * Line shape (Monolog-ish, intentional so the file is greppable
 * with the usual tools):
 *
 *     [2026-05-22T14:23:00+02:00] INFO: Contact form submitted from juri@example.com
 *
 * `{placeholder}` tokens in the message are substituted from the
 * `$context` array per PSR-3 §1.2. Throwable values stringify to
 * their `getMessage()`; objects with `__toString()` stringify via
 * that method; arrays / non-stringable objects are left as-is so
 * the caller decides on their own format.
 */
final class FileLogger extends AbstractLogger
{
    /**
     * Numeric rank for each PSR-3 level. Higher = more severe.
     * Records whose level rank is below the configured min are
     * dropped silently. Order matches the PSR-3 spec.
     *
     * @var array<string, int>
     */
    private const LEVEL_RANK = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    ];

    private readonly int $minRank;

    public function __construct(
        private readonly string $path,
        string $minLevel = LogLevel::INFO,
    ) {
        if (! isset(self::LEVEL_RANK[$minLevel])) {
            throw new InvalidArgumentException(
                'Unknown PSR-3 level: "' . $minLevel . '". '
                . 'Use one of: ' . implode(', ', array_keys(self::LEVEL_RANK)),
            );
        }
        $this->minRank = self::LEVEL_RANK[$minLevel];
    }

    /**
     * @param mixed              $level   PSR-3 LogLevel::* constant or matching string.
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $levelKey = \is_string($level) ? $level : (string) $level;
        if (! isset(self::LEVEL_RANK[$levelKey])) {
            throw new InvalidArgumentException('Unknown PSR-3 level: "' . $levelKey . '"');
        }
        if (self::LEVEL_RANK[$levelKey] < $this->minRank) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s\n",
            date('c'),
            strtoupper($levelKey),
            self::interpolate((string) $message, $context),
        );

        // Lazy mkdir so a deleted `data/logs/` doesn't kill logging.
        $dir = \dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // LOCK_EX serialises concurrent FPM workers writing the same
        // file. The @ suppresses the warning when the file is on a
        // read-only mount during a misconfigured deploy; the caller
        // would notice via missing log lines.
        @file_put_contents($this->path, $line, \FILE_APPEND | \LOCK_EX);
    }

    /**
     * PSR-3 §1.2 message interpolation. Replaces `{key}` tokens in
     * the message with the matching `$context[key]` value. Scalars
     * and `Stringable` objects stringify; Throwables stringify to
     * their getMessage(); other values are left untouched so the
     * unreplaced `{key}` reaches the log unmodified (a clear sign
     * the caller passed something we can't render).
     *
     * @param array<string, mixed> $context
     */
    private static function interpolate(string $message, array $context): string
    {
        if ($context === [] || strpos($message, '{') === false) {
            return $message;
        }
        $replace = [];
        foreach ($context as $key => $val) {
            if (! \is_string($key)) {
                continue;
            }
            // Check Throwable BEFORE Stringable: Throwable implements
            // Stringable, but its __toString() returns the full stack
            // trace, which would explode a single log line. Loggers
            // generally want just the message; full trace stays
            // accessible via the `exception` context key per PSR-3
            // §1.3 if a downstream handler wants it.
            if ($val instanceof \Throwable) {
                $replace['{' . $key . '}'] = $val->getMessage();
            } elseif ($val === null || \is_scalar($val) || $val instanceof \Stringable) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }
}
