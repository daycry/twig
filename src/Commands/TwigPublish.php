<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Exception;

class TwigPublish extends AbstractTwigCommand
{
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'twig:publish';

    /**
     * the Command's short description
     *
     * @var string
     */
    protected $description = 'Twig config file publisher.';

    /**
     * the Command's options description
     *
     * @var array<string, string>
     */
    protected $options = [
        '-f' => 'Force overwrite ALL existing files in destination.',
    ];

    /**
     * The path to Daycry\Twig\src directory.
     *
     * @var string
     */
    protected $sourcePath;

    // --------------------------------------------------------------------
    /**
     * Copy config file
     */
    public function run(array $params)
    {
        if (! $this->determineSourcePath()) {
            return EXIT_ERROR;
        }
        if (! $this->publishConfig()) {
            return EXIT_ERROR;
        }
        CLI::write('Config file was successfully generated.', 'green');

        return EXIT_SUCCESS;
    }

    // --------------------------------------------------------------------
    /**
     * Determines the current source path from which all other files are located.
     */
    protected function determineSourcePath(): bool
    {
        $this->sourcePath = realpath(__DIR__ . '/../');
        if ($this->sourcePath === '/' || empty($this->sourcePath)) {
            CLI::error('Unable to determine the correct source directory. Bailing.');

            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------
    /**
     * Publish config file.
     */
    protected function publishConfig(): bool
    {
        $path    = "{$this->sourcePath}/Config/Twig.php";
        $content = file_get_contents($path);
        if ($content === false) {
            CLI::error('Unable to read source config: ' . $path);

            return false;
        }
        $content = str_replace('namespace Daycry\Twig\Config', 'namespace Config', $content);
        $content = str_replace('extends BaseConfig', 'extends \\Daycry\\Twig\\Config\\Twig', $content);

        return $this->writeFile('Config/Twig.php', $content);
    }

    // --------------------------------------------------------------------
    /** Write a file, catching any exceptions and showing a nicely formatted error. */
    /**
     * Write a file, catching any exceptions and showing a
     * nicely formatted error.
     *
     * @param string $path Relative file path like 'Config/Twig.php'.
     */
    protected function writeFile(string $path, string $content): bool
    {
        helper('filesystem');

        $config    = new Autoload();
        $appPath   = $config->psr4[APP_NAMESPACE];
        $directory = dirname($appPath . $path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (file_exists($appPath . $path) && CLI::prompt('Config file already exists, do you want to replace it?', ['y', 'n']) === 'n') {
            CLI::error('Cancelled');

            return false;
        }

        try {
            write_file($appPath . $path, $content);
        } catch (Exception $e) {
            $this->showError($e);

            return false;
        }
        $path = str_replace($appPath, '', $path);
        CLI::write(CLI::color('Created: ', 'yellow') . $path);

        return true;
    }
    // --------------------------------------------------------------------
}
