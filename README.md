# Ⓛ Log Viewer

Nette log viewer - Developer tool for viewing and downloading Tracy log files.

[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Latest Stable Version](https://poser.pugx.org/liquiddesign/nette-log-viewer/v/stable)](https://packagist.org/packages/liquiddesign/nette-log-viewer)
[![License](https://poser.pugx.org/liquiddesign/nette-log-viewer/license)](https://packagist.org/packages/liquiddesign/nette-log-viewer)

## Features

- 📁 **Browse log directory structure** - Navigate through folders and files
- 👀 **View log files** - Syntax highlighting for better readability
- 🔍 **Search in files** - Find text with configurable context lines
- 📄 **Pagination** - Browse large directories (100 items per page) and files (100KB chunks)
- 💾 **Download support** - Download log files directly
- 🎨 **HTML dumps** - View Tracy exception dumps in iframe
- 🔐 **Secure** - Only accessible when Tracy debugger is enabled (debug mode)

## Screenshots

### Directory Browser
Browse your log directory with pagination and search functionality.

### File Viewer
View log files with syntax highlighting, pagination, and in-file search.

## Installation

```bash
composer require liquiddesign/nette-log-viewer
```

## Usage

### 1. Register Presenter in Router

#### Option A: Using NEON Configuration (Recommended)

Add routes to your `config/pages.neon` or similar configuration file:

```neon
routing:
	routes:
		'log-viewer/view/<file .+>': LogViewer:LogViewer:view
		'log-viewer/download/<file .+>': LogViewer:LogViewer:download
		'log-viewer[/<path .+>]': LogViewer:LogViewer:default
```

If you extended the presenter in your app namespace (e.g., `App\Web\LogViewerPresenter`):

```neon
routing:
	routes:
		'log-viewer/view/<file .+>': Web:LogViewer:view
		'log-viewer/download/<file .+>': Web:LogViewer:download
		'log-viewer[/<path .+>]': Web:LogViewer:default
```

#### Option B: Using PHP Router

Alternatively, add the route in your RouterFactory:

```php
// app/Router/RouterFactory.php
use LogViewer\LogViewerPresenter;

$router[] = new Route('log-viewer[/<action>][/<path .+>]', LogViewerPresenter::class);
```

### 2. Access Log Viewer

Navigate to:
```
https://your-app.com/log-viewer
```

**Note:** The log viewer is only accessible when Tracy debugger is enabled (debug mode). This is typically controlled by your `config.neon`:

```neon
parameters:
	debugMode: %debugMode%  # or specific IP addresses
```

### 3. Features

#### Directory Browser
- Navigate through your log directory structure
- Search for files and folders by name
- Pagination for directories with many files (100 items per page)
- Sort by type (directories first) and name

#### File Viewer
- View text log files with syntax highlighting
- View HTML Tracy dumps in iframe
- Pagination for large files (100KB chunks)
- Search within files with configurable context (3-300 lines)
- Download any log file

## Configuration

### Custom Log Directory

By default, the log viewer uses `Tracy\Debugger::$logDirectory`. If you need a custom log directory, extend the presenter and override the log directory in `startup()`:

```php
namespace App\Web;

use LogViewer\LogViewerPresenter as BaseLogViewerPresenter;

class LogViewerPresenter extends BaseLogViewerPresenter
{
	protected function startup(): void
	{
		parent::startup();

		// Option 1: Use hardcoded path
		$this->logDir = '/custom/path/to/logs';

		// Option 2: Use container parameters (recommended)
		$this->logDir = $this->container->getParameters()['tempDir'] . '/log';
	}
}
```

Then register your extended presenter in router configuration instead of the base one.

### Access Control

The package automatically restricts access to debug mode only. For additional security, you can extend the presenter and add custom access control:

```php
namespace App\Web;

use LogViewer\LogViewerPresenter as BaseLogViewerPresenter;
use Nette\Application\ForbiddenRequestException;

class LogViewerPresenter extends BaseLogViewerPresenter
{
	protected function startup(): void
	{
		parent::startup();

		// Add custom access control (e.g., admin role required)
		if (!$this->getUser()->isInRole('admin')) {
			throw new ForbiddenRequestException();
		}
	}
}
```

## Requirements

- PHP 8.3 or 8.4
- Nette Application 3.2+
- Nette Utils 4.0+
- Tracy 2.10+

## Development

### Code Quality

```bash
# PHPStan analysis (level 8)
composer phpstan

# Code style check
composer phpcs

# Code style auto-fix
composer phpcsfix
```

### Testing

The package is tested in production environment with Abel e-commerce platform.

## Security

**Important:** This package is a developer tool and should **never** be accessible in production. Always ensure:

1. Tracy debugger is disabled in production
2. Access is restricted by IP address or authentication
3. Log files don't contain sensitive information

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Credits

Developed by [Liquid Design](https://www.lqd.cz).

## Support

For issues and feature requests, please use [GitHub Issues](https://github.com/liquiddesign/nette-log-viewer/issues).
