<?php

namespace Tests\Support;

use Psr\Log\AbstractLogger;

final class TestLogger extends AbstractLogger
{
    public array $entries = [];

    public function log($level, $message, array $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
