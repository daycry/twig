<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Twig\Config\Services;
use Daycry\Twig\Twig;

class TwigWarmup extends BaseCommand
{
    protected $group       = 'Twig';
    protected $name        = 'twig:warmup';
    protected $description = 'Precompiles (warms) Twig templates to reduce first-request latency.';
    protected $usage       = 'twig:warmup [template1 template2 ...] [--all] [--force] [--json] [--verbose]';
    protected $arguments   = [
        'templates' => 'Optional list of logical template names (without extension). If omitted, use --all to warm every discovered template.',
    ];
    protected $options = [
        '--all'     => 'Warm all templates discovered in configured paths.',
        '--force'   => 'Force recompilation even if a compiled cache file seems present.',
        '--verbose' => 'Show discovered paths & template list before warming.',
        '--json'    => 'Emit machine-readable JSON result (CI/CD integration).',
    ];

    public function run(array $params)
    {
        $force   = in_array('--force', $params, true)   || CLI::getOption('force');
        $all     = in_array('--all', $params, true)     || CLI::getOption('all');
        $verbose = in_array('--verbose', $params, true) || CLI::getOption('verbose');
        $asJson  = in_array('--json', $params, true)    || CLI::getOption('json');

        /** @var Twig $twig */
        $twig = Services::twig();

        if ($all) {
            // Discover first (listTemplates triggers discovery & cache)
            $templates = $twig->listTemplates();
            if ($verbose) {
                CLI::write('Discovered template count: ' . count($templates), 'yellow');
                if ($templates) {
                    foreach ($templates as $t) {
                        CLI::write(' - ' . $t);
                    }
                }
            }
            if (empty($templates)) {
                CLI::write('No templates discovered. Check configured paths or extension.', 'red');
            }
            if ($verbose) {
                putenv('TWIG_WARMUP_VERBOSE=1');
            }
            $result         = $twig->warmupAll($force);
            $result['mode'] = 'all';
            if ($asJson) {
                CLI::write(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            } else {
                CLI::write("Warmup (all): compiled={$result['compiled']} skipped={$result['skipped']} errors={$result['errors']}", 'green');
                if ($verbose && isset($result['error_details'])) {
                    CLI::write('Errors:', 'red');

                    foreach ($result['error_details'] as $err) {
                        CLI::write(' - ' . $err['template'] . ': ' . $err['error'], 'red');
                    }
                }
            }
            // Dispatch post-warmup event/hook
            if (function_exists('event')) {
                @event('twig:warmup:after', $result);
            }

            return;
        }

        // Remove option flags from params to leave only template names
        $templates = array_values(array_filter($params, static fn ($p) => ! str_starts_with($p, '--')));
        if (empty($templates)) {
            CLI::error('Provide template names or use --all');

            return;
        }
        if ($verbose) {
            CLI::write('Templates to warm: ' . implode(', ', $templates), 'yellow');
        }
        if ($verbose) {
            putenv('TWIG_WARMUP_VERBOSE=1');
        }
        $result              = $twig->warmup($templates, $force);
        $result['mode']      = 'subset';
        $result['templates'] = $templates;
        if ($asJson) {
            CLI::write(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            CLI::write("Warmup: compiled={$result['compiled']} skipped={$result['skipped']} errors={$result['errors']}", 'green');
            if ($verbose && isset($result['error_details'])) {
                CLI::write('Errors:', 'red');

                foreach ($result['error_details'] as $err) {
                    CLI::write(' - ' . $err['template'] . ': ' . $err['error'], 'red');
                }
            }
        }
        if (function_exists('event')) {
            @event('twig:warmup:after', $result);
        }
    }
}
