<?php

declare(strict_types=1);

namespace LogViewer\DI;

use Nette\Application\IPresenterFactory;
use Nette\Application\Routers\Route;
use Nette\DI\CompilerExtension;

/**
 * Registers the LogViewer presenter mapping and exposes a route factory.
 *
 * Nette 2.4 note: the 2.4 RouteList has no addRoute()/prepend(), so routes
 * cannot be injected into the host router from the DI extension. Register them
 * manually in your RouterFactory by prepending LogViewerExtension::createRoutes()
 * before your catch-all route.
 *
 * Usage in host application config:
 *
 *   extensions:
 *       logViewer: LogViewer\DI\LogViewerExtension
 *
 * Optional config:
 *
 *   logViewer:
 *       registerPresenterMapping: true # set false if the host uses its own mapping
 *
 * Usage in RouterFactory:
 *
 *   foreach (LogViewerExtension::createRoutes() as $route) {
 *       $router[] = $route;
 *   }
 */
final class LogViewerExtension extends CompilerExtension
{
	/** @var array<string, bool> */
	public array $defaults = [
		'registerPresenterMapping' => true,
	];

	public function beforeCompile(): void
	{
		/** @var array{registerPresenterMapping: bool} $config */
		$config = $this->validateConfig($this->defaults);

		if (!$config['registerPresenterMapping']) {
			return;
		}

		$builder = $this->getContainerBuilder();
		$factory = $builder->getDefinitionByType(IPresenterFactory::class);
		$factory->addSetup('setMapping', [['LogViewer' => 'LogViewer\\*Presenter']]);
	}

	/**
	 * Build the LogViewer routes. Prepend them in the host RouterFactory before
	 * the catch-all route so they are matched first.
	 * @return array<int, \Nette\Application\Routers\Route>
	 */
	public static function createRoutes(string $urlPrefix = 'log-viewer', string $uiPresenter = 'LogViewer:LogViewer', string $apiPresenter = 'LogViewer:LogViewerApi'): array
	{
		$prefix = \rtrim($urlPrefix, '/');

		return [
			new Route("{$prefix}/api/<action>", "{$apiPresenter}:default"),
			new Route("{$prefix}/view/<file .+>", "{$uiPresenter}:view"),
			new Route("{$prefix}/download/<file .+>", "{$uiPresenter}:download"),
			new Route("{$prefix}[/<path .+>]", "{$uiPresenter}:default"),
		];
	}
}
