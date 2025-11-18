<?php

declare(strict_types=1);

namespace LogViewer;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Log Viewer - Developer tool for viewing and downloading log files
 * Accessible only in debug mode (Tracy enabled)
 */
class LogViewerPresenter extends Presenter
{
	private string $logDir;

	public function actionDefault(?string $path = null, int $page = 1, ?string $search = null): void
	{
		// Validate and sanitize path
		$relativePath = $this->validatePath($path);

		$fullPath = $this->logDir . ($relativePath !== '' ? '/' . $relativePath : '');

		if (!\is_dir($fullPath)) {
			throw new BadRequestException('Invalid directory');
		}

		// Validate page parameter
		if ($page < 1) {
			$this->redirect('this', ['path' => $path === '' ? null : $path, 'page' => 1, 'search' => $search]);
		}

		// Validate search parameter (empty string same as null)
		if ($search !== '') {
			return;
		}

		$this->redirect('this', ['path' => $path === '' ? null : $path, 'page' => $page, 'search' => null]);
	}

	public function renderDefault(?string $path = null, int $page = 1, ?string $search = null): void
	{
		$relativePath = $this->validatePath($path);
		$fullPath = $this->logDir . ($relativePath !== '' ? '/' . $relativePath : '');

		// Get files and directories
		$allItems = $this->getDirectoryContents($fullPath, $relativePath);

		// Filter by search if provided
		if ($search !== null && $search !== '') {
			$searchLower = \mb_strtolower($search);
			$allItems = \array_filter($allItems, function (array $item) use ($searchLower): bool {
				return \str_contains(\mb_strtolower($item['name']), $searchLower);
			});
			// Re-index array
			$allItems = \array_values($allItems);
		}

		// Sort: directories first, then files, alphabetically
		\usort($allItems, function (array $a, array $b): int {
			if ($a['is_dir'] !== $b['is_dir']) {
				return $b['is_dir'] <=> $a['is_dir'];
			}

			return $a['name'] <=> $b['name'];
		});

		// Pagination
		$itemsPerPage = 100;
		$totalItems = \count($allItems);
		$totalPages = (int) \ceil($totalItems / $itemsPerPage);

		// Validate page number
		if ($page < 1) {
			$page = 1;
		}

		if ($page > $totalPages && $totalPages > 0) {
			$page = $totalPages;
		}

		// Get items for current page
		$offset = ($page - 1) * $itemsPerPage;
		$items = \array_slice($allItems, $offset, $itemsPerPage);

		$this->template->items = $items;
		$this->template->currentPath = $relativePath;
		$this->template->breadcrumbs = $this->getBreadcrumbs($relativePath);
		$this->template->currentPage = $page;
		$this->template->totalPages = $totalPages;
		$this->template->totalItems = $totalItems;
		$this->template->itemsPerPage = $itemsPerPage;
		$this->template->searchQuery = $search;
	}

