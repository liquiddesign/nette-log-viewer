# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.1]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.0.1
[1.0.0]: https://github.com/liquiddesign/nette-log-viewer/releases/tag/v1.0.0
