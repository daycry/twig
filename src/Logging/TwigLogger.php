<?php

namespace Daycry\Twig\Logging;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Optional PSR-3 logging; standalone instances are silent unless a logger is supplied.
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
    }
}
