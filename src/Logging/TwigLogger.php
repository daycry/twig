<?php

namespace Daycry\Twig\Logging;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Thin bridge that prefers an injected PSR-3 logger but falls back to
 * CodeIgniter's global `log_message()` helper. Lets users wire monolog/syslog
 * without forcing a hard dependency on PSR-3 across the rest of the codebase.
 *
 * Usage:
 *   $bridge = new TwigLogger($psr3); // or new TwigLogger() for log_message fallback
 *   $bridge->info('event=twig.warmup.done count=42');
 */
final class TwigLogger
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        $msg = (string) $message;
        if ($this->logger !== null) {
            $this->logger->log($level, $msg, $context);

            return;
        }
        if (function_exists('log_message')) {
            log_message($level, $context === [] ? $msg : $msg . ' ' . self::formatContext($context));
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function formatContext(array $context): string
    {
        $parts = [];

        foreach ($context as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $parts[] = $k . '=' . (is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? ''));
            }
        }

        return implode(' ', $parts);
    }
}
