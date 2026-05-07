<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use Throwable;
use Twig\Error\SyntaxError;
use Twig\Source;

/**
 * `php spark twig:lint [template?]` — validates Twig syntax without rendering.
 *
 * Useful in CI to fail builds on broken templates before deployment. When no
 * template name is supplied, every discovered template is linted and the
 * command exits non-zero if any template has a syntax error.
 */
class TwigLint extends AbstractTwigCommand
{
    protected $name        = 'twig:lint';
    protected $description = 'Validate Twig template syntax without rendering.';
    protected $usage       = 'twig:lint [template] [--json]';
    protected $arguments   = [
        'template' => 'Optional logical template name (without extension). If omitted, every discovered template is linted.',
    ];
    protected $options = [
        '--json' => 'Emit JSON result (CI/CD friendly).',
    ];

    public function run(array $params)
    {
        $asJson   = $this->flag('json', $params);
        $args     = $this->positional($params);
        $explicit = $args[0] ?? null;

        $facade = $this->twig();
        if ($facade === null) {
            return EXIT_ERROR;
        }
        $env = $facade->getTwig();

        $targets = $explicit !== null && $explicit !== ''
            ? [$explicit]
            : $facade->listTemplates();

        if ($targets === []) {
            $msg = 'No templates to lint.';
            if ($asJson) {
                return $this->writeJson(true, ['ok_count' => 0, 'error_count' => 0, 'errors' => []]);
            }
            CLI::write($msg, 'yellow');

            return EXIT_SUCCESS;
        }

        $extension = '.twig';
        $errors    = [];
        $okCount   = 0;

        foreach ($targets as $logical) {
            $logical = (string) $logical;
            $name    = str_ends_with($logical, $extension) ? $logical : $logical . $extension;

            try {
                // Use the loader to obtain source then tokenize+parse — this catches syntax
                // errors without producing compiled output.
                $source = $env->getLoader()->getSourceContext($name);
                $env->parse($env->tokenize(new Source($source->getCode(), $source->getName(), $source->getPath())));
                $okCount++;
            } catch (SyntaxError $e) {
                $errors[] = [
                    'template' => $logical,
                    'line'     => $e->getTemplateLine(),
                    'message'  => $e->getRawMessage(),
                ];
            } catch (Throwable $e) {
                $errors[] = [
                    'template' => $logical,
                    'line'     => null,
                    'message'  => $e->getMessage(),
                ];
            }
        }

        if ($asJson) {
            return $this->writeJson(
                $errors === [],
                [
                    'ok_count'    => $okCount,
                    'error_count' => count($errors),
                    'errors'      => $errors,
                ],
            );
        }

        if ($errors === []) {
            CLI::write(sprintf('All %d template(s) OK.', $okCount), 'green');

            return EXIT_SUCCESS;
        }
        CLI::write(sprintf('%d template(s) OK, %d with errors:', $okCount, count($errors)), 'red');

        foreach ($errors as $err) {
            $line = $err['line'] !== null ? '(line ' . $err['line'] . ')' : '';
            CLI::write(sprintf('  - %s %s: %s', $err['template'], $line, $err['message']), 'red');
        }

        return EXIT_ERROR;
    }
}
