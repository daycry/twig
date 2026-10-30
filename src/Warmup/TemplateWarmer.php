<?php

namespace Daycry\Twig\Warmup;

use Daycry\Twig\Contracts\CacheManagerInterface;
use Daycry\Twig\Logging\TwigLogger;
use Daycry\Twig\Persistence\StateRepository;
use Daycry\Twig\Runtime\Environment as ManagedEnvironment;
use Daycry\Twig\Support\TemplateNameValidator;
use Throwable;
use Twig\Environment;

final readonly class TemplateWarmer
{
    public function __construct(
        private CacheManagerInterface $manager,
        private StateRepository $state,
        private TwigLogger $log,
    ) {
    }

    public function run(Environment $environment, array $templates, string $extension, bool $force): array
    {
        $compiled = $skipped = $errors = 0;
        $details  = [];

        try {
            $sources = $this->state->read('compile.sources') ?? [];
        } catch (Throwable) {
            $sources = [];
        }
        $cache = $environment->getCache(false);

        foreach (array_unique($templates) as $logical) {
            $logical = trim($logical);
            if ($logical === '') {
                continue;
            }

            try {
                TemplateNameValidator::assertValid($logical);
                $name        = $logical . $extension;
                $source      = $environment->getLoader()->getSourceContext($name);
                $class       = $environment instanceof ManagedEnvironment ? $environment->getPersistentTemplateClass($name) : $environment->getTemplateClass($name);
                $fingerprint = hash('sha256', $class . $source->getCode());
                $key         = $cache->generateKey($name, $class);
                $timestamp   = $cache->getTimestamp($key);
                // Source fingerprints disambiguate edits within filesystem mtime resolution.
                $fresh = $timestamp > 0 && ($environment->isTemplateFresh($name, $timestamp)
                    || (($sources[$logical] ?? null) === $fingerprint && $environment->isTemplateFresh($name, $timestamp + 1)));
                if (! $force && $fresh) {
                    $this->manager->markCompiled($logical);
                    $skipped++;

                    continue;
                }
                $content = $environment instanceof ManagedEnvironment ? $environment->compilePersistentSource($source) : $environment->compileSource($source);
                $cache->write($key, $content);
                clearstatcache(true, $key);
                $sources[$logical] = $fingerprint;
                $this->manager->markCompiled($logical);
                $compiled++;
            } catch (Throwable $e) {
                $errors++;
                $details[] = ['template' => $logical, 'error' => $e->getMessage()];
                $this->log->error('event=twig.warmup.error', ['template' => $logical, 'message' => $e->getMessage()]);
            }
        }
        if ($compiled > 0) {
            try {
                $this->state->write('compile.sources', $sources);
            } catch (Throwable $e) {
                $this->log->debug('event=twig.warmup.sources.write_failed', ['message' => $e->getMessage()]);
            }
        }
        $result = ['compiled' => $compiled, 'skipped' => $skipped, 'errors' => $errors];
        if ($details !== [] && getenv('TWIG_WARMUP_VERBOSE')) {
            $result['error_details'] = $details;
        }

        return $result;
    }
}
