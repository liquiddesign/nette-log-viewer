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
- 🔌 **JSON REST API** - Programmatic access for external tools (Claude, scripts, monitoring)
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

## Migrating from 1.1 to 1.2

Version 1.2 adds `LogViewer\DI\LogViewerExtension`, which makes route registration a one-liner. **Existing 1.1 setups keep working unchanged** — the extension is opt-in. To migrate:

1. **Bump the dependency** in your host application:

   ```bash
   composer update liquiddesign/nette-log-viewer
   ```

2. **Register the extension** in `config/common.neon` (or equivalent):

   ```neon
   extensions:
       logViewer: LogViewer\DI\LogViewerExtension
   ```

   If you use subclassed presenters (e.g. `App\Web\LogViewerPresenter` extending `LogViewer\LogViewerPresenter` for custom IP auth), point the extension at them:

   ```neon
   logViewer:
       presenter: Web:LogViewer
       apiPresenter: Web:LogViewerApi
       registerPresenterMapping: false   # your app already maps Web:* → App\Web\*Presenter
   ```

3. **Remove the manual `log-viewer/*` routes** from `pages.neon` / `routing:` config:

   ```diff
    routing:
        routes:
   -        'log-viewer/api/<action>': Web:LogViewerApi:default
   -        'log-viewer/view/<file .+>': Web:LogViewer:view
   -        'log-viewer/download/<file .+>': Web:LogViewer:download
   -        'log-viewer[/<path .+>]': Web:LogViewer:default
   ```

   The extension prepends these routes onto the main router automatically (specific routes before the wildcard, so `/log-viewer/api/list` always reaches the API presenter).

4. **Clear the Nette DI cache** so the new container picks up the extension:

   ```bash
   composer clear-nette-cache  # or rm -rf temp/cache/Nette.Configurator
   ```

5. **Verify** that `GET /log-viewer` (UI) and `GET /log-viewer/api/list` (JSON) both respond as before.

