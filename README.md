# Ⓛ Log Viewer

Nette log viewer - Developer tool for viewing and downloading Tracy log files.

[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Latest Stable Version](https://poser.pugx.org/liquiddesign/log-viewer/v/stable)](https://packagist.org/packages/liquiddesign/log-viewer)
[![License](https://poser.pugx.org/liquiddesign/log-viewer/license)](https://packagist.org/packages/liquiddesign/log-viewer)

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
composer require liquiddesign/log-viewer
```

## Usage

### 1. Register Presenter in Router

Add the log viewer route to your router configuration:

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

By default, the log viewer uses `Tracy\Debugger::$logDirectory`. If you need a custom log directory:

```php
use LogViewer\LogViewerPresenter as BaseLogViewerPresenter;

class LogViewerPresenter extends BaseLogViewerPresenter
{
	protected function startup(): void
	{
		parent::startup();

		// Override log directory
		$this->logDir = '/custom/path/to/logs';
	}
}
```

### Access Control

The package automatically restricts access to debug mode only. For additional security:

```php
use LogViewer\LogViewerPresenter as BaseLogViewerPresenter;

class LogViewerPresenter extends BaseLogViewerPresenter
{
	protected function startup(): void
	{
		parent::startup();

		// Add custom access control
		if (!$this->getUser()->isInRole('admin')) {
			throw new Nette\Application\ForbiddenRequestException();
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

Developed by [Liquid Design](https://www.lweb.cz).

## Support

For issues and feature requests, please use [GitHub Issues](https://github.com/liquiddesign/log-viewer/issues).
