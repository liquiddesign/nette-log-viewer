# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-05-22

### Added
- `LogViewer\DI\LogViewerExtension` for one-line setup — registers UI + JSON API routes and presenter mapping via Nette DI. Eliminates the need to write 4 routes into `pages.neon` (and the wildcard-order trap that caused). Configurable `urlPrefix`, `presenter`, `apiPresenter`, and toggles `registerRoutes` / `registerPresenterMapping`.

### Changed
- `composer.json`: added explicit dependencies on `nette/di ^3.1` and `nette/schema ^1.2` (previously satisfied transitively via `nette/application`)

## [1.1.0] - 2026-05-21

### Added
- JSON REST API (`LogViewerApiPresenter`) with endpoints: `list`, `stat`, `view`, `search`, `download`
- Claude Code skill in `skill/log-viewer-api/` documenting the API for AI assistants
- HTTP method restriction on API (returns `405` for non-GET/HEAD)
- HTML view size cap (5 MB) with `truncated` flag to prevent OOM on large Tracy dumps

### Changed
- Extracted shared file/directory logic into new `LogReader` class; `LogViewerPresenter` now delegates to it
- `$logDir` is now `protected` (was private), allowing extension via subclass override as documented

### Fixed
- Sibling-directory bypass in `validateFilePath` (`/var/log` could match `/var/log-other`); strict `DIRECTORY_SEPARATOR` containment check
- Missing realpath containment check in directory listing (symlinks inside log dir could escape)
- File handle leak in `search` and `readChunk` when iteration threw an exception (now use `try/finally`)
- `fopen` failure in `readChunk`/`search` silently returned empty content; now throws `InvalidPathException`

## [1.0.2] - 2025-12-01

### Changed
- File listing now sorted by modification date (newest first) instead of alphabetically

## [1.0.1] - 2025-01-18

### Fixed
- Template resolution when extending presenter in application namespace - Added `formatTemplateFiles()` method to ensure templates are loaded from package directory even when presenter is extended in app namespace (e.g., `App\Web\LogViewerPresenter extends LogViewer\LogViewerPresenter`)

## [1.0.0] - 2025-01-18

### Added
- Initial release
- Log directory browser with folder navigation
- File viewer with syntax highlighting
- HTML Tracy dump viewer (iframe)
- Search functionality in files with configurable context (3-300 lines)
- Pagination for directories (100 items per page)
- Pagination for large files (100KB chunks)
- Download support for log files
- Security: Access only in debug mode (Tracy enabled)
- Bootstrap 5 UI with responsive design
- Font Awesome icons

### Security
- Path traversal protection
- Debug mode access restriction
- Directory boundary validation

[1.1.0]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.1.0
[1.0.2]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.0.2
[1.0.1]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.0.1
[1.0.0]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.0.0
