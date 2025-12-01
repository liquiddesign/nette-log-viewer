# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nette Log Viewer is a developer tool for viewing and downloading Tracy log files in Nette Framework applications. It provides a web interface for browsing log directories, viewing log content with syntax highlighting, and downloading files. Access is restricted to debug mode only (Tracy enabled).

## Commands

```bash
# Static analysis (PHPStan level 8)
composer phpstan

# Code style check
composer phpcs

# Code style auto-fix
composer phpcsfix
```

## Architecture

This is a single-presenter package with a simple structure:

- `src/LogViewerPresenter.php` - Main presenter handling all functionality:
  - `actionDefault` / `renderDefault` - Directory browser with pagination (100 items/page) and search
  - `actionView` / `renderView` - File viewer with pagination (100KB chunks) and in-file search
  - `actionDownload` - File download handler
  - Security: Only accessible when `Tracy\Debugger::isEnabled()` returns true

- `src/templates/` - Latte templates:
  - `LogViewer.default.latte` - Directory listing view
  - `LogViewer.view.latte` - File content view (handles both text and HTML Tracy dumps)

## Key Implementation Details

- Uses `Tracy\Debugger::$logDirectory` as the default log path
- Template loading via `formatTemplateFiles()` ensures templates load from package directory even when the presenter is extended
- Path validation prevents directory traversal attacks
- Large files are paginated by byte offset, not line count
- HTML Tracy dump files are rendered in iframes
