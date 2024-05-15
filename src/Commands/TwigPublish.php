<?php

namespace Daycry\Twig\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload;
use Exception;

class TwigPublish extends BaseCommand
{
    /**
     * The group the command is lumped under
     * when listing commands.
     *
     * @var string
     */
    protected $group = 'Twig';

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
        $this->determineSourcePath();
        $this->publishConfig();
        CLI::write('Config file was successfully generated.', 'green');
    }

    // --------------------------------------------------------------------
    /**
     * Determines the current source path from which all other files are located.
     */
    protected function determineSourcePath()
    {
        $this->sourcePath = realpath(__DIR__ . '/../');
        if ($this->sourcePath === '/' || empty($this->sourcePath)) {
            CLI::error('Unable to determine the correct source directory. Bailing.');

            exit();
        }
    }

    // --------------------------------------------------------------------
    /**
     * Publish config file.
     */
    protected function publishConfig()
    {
        $path    = "{$this->sourcePath}/Config/Twig.php";
        $content = file_get_contents($path);
        $content = str_replace('namespace Daycry\Twig\Config', 'namespace Config', $content);
        $content = str_replace('extends BaseConfig', 'extends \\Daycry\\Twig\\Config\\Twig', $content);
        $this->writeFile('Config/Twig.php', $content);
    }

    // --------------------------------------------------------------------
    /**
     * Write a file, catching any exceptions and showing a nicely formatted error.
     */
    /**
     * Write a file, catching any exceptions and showing a
     * nicely formatted error.
     *
     * @param string $path Relative file path like 'Config/Twig.php'.
     */
    protected function writeFile(string $path, string $content): void
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

            exit();
        }

        try {
            write_file($appPath . $path, $content);
        } catch (Exception $e) {
            $this->showError($e);

            exit();
        }
        $path = str_replace($appPath, '', $path);
        CLI::write(CLI::color('Created: ', 'yellow') . $path);
    }
    // --------------------------------------------------------------------
}
