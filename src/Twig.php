<?php

namespace Daycry\Twig;

use CodeIgniter\Filters\DebugToolbar;
use Config\Services;
use Config\Toolbar;
use Daycry\Twig\Config\Twig as TwigConfig;
use Daycry\Twig\Debug\Toolbar\Collectors\Twig as CollectorsTwig;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class General
 */
class Twig
{
    /**
     * Saved Data.
     */
    protected array $data = [];

    protected string $extension = '.twig';

    /**
     * @var array Paths to Twig templates
     */
    private array $paths = [APPPATH . 'Views'];

    /**
     * @var array Functions to add to Twig
     */
    private array $functions_asis = ['base_url', 'site_url'];

    /**
     * @var array Functions with `is_safe` option
     *
     * @see http://twig.sensiolabs.org/doc/advanced.html#automatic-escaping
     */
    private array $functions_safe = [
        'form_open', 'form_close', 'form_error', 'form_hidden', 'set_value',
    ];

    /**
     * @var array<string,array<mixed,string>|string> filters
     */
    private array $filters = [];

    /**
     * @var array Twig Environment Options
     *
     * @see http://twig.sensiolabs.org/doc/api.html#environment-options
     */
    private array $config = [];

    /**
     * https://twig.symfony.com/doc/3.x/advanced.html
     */
    private array $extensions = [];

    /**
     * @var bool Whether functions are added or not
     */
    private bool $functions_added = false;

    private ?Environment $twig = null;

    /**
     * @class \Twig\Loader\FilesystemLoader
     */
    private ?LoaderInterface $loader = null;

    protected array $performanceData = [];
    protected bool $debug            = false;
    protected bool $saveData         = true;
    protected ?array $tempData       = null;
    protected int $viewsCount        = 0;

    public function __construct(?TwigConfig $config = null)
    {
        $this->initialize($config);
    }

    public function initialize(?TwigConfig $config = null): Twig
    {
        if (empty($config)) {
            /** @var TwigConfig $config */
            $config = config('Twig');
        }

        $this->debug = (ENVIRONMENT !== 'production') ? true : false;

        $this->extensions = $this->unique_matrix($config->extensions);

        if (isset($config->extension) && $config->extension !== '') {
            $this->extension = $config->extension;
        }

        if (isset($config->functions_asis)) {
            $this->functions_asis = $this->unique_matrix(array_merge($this->functions_asis, $config->functions_asis));
        }

        if (isset($config->functions_safe)) {
            $this->functions_safe = $this->unique_matrix(array_merge($this->functions_safe, $config->functions_safe));
        }

        if (isset($config->paths)) {
            $this->paths = $this->unique_matrix(array_merge($this->paths, $config->paths));
        }

        $this->filters = $this->unique_matrix(array_merge($this->filters, $config->filters));

        // default Twig config
        $this->config = [
            'cache'      => WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'twig',
            'debug'      => $this->debug,
            'autoescape' => 'html',
        ];

        if (isset($config->saveData)) {
            $this->saveData = $config->saveData;
        }

        return $this;
    }

    public function resetTwig(): void
    {
        $this->twig = null;
        $this->createTwig();
    }

    /**
     * @param string $uri
     * @param string $title
     * @param array  $attributes [changed] only array is acceptable
     */
    public function safe_anchor($uri = '', $title = '', $attributes = []): string
    {
        $uri   = esc($uri, 'url');
        $title = esc($title);

        $new_attr = [];

        foreach ($attributes as $key => $val) {
            $new_attr[esc($key)] = $val;
        }

        return anchor($uri, $title, $new_attr);
    }

    /**
     * @codeCoverageIgnore
     */
    public function validation_list_errors(): string
    {
        return Services::validation()->listErrors();
    }

    public function getTwig(): Environment
    {
        $this->createTwig();

        return $this->twig;
    }

    /**
     * @return array
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Renders Twig Template and Set Output
     *
     * @param string $view   Template filename without `.twig`
     * @param array  $params Array of parameters to pass to the template
     */
    public function display(string $view, array $params = [])
    {
        echo $this->render($view, $params);
    }