	public function actionView(string $file, int $page = 1, ?string $search = null, int $context = 5, string $contextDirection = 'both'): void
	{
		$filePath = $this->validateFilePath($file);
		$fullPath = $this->logDir . '/' . $filePath;

		if (!\is_file($fullPath)) {
			throw new BadRequestException('File not found');
		}

		// Validate page parameter
		if ($page < 1) {
			$this->redirect('this', ['file' => $file, 'page' => 1, 'search' => $search, 'context' => $context, 'contextDirection' => $contextDirection]);
		}

		// Validate context parameter
		if ($context < 1 || $context > 300) {
			$validContext = \max(1, \min(300, $context));
			$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => $search, 'context' => $validContext, 'contextDirection' => $contextDirection]);
		}

		// Validate contextDirection parameter
		if ($contextDirection !== 'both' && $contextDirection !== 'before' && $contextDirection !== 'after') {
			$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => $search, 'context' => $context, 'contextDirection' => 'both']);
		}

		// Validate search parameter (empty string same as null)
		if ($search !== '') {
			return;
		}

		$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => null, 'context' => $context, 'contextDirection' => $contextDirection]);
	}

	public function renderView(string $file, int $page = 1, ?string $search = null, int $context = 5, string $contextDirection = 'both'): void
	{
		$filePath = $this->validateFilePath($file);
		$fullPath = $this->logDir . '/' . $filePath;

		$fileInfo = \pathinfo($fullPath);
		$isHtml = isset($fileInfo['extension']) && $fileInfo['extension'] === 'html';

		$fileSize = \filesize($fullPath);

		if ($fileSize === false) {
			$fileSize = 0;
		}

		// Validate and limit context
		if ($context < 1) {
			$context = 1;
		}

		if ($context > 300) {
			$context = 300;
		}

		// Validate contextDirection
		if ($contextDirection !== 'both' && $contextDirection !== 'before' && $contextDirection !== 'after') {
			$contextDirection = 'both';
		}

		// If search is provided, find the text and show context
		if ($search !== null && $search !== '' && !$isHtml) {
			$searchResult = $this->searchInFile($fullPath, $search, $context, $contextDirection);

			if ($searchResult !== null) {
				$this->template->content = $searchResult['content'];
				$this->template->totalSize = $fileSize;
				$this->template->currentPage = 1;
				$this->template->totalPages = 1;
				$this->template->chunkSize = null;
				$this->template->displayedSize = Strings::length($searchResult['content']);
				$this->template->searchQuery = $search;
				$this->template->searchLineNumber = $searchResult['lineNumber'];
				$this->template->searchFound = true;
				$this->template->searchContext = $context;
				$this->template->searchContextDirection = $contextDirection;
			} else {
				$this->template->content = '';
				$this->template->totalSize = $fileSize;
				$this->template->currentPage = 1;
				$this->template->totalPages = 1;
				$this->template->chunkSize = null;
				$this->template->displayedSize = 0;
				$this->template->searchQuery = $search;
				$this->template->searchLineNumber = null;
				$this->template->searchFound = false;
				$this->template->searchContext = $context;
				$this->template->searchContextDirection = $contextDirection;
			}
		} elseif ($isHtml) {
			// For HTML files, load everything (Tracy dumps are typically not huge)
			$content = FileSystem::read($fullPath);
			$this->template->content = $content;
			$this->template->totalSize = $fileSize;
			$this->template->currentPage = 1;
			$this->template->totalPages = 1;
			$this->template->chunkSize = null;
			$this->template->displayedSize = $fileSize;
			$this->template->searchQuery = null;
			$this->template->searchLineNumber = null;
			$this->template->searchFound = null;
			$this->template->searchContext = null;
			$this->template->searchContextDirection = null;
		} else {
			// For text files, use pagination by size (100KB chunks)
			// 100KB
			$chunkSize = 100 * 1024;
			$totalPages = (int) \ceil($fileSize / $chunkSize);

			// Validate page number
			if ($page < 1) {
				$page = 1;
			}

			if ($page > $totalPages && $totalPages > 0) {
				$page = $totalPages;
			}

			// Read chunk from file
			$offset = ($page - 1) * $chunkSize;
			$handle = \fopen($fullPath, 'r');

			if ($handle === false) {
				$content = '';
				$actualSize = 0;
			} else {
				\fseek($handle, $offset);
				$content = \fread($handle, $chunkSize);

				if ($content === false) {
					$content = '';
					$actualSize = 0;
				} else {
					// If not at the end of file and didn't end with newline, read until end of line
					if (!\feof($handle) && !Strings::endsWith($content, "\n")) {
						$extraChars = '';
						// Max 10KB extra to complete the line
						$maxExtra = 10000;
						$readCount = 0;

						while (!\feof($handle) && $readCount < $maxExtra) {
							$char = \fgetc($handle);

							if ($char === false) {
								break;
							}

							$extraChars .= $char;
							$readCount++;

							if ($char === "\n") {
								break;
							}
						}

						$content .= $extraChars;
					}

					// If not first page, skip partial first line
					if ($page > 1) {
						$firstNewline = Strings::indexOf($content, "\n");

						if ($firstNewline !== null) {
							$content = Strings::substring($content, $firstNewline + 1);
						}
					}

					$actualSize = Strings::length($content);
				}

				\fclose($handle);
			}

			$this->template->content = $content;
			$this->template->totalSize = $fileSize;
			$this->template->currentPage = $page;
			$this->template->totalPages = $totalPages;
			$this->template->chunkSize = $chunkSize;
			$this->template->displayedSize = $actualSize;
			$this->template->searchQuery = null;
			$this->template->searchLineNumber = null;
			$this->template->searchFound = null;
			$this->template->searchContext = null;
			$this->template->searchContextDirection = null;
		}

		$this->template->fileName = \basename($fullPath);
		$this->template->filePath = $filePath;
		$this->template->isHtml = $isHtml;
		$this->template->fileSize = \filesize($fullPath);
		$this->template->lastModified = \filemtime($fullPath);
	}

	public function actionDownload(string $file): void
	{
		$filePath = $this->validateFilePath($file);
		$fullPath = $this->logDir . '/' . $filePath;

		if (!\is_file($fullPath)) {
			throw new BadRequestException('File not found');
		}

		$this->sendResponse(new FileResponse($fullPath, \basename($fullPath)));
	}

	protected function startup(): void
	{
		parent::startup();

		// Security: Only accessible when Tracy debugger is enabled
		if (!Debugger::isEnabled()) {
			throw new BadRequestException('Access denied');
		}

		// Use Tracy's log directory
		$logDirectory = Debugger::$logDirectory;

		if ($logDirectory === null) {
			throw new BadRequestException('Log directory is not configured');
		}

		$this->logDir = $logDirectory;
	}

	/**
	 * Validate and sanitize path to prevent directory traversal
	 * @return string Validated relative path (empty string for root)
	 */
	private function validatePath(?string $path): string
	{
		if ($path === null || $path === '') {
			return '';
		}

		// Remove any directory traversal attempts
		$path = \str_replace(['..', '\\'], '', $path);
		$path = Strings::trim($path, '/');

		return $path;
	}

	/**
	 * Validate file path and ensure it's within log directory
	 * @return string Validated relative file path
	 */
	private function validateFilePath(string $file): string
	{
		$file = $this->validatePath($file);

		if ($file === '') {
			throw new BadRequestException('Invalid file path');
		}

		$fullPath = $this->logDir . '/' . $file;
		$realPath = \realpath($fullPath);
		$logDirRealPath = \realpath($this->logDir);

		// Ensure the file is within log directory
		if ($realPath === false || $logDirRealPath === false || !\str_starts_with($realPath, $logDirRealPath)) {
			throw new BadRequestException('Invalid file path');
		}

		return $file;
	}

	/**
	 * Get contents of directory with file information
	 * @return array<int, array{name: string, path: string, is_dir: bool, size: int|null, modified: int|null, extension: string|null, type: string}>
	 */
	private function getDirectoryContents(string $fullPath, string $relativePath): array
	{
		$items = [];
		$entries = \scandir($fullPath);

		if ($entries === false) {
			return [];
		}

		foreach ($entries as $entry) {
			// Skip . and .. and hidden files/folders (starting with .)
			if ($entry === '.' || $entry === '..' || \str_starts_with($entry, '.')) {
				continue;
			}

			$itemFullPath = $fullPath . '/' . $entry;
			$itemRelativePath = $relativePath !== '' ? $relativePath . '/' . $entry : $entry;
			$isDir = \is_dir($itemFullPath);

			$modified = \filemtime($itemFullPath);

			if ($isDir) {
				$items[] = [
					'name' => $entry,
					'path' => $itemRelativePath,
					'is_dir' => true,
					'size' => null,
					'modified' => $modified !== false ? $modified : null,
					'extension' => null,
					'type' => 'directory',
				];
			} else {
				$extension = \pathinfo($entry, \PATHINFO_EXTENSION);
				$size = \filesize($itemFullPath);

				$items[] = [
					'name' => $entry,
					'path' => $itemRelativePath,
					'is_dir' => false,
					'size' => $size !== false ? $size : null,
					'modified' => $modified !== false ? $modified : null,
					'extension' => $extension,
					'type' => $this->getFileType($extension),
				];
			}
		}

		return $items;
	}

	/**
	 * Get human-readable file type based on extension
	 */
	private function getFileType(string $extension): string
	{
		return match ($extension) {
			'log' => 'log',
			'html' => 'html',
			'json' => 'json',
			'txt' => 'text',
			default => 'file',
		};
	}

	/**
	 * Generate breadcrumb navigation
	 * @return array<int, array{name: string, path: string|null}>
	 */
	private function getBreadcrumbs(string $relativePath): array
	{
		$breadcrumbs = [
			['name' => 'Log Viewer', 'path' => null],
		];

		if ($relativePath === '') {
			return $breadcrumbs;
		}

		$parts = \explode('/', $relativePath);
		$currentPath = '';

		foreach ($parts as $part) {
			$currentPath .= ($currentPath !== '' ? '/' : '') . $part;
			$breadcrumbs[] = [
				'name' => $part,
				'path' => $currentPath,
			];
		}

		return $breadcrumbs;
	}

	/**
	 * Search for text in file and return context around first match
	 * @return array{content: string, lineNumber: int}|null
	 */
	private function searchInFile(string $fullPath, string $searchText, int $contextLines = 5, string $direction = 'both'): ?array
	{
		$handle = \fopen($fullPath, 'r');

		if ($handle === false) {
			return null;
		}

		$buffer = [];
		$lineNumber = 0;
		$foundAtLine = null;
		$linesAfterFound = 0;

		while (($line = \fgets($handle)) !== false) {
			$lineNumber++;

			// If not found yet, maintain a rolling buffer of last N lines
			if ($foundAtLine === null) {
				// Only keep buffer for 'both' or 'before' directions
				if ($direction === 'both' || $direction === 'before') {
					$buffer[] = $line;

					// Keep only last contextLines lines
					if (\count($buffer) > $contextLines) {
						\array_shift($buffer);
					}
				}

				// Check if this line contains the search text (case-insensitive)
				if (\stripos($line, $searchText) !== false) {
					$foundAtLine = $lineNumber;

					// For 'after' direction, start with empty buffer and add the found line
					if ($direction === 'after') {
						$buffer = [$line];
					}

					// For 'before' direction, we already have the buffer, don't add more
					if ($direction === 'before') {
						break;
					}

					continue;
				}
			} else {
				// We found it, now collect contextLines lines after (for 'both' and 'after')
				if ($direction === 'both' || $direction === 'after') {
					$buffer[] = $line;
					$linesAfterFound++;

					if ($linesAfterFound >= $contextLines) {
						break;
					}
				}
			}
		}

		\fclose($handle);

		if ($foundAtLine === null) {
			return null;
		}

		$content = \implode('', $buffer);

		return [
			'content' => $content,
			'lineNumber' => $foundAtLine,
		];
	}
}