If you would rather not use the extension, set `registerRoutes: false` and `registerPresenterMapping: false` (or simply don't register the extension at all) — manual routing from 1.1 keeps working as documented under [Option B](#option-b-manual-neon-routes).

## Usage

### 1. Register Presenter in Router

#### Option A: Using DI Extension (Recommended)

Register the bundled `LogViewerExtension` in your `config/common.neon`:

```neon
extensions:
	logViewer: LogViewer\DI\LogViewerExtension
```

This registers the four routes (UI + JSON API) at the **front** of the router (so the `log-viewer/api/<action>` specific route always matches before the `log-viewer[/<path .+>]` wildcard) and adds the presenter mapping `LogViewer:* → LogViewer\*Presenter`. No additional `pages.neon` / `routing:` entries needed.

If you subclass the presenters in your own namespace (see [Access Control](#access-control) below), point the extension at them:

```neon
logViewer:
	presenter: Web:LogViewer
	apiPresenter: Web:LogViewerApi
	registerPresenterMapping: false   # your app already maps Web:* → App\Web\*Presenter
```

All options (with defaults):

```neon
logViewer:
	urlPrefix: log-viewer                # URL prefix (no leading slash)
	presenter: LogViewer:LogViewer       # UI presenter
	apiPresenter: LogViewer:LogViewerApi # JSON API presenter
	registerRoutes: true                 # set false to manage routes manually
	registerPresenterMapping: true       # set false if the host uses its own mapping
```

#### Option B: Manual NEON Routes

If you prefer to manage routing yourself, add the routes to `config/pages.neon` (or your routing config):

```neon
routing:
	routes:
		'log-viewer/api/<action>': LogViewer:LogViewerApi:default
		'log-viewer/view/<file .+>': LogViewer:LogViewer:view
		'log-viewer/download/<file .+>': LogViewer:LogViewer:download
		'log-viewer[/<path .+>]': LogViewer:LogViewer:default
```

With an extended presenter in your app namespace (e.g., `App\Web\LogViewerPresenter` / `App\Web\LogViewerApiPresenter`):

```neon
routing:
	routes:
		'log-viewer/api/<action>': Web:LogViewerApi:default
		'log-viewer/view/<file .+>': Web:LogViewer:view
		'log-viewer/download/<file .+>': Web:LogViewer:download
		'log-viewer[/<path .+>]': Web:LogViewer:default
```

When using the `LogViewer:*` presenter names with manual routes, you also need a presenter mapping in `common.neon`:

```neon
application:
	mapping:
		LogViewer: LogViewer\*Presenter
```

#### Option C: Using PHP Router

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

## JSON REST API

The package exposes a JSON REST API alongside the HTML UI so that external tools — scripts, monitoring, AI assistants like Claude — can read logs programmatically. The API obeys the same security model as the UI: it is **only available when Tracy debugger is enabled** (`Debugger::isEnabled()` returns true). Outside of debug mode every endpoint returns `403`.

### Endpoints

All endpoints accept `GET` and live under `log-viewer/api/` (when registered using the recommended NEON routing above).

| Endpoint | Parameters | Response |
|---|---|---|
| `GET /log-viewer/api/list` | `path?`, `page=1`, `search?`, `itemsPerPage=100` | JSON: `{path, items[], page, totalPages, totalItems, itemsPerPage, search}` |
| `GET /log-viewer/api/stat` | `file` | JSON: `{file, size, lastModified, extension, type, isHtml, totalPages, chunkSize}` |
| `GET /log-viewer/api/view` | `file`, `page=1` | JSON: `{file, page, totalPages, chunkSize, fileSize, lastModified, isHtml, displayedSize, content}` |
| `GET /log-viewer/api/search` | `file`, `q`, `context=5`, `direction=both\|before\|after` | JSON: `{file, query, context, direction, found, lineNumber, content}` |
| `GET /log-viewer/api/download` | `file` | Raw file (binary `application/octet-stream`) |

`itemsPerPage` is clamped to 1–1000, `context` to 1–300, `chunkSize` is fixed at 100 KB.

### Error responses

All errors are JSON with HTTP status set accordingly:

```json
{ "error": "Invalid file path", "code": 400 }
```

| Status | Meaning |
|---|---|
| `400` | Invalid path / missing parameter / unsupported file type |
| `403` | Debug mode disabled |
| `500` | Log directory not configured |

### Example calls

```bash
# List root of log directory
curl 'http://your-app.com/log-viewer/api/list'

# List a subdirectory
curl 'http://your-app.com/log-viewer/api/list?path=cron'

# Get file metadata (size, totalPages)
curl 'http://your-app.com/log-viewer/api/stat?file=exception.log'

# Read first chunk of a file
curl 'http://your-app.com/log-viewer/api/view?file=exception.log&page=1'

# Search inside a file with 10 lines of context
curl 'http://your-app.com/log-viewer/api/search?file=exception.log&q=Error&context=10'

# Download the raw file
curl -o exception.log 'http://your-app.com/log-viewer/api/download?file=exception.log'
```

### Recommended workflow for log clients

1. Call `/api/list` to discover files (sort is "directories first, then newest first").
2. Call `/api/stat` to inspect file size and number of pages.
3. Call `/api/view` (with `page`) for paginated content, or `/api/search?q=...` to jump to a match with context.
4. Call `/api/download` only when the raw file is needed (e.g. uploading to a ticket).

### Claude Code skill

The package ships a Claude Code [Skill](https://docs.claude.com/en/docs/claude-code/skills) that teaches Claude how to call the API and what workflows to use. Install it once per machine, then any Claude Code session targeting any of your Nette projects gains the skill automatically:

```bash
# User-level (recommended) — skill available in every Claude Code session
mkdir -p ~/.claude/skills
cp -r vendor/liquiddesign/nette-log-viewer/skill/log-viewer-api ~/.claude/skills/

# Or symlink so the skill updates with composer update
ln -s "$(pwd)/vendor/liquiddesign/nette-log-viewer/skill/log-viewer-api" ~/.claude/skills/log-viewer-api
```

To restrict the skill to a single project, copy it to `.claude/skills/` in your project root instead.

When Claude is asked to look into logs, it will reach for `/api/list`, `/api/stat`, `/api/view`, and `/api/search` via `curl`. You only need to tell Claude the base URL of your log viewer (e.g. `https://app.example.com/log-viewer/api`) and ensure the source IP is on Tracy's debug-mode allowlist.

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