    /**
     * Renders Twig Template and Returns as String
     *
     * @param string $view   Template filename without `.twig`
     * @param array  $params Array of parameters to pass to the template
     */
    public function render(string $view, array $params = []): string
    {
        $start = microtime(true);
        $data  = esc($params, 'raw');
        $this->tempData ??= $this->data;
        $this->tempData = array_merge($this->tempData, $data);

        // Make our view data available to the view.
        $this->prepareTemplateData($this->saveData);

        $this->createTwig();
        // We call addFunctions() here, because we must call addFunctions()
        // after loading CodeIgniter functions in a controller.
        $this->addFunctions();

        $view .= $this->extension;

        $output = $this->twig->render($view, $params);

		// Check if DebugToolbar is enabled.
		$filters              = service('filters');
		$requiredAfterFilters = $filters->getRequiredFilters('after')[0];

		if (in_array('toolbar', $requiredAfterFilters, true)) {
			$debugBarEnabled = true;
		} else {
			$afterFilters    = $filters->getFiltersClass()['after'];
			$debugBarEnabled = in_array(DebugToolbar::class, $afterFilters, true);
		}

		if ($this->debug && $debugBarEnabled) {
			$this->logPerformance($start, microtime(true), $view);

			$toolbarCollectors = config(Toolbar::class)->collectors;

			if (in_array(CollectorsTwig::class, $toolbarCollectors, true)) {
				$output = '<!-- DEBUG-VIEW START ' . $view . ' -->' . PHP_EOL
					. $output . PHP_EOL
					. '<!-- DEBUG-VIEW ENDED ' . $view . ' -->' . PHP_EOL;
			}
		}

        $this->tempData = null;

        return $output;
    }

    public function createTemplate(string $template, array $params = [], bool $display = false)
    {
        $this->createTwig();
        // We call addFunctions() here, because we must call addFunctions()
        // after loading CodeIgniter functions in a controller.
        $this->addFunctions();

        $template = $this->twig->createTemplate($template);

        if (! $display) {
            return $template->render($params);
        }

        echo $template->render($params);
    }

    /**
     * Returns the performance data that might have been collected
     * during the execution. Used primarily in the Debug Toolbar.
     */
    public function getPerformanceData(): array
    {
        return $this->performanceData;
    }

    /**
     * Returns the current data that will be displayed in the view.
     */
    public function getData(): array
    {
        return $this->tempData ?? $this->data;
    }

    protected function createTwig(): void
    {
        // $this->twig is singleton
        if ($this->twig !== null) {
            return;
        }

        if ($this->loader === null) {
            $this->loader = new FilesystemLoader();

            foreach ($this->paths as $path) {
                if (is_array($path)) {
                    $this->loader->addPath($path[0], $path[1]);

                    continue;
                }
                $this->loader->addPath($path);
            }
        }

        $twig = new Environment($this->loader, $this->config);

        if ($this->debug) {
            $twig->addExtension(new DebugExtension());
        }

        foreach($this->extensions as $extension) {
            $twig->addExtension(new $extension());
        }
        $this->twig = $twig;
    }

    protected function setLoader($loader)
    {
        $this->loader = $loader;
    }

    /**
     * Registers a Global
     *
     * @param string $name  The global name
     * @param mixed  $value The global value
     */
    public function addGlobal($name, $value): void
    {
        $this->createTwig();
        $this->twig->addGlobal($name, $value);
    }

    protected function addFunctions(): void
    {
        // Runs only once
        if ($this->functions_added) {
            return;
        }

        // as is functions
        foreach ($this->functions_asis as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function));
            }
        }

        // safe functions
        foreach ($this->functions_safe as $function) {
            if (function_exists($function)) {
                $this->twig->addFunction(new TwigFunction($function, $function, ['is_safe' => ['html']]));
            }
        }

        // filters
        foreach ($this->filters as $name => $filter) {
            $this->twig->addFilter(new TwigFilter($name, $filter, ['is_variadic' => true, 'is_safe' => ['html']]));
        }

        // customized functions
        if (function_exists('anchor')) {
            $this->twig->addFunction(new TwigFunction('anchor', [$this, 'safe_anchor'], ['is_safe' => ['html']]));
        }

        $this->twig->addFunction(new TwigFunction('validation_list_errors', [$this, 'validation_list_errors'], ['is_safe' => ['html']]));

        $this->functions_added = true;
    }

    /**
     * Logs performance data for rendering a view.
     */
    protected function logPerformance(float $start, float $end, string $view): void
    {
        $this->performanceData[] = [
            'start' => $start,
            'end'   => $end,
            'view'  => $view,
        ];
    }

    protected function prepareTemplateData(bool $saveData): void
    {
        $this->tempData ??= $this->data;

        if ($saveData) {
            $this->data = $this->tempData;
        }
    }

    private function unique_matrix($matrix): array
    {
        $matrixAux = $matrix;

        foreach ($matrix as $key => $subMatrix) {
            unset($matrixAux[$key]);

            foreach ($matrixAux as $subMatrixAux) {
                if ($subMatrix === $subMatrixAux) {
                    // Or this
                    // if($subMatrix[0] === $subMatrixAux[0]) {
                    unset($matrix[$key]);
                }
            }
        }

        return $matrix;
    }
}
