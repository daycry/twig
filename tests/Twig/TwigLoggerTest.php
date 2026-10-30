<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\CodeIgniter4\Config\Twig as TwigConfig;
use Daycry\Twig\CodeIgniter4\Twig;
use Tests\Support\TestCase;
use Tests\Support\TestLogger;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
final class TwigLoggerTest extends TestCase
{
    public function testLifecycleEventsReachTheInjectedLogger(): void
    {
        $logger = new TestLogger();
        $twig   = new Twig(new TwigConfig(), $logger);
        $twig->withLoader(new ArrayLoader(['logging.twig' => '{{ log_test_fn() }}', 'filter.twig' => '{{ "lower"|caps }}']));
        $twig->registerFunction('log_test_fn', static fn () => 'logged');
        $this->assertSame('logged', $twig->render('logging'));
        $twig->registerFilter('caps', strtoupper(...));
        $this->assertSame('LOWER', $twig->render('filter'));
        $twig->clearCache();
        $messages = array_column($logger->entries, 'message');
        $this->assertContains('event=twig.function.queued name=log_test_fn', $messages);
        $this->assertContains('event=twig.filter.queued name=caps', $messages);
        $this->assertContains('event=twig.cache.cleared', $messages);
    }
}
