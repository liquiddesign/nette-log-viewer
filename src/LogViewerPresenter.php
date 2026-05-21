<?php

declare(strict_types=1);

namespace LogViewer;

use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Log Viewer - Developer tool for viewing and downloading log files
 * Accessible only in debug mode (Tracy enabled)
 */
class LogViewerPresenter extends Presenter
{
	protected string $logDir;

	private ?LogReader $cachedReader = null;

	public function actionDefault(?string $path = null, int $page = 1, ?string $search = null): void
	{
		$relativePath = $this->reader()->validatePath($path);

		$fullPath = $this->logDir . ($relativePath !== '' ? '/' . $relativePath : '');

		if (!\is_dir($fullPath)) {
			throw new BadRequestException('Invalid directory');
		}

		if ($page < 1) {
			$this->redirect('this', ['path' => $path === '' ? null : $path, 'page' => 1, 'search' => $search]);
		}

		if ($search !== '') {
			return;
		}

		$this->redirect('this', ['path' => $path === '' ? null : $path, 'page' => $page, 'search' => null]);
	}

	public function renderDefault(?string $path = null, int $page = 1, ?string $search = null): void
	{
		try {
			$relativePath = $this->reader()->validatePath($path);
			$allItems = $this->reader()->listDirectory($relativePath);
		} catch (InvalidPathException $e) {
			throw new BadRequestException($e->getMessage());
		}

		if ($search !== null && $search !== '') {
			$searchLower = \mb_strtolower($search);
			$allItems = \array_filter($allItems, function (array $item) use ($searchLower): bool {
				return \str_contains(\mb_strtolower($item['name']), $searchLower);
			});
			$allItems = \array_values($allItems);
		}

		\usort($allItems, function (array $a, array $b): int {
			if ($a['is_dir'] !== $b['is_dir']) {
				return $b['is_dir'] <=> $a['is_dir'];
			}

			return ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0);
		});

		$itemsPerPage = LogReader::DEFAULT_ITEMS_PER_PAGE;
		$totalItems = \count($allItems);
		$totalPages = (int) \ceil($totalItems / $itemsPerPage);

		if ($page < 1) {
			$page = 1;
		}

		if ($page > $totalPages && $totalPages > 0) {
			$page = $totalPages;
		}

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
		try {
			$this->reader()->validateFilePath($file);
		} catch (InvalidPathException $e) {
			throw new BadRequestException($e->getMessage());
		}

		if ($page < 1) {
			$this->redirect('this', ['file' => $file, 'page' => 1, 'search' => $search, 'context' => $context, 'contextDirection' => $contextDirection]);
		}

		if ($context < 1 || $context > 300) {
			$validContext = \max(1, \min(300, $context));
			$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => $search, 'context' => $validContext, 'contextDirection' => $contextDirection]);
		}

		if ($contextDirection !== 'both' && $contextDirection !== 'before' && $contextDirection !== 'after') {
			$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => $search, 'context' => $context, 'contextDirection' => 'both']);
		}

		if ($search !== '') {
			return;
		}

		$this->redirect('this', ['file' => $file, 'page' => $page, 'search' => null, 'context' => $context, 'contextDirection' => $contextDirection]);
	}

	public function renderView(string $file, int $page = 1, ?string $search = null, int $context = 5, string $contextDirection = 'both'): void
	{
		try {
			$filePath = $this->reader()->validateFilePath($file);
			$fullPath = $this->reader()->fullPath($filePath);
		} catch (InvalidPathException $e) {
			throw new BadRequestException($e->getMessage());
		}

		$fileInfo = \pathinfo($fullPath);
		$isHtml = isset($fileInfo['extension']) && $fileInfo['extension'] === 'html';

		$fileSize = \filesize($fullPath);

		if ($fileSize === false) {
			$fileSize = 0;
		}

		if ($context < 1) {
			$context = 1;
		}

		if ($context > 300) {
			$context = 300;
		}

		if ($contextDirection !== 'both' && $contextDirection !== 'before' && $contextDirection !== 'after') {
			$contextDirection = 'both';
		}

		$searchActive = $search !== null && $search !== '' && !$isHtml;

		try {
			$searchResult = $searchActive ? $this->reader()->search($filePath, $search, $context, $contextDirection) : null;
			$htmlContent = $isHtml && !$searchActive ? $this->reader()->readAll($filePath) : null;
			$chunk = !$isHtml && !$searchActive ? $this->reader()->readChunk($filePath, $page) : null;
		} catch (InvalidPathException $e) {
			throw new BadRequestException($e->getMessage());
		}

		if ($searchActive) {
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
			$this->template->content = $htmlContent ?? '';
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
			$chunk ??= $this->reader()->readChunk($filePath, $page);
			$this->template->content = $chunk['content'];
			$this->template->totalSize = $chunk['fileSize'];
			$this->template->currentPage = $chunk['currentPage'];
			$this->template->totalPages = $chunk['totalPages'];
			$this->template->chunkSize = $chunk['chunkSize'];
			$this->template->displayedSize = $chunk['displayedSize'];
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
		try {
			$filePath = $this->reader()->validateFilePath($file);
			$fullPath = $this->reader()->fullPath($filePath);
		} catch (InvalidPathException $e) {
			throw new BadRequestException($e->getMessage());
		}

		$this->sendResponse(new FileResponse($fullPath, \basename($fullPath)));
	}

	/**
	 * Format template files - ensure templates are loaded from package directory
	 * This is needed when presenter is extended in application namespace
	 * @return array<string>
	 */
	public function formatTemplateFiles(): array
	{
		$name = $this->getName() ?? 'LogViewer';
		$presenter = Strings::substring($name, (int) \strrpos(':' . $name, ':'));
		$file = (new \ReflectionClass(self::class))->getFileName();
		$dir = $file !== false ? \dirname($file) : __DIR__;

		return [
			"$dir/templates/$presenter.$this->view.latte",
		];
	}

	protected function startup(): void
	{
		parent::startup();

		if (!Debugger::isEnabled()) {
			throw new BadRequestException('Access denied');
		}

		$logDirectory = Debugger::$logDirectory;

		if ($logDirectory === null) {
			throw new BadRequestException('Log directory is not configured');
		}

		$this->logDir = $logDirectory;
	}

	/**
	 * Lazily build LogReader bound to the current $logDir.
	 * Re-creates the instance if an override changed $logDir after startup().
	 */
	protected function reader(): LogReader
	{
		if ($this->cachedReader === null || $this->cachedReader->getLogDir() !== $this->logDir) {
			$this->cachedReader = new LogReader($this->logDir);
		}

		return $this->cachedReader;
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
}
