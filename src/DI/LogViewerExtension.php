<?php

declare(strict_types=1);

namespace LogViewer\DI;

use Nette\Application\IPresenterFactory;
use Nette\Application\Routers\RouteList;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Routing\Router;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Registers Log Viewer UI + JSON API routes and the LogViewer presenter mapping.
 *
 * Usage in host application config:
 *
 *   extensions:
 *       logViewer: LogViewer\DI\LogViewerExtension
 *
 * Optional config:
 *
 *   logViewer:
 *       urlPrefix: log-viewer # URL prefix (no leading slash)
 *       presenter: LogViewer:LogViewer # UI presenter, e.g. Web:LogViewer for app-level subclass
 *       apiPresenter: LogViewer:LogViewerApi # JSON API presenter
 *       registerRoutes: true # set false to manage routes manually
 *       registerPresenterMapping: true # set false if the host uses its own mapping
 */
final class LogViewerExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'urlPrefix' => Expect::string('log-viewer'),
			'presenter' => Expect::string('LogViewer:LogViewer'),
			'apiPresenter' => Expect::string('LogViewer:LogViewerApi'),
			'registerRoutes' => Expect::bool(true),
			'registerPresenterMapping' => Expect::bool(true),
		])->castTo('array');
	}

	public function loadConfiguration(): void
	{
		/** @var array{urlPrefix: string, presenter: string, apiPresenter: string, registerRoutes: bool, registerPresenterMapping: bool} $config */
		$config = $this->config;

		if (!$config['registerRoutes']) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$prefix = \rtrim($config['urlPrefix'], '/');
		$uiPresenter = $config['presenter'];
		$apiPresenter = $config['apiPresenter'];

		$builder->addDefinition($this->prefix('routes'))
			->setType(RouteList::class)
			->setFactory(RouteList::class)
			->addSetup('addRoute', ["{$prefix}/api/<action>", "{$apiPresenter}:default"])
			->addSetup('addRoute', ["{$prefix}/view/<file .+>", "{$uiPresenter}:view"])
			->addSetup('addRoute', ["{$prefix}/download/<file .+>", "{$uiPresenter}:download"])
			->addSetup('addRoute', ["{$prefix}[/<path .+>]", "{$uiPresenter}:default"])
			->setAutowired(false);
	}

	public function beforeCompile(): void
	{
		/** @var array{urlPrefix: string, presenter: string, apiPresenter: string, registerRoutes: bool, registerPresenterMapping: bool} $config */
		$config = $this->config;
		$builder = $this->getContainerBuilder();

		if ($config['registerPresenterMapping']) {
			$factory = $builder->getDefinitionByType(IPresenterFactory::class);
			\assert($factory instanceof ServiceDefinition);
			$factory->addSetup('setMapping', [['LogViewer' => 'LogViewer\\*Presenter']]);
		}

		if (!$config['registerRoutes']) {
			return;
		}

		$router = $builder->getDefinitionByType(Router::class);
		\assert($router instanceof ServiceDefinition);
		$router->addSetup('prepend', ['@' . $this->prefix('routes')]);
	}
}
