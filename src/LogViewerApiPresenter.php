<?php

declare(strict_types=1);

namespace LogViewer;

use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * JSON REST API for Nette Log Viewer.
 *
 * Mirrors the operations of LogViewerPresenter but emits JSON.
 * Access control matches the UI presenter — only available when
 * Tracy debugger is enabled.
 */
class LogViewerApiPresenter extends Presenter
{
	/** Maximum byte size of a single HTML file loaded in /view (5 MB) */
	public const HTML_VIEW_LIMIT = 5 * 1024 * 1024;

	protected string $logDir;

	private ?LogReader $cachedReader = null;

	public function actionList(?string $path = null, int $page = 1, ?string $search = null, int $itemsPerPage = LogReader::DEFAULT_ITEMS_PER_PAGE): void
	{
		try {
			$relativePath = $this->reader()->validatePath($path);
			$allItems = $this->reader()->listDirectory($relativePath);
		} catch (InvalidPathException $e) {
			$this->sendErrorResponse(400, $e->getMessage());
		}

		if ($search !== null && $search !== '') {
			$searchLower = \mb_strtolower($search);
			$allItems = \array_values(\array_filter($allItems, function (array $item) use ($searchLower): bool {
				return \str_contains(\mb_strtolower($item['name']), $searchLower);
			}));
		}

		\usort($allItems, function (array $a, array $b): int {
			if ($a['is_dir'] !== $b['is_dir']) {
				return $b['is_dir'] <=> $a['is_dir'];
			}

			return ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0);
		});

		if ($itemsPerPage < 1) {
			$itemsPerPage = LogReader::DEFAULT_ITEMS_PER_PAGE;
		}

		if ($itemsPerPage > 1000) {
			$itemsPerPage = 1000;
		}

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

		$this->sendJsonPayload([
			'path' => $relativePath,
			'items' => $items,
			'page' => $page,
			'totalPages' => $totalPages,
			'totalItems' => $totalItems,
			'itemsPerPage' => $itemsPerPage,
			'search' => $search !== '' ? $search : null,
		]);
	}

	public function actionStat(string $file): void
	{
		try {
			$relativeFile = $this->reader()->validateFilePath($file);
			$stat = $this->reader()->stat($relativeFile);
		} catch (InvalidPathException $e) {
			$this->sendErrorResponse(400, $e->getMessage());
		}

		$this->sendJsonPayload([
			'file' => $relativeFile,
			'size' => $stat['size'],
			'lastModified' => $stat['modified'],
			'extension' => $stat['extension'],
			'type' => $stat['type'],
			'isHtml' => $stat['isHtml'],
			'totalPages' => $stat['totalPages'],
			'chunkSize' => $stat['chunkSize'],
		]);
	}

	public function actionView(string $file, int $page = 1): void
	{
		try {
			$relativeFile = $this->reader()->validateFilePath($file);
			$stat = $this->reader()->stat($relativeFile);

			if ($stat['isHtml']) {
				if ($stat['size'] > self::HTML_VIEW_LIMIT) {
					$this->sendJsonPayload([
						'file' => $relativeFile,
						'page' => 1,
						'totalPages' => 1,
						'chunkSize' => 0,
						'fileSize' => $stat['size'],
						'lastModified' => $stat['modified'],
						'isHtml' => true,
						'truncated' => true,
						'displayedSize' => 0,
						'content' => '',
						'hint' => 'HTML file exceeds limit; use /download endpoint',
					]);
				}

				$content = $this->reader()->readAll($relativeFile);

				$this->sendJsonPayload([
					'file' => $relativeFile,
					'page' => 1,
					'totalPages' => 1,
					'chunkSize' => 0,
					'fileSize' => $stat['size'],
					'lastModified' => $stat['modified'],
					'isHtml' => true,
					'truncated' => false,
					'displayedSize' => Strings::length($content),
					'content' => $content,
				]);
			}

			$chunk = $this->reader()->readChunk($relativeFile, $page);
		} catch (InvalidPathException $e) {
			$this->sendErrorResponse(400, $e->getMessage());
		}

		$this->sendJsonPayload([
			'file' => $relativeFile,
			'page' => $chunk['currentPage'],
			'totalPages' => $chunk['totalPages'],
			'chunkSize' => $chunk['chunkSize'],
			'fileSize' => $chunk['fileSize'],
			'lastModified' => $stat['modified'],
			'isHtml' => false,
			'truncated' => false,
			'displayedSize' => $chunk['displayedSize'],
			'content' => $chunk['content'],
		]);
	}

	public function actionSearch(string $file, string $q, int $context = 5, string $direction = 'both'): void
	{
		if ($q === '') {
			$this->sendErrorResponse(400, 'Missing query parameter "q"');
		}

		if ($context < 1) {
			$context = 1;
		}

		if ($context > 300) {
			$context = 300;
		}

		if ($direction !== 'both' && $direction !== 'before' && $direction !== 'after') {
			$direction = 'both';
		}

		try {
			$relativeFile = $this->reader()->validateFilePath($file);
			$stat = $this->reader()->stat($relativeFile);

			if ($stat['isHtml']) {
				$this->sendErrorResponse(400, 'Search is not supported on HTML files');
			}

			$result = $this->reader()->search($relativeFile, $q, $context, $direction);
		} catch (InvalidPathException $e) {
			$this->sendErrorResponse(400, $e->getMessage());
		}

		$this->sendJsonPayload([
			'file' => $relativeFile,
			'query' => $q,
			'context' => $context,
			'direction' => $direction,
			'found' => $result !== null,
			'lineNumber' => $result !== null ? $result['lineNumber'] : null,
			'content' => $result !== null ? $result['content'] : '',
		]);
	}

	public function actionDownload(string $file): void
	{
		try {
			$relativeFile = $this->reader()->validateFilePath($file);
			$fullPath = $this->reader()->fullPath($relativeFile);
		} catch (InvalidPathException $e) {
			$this->sendErrorResponse(400, $e->getMessage());
		}

		$this->sendResponse(new FileResponse($fullPath, \basename($fullPath)));
	}

	protected function startup(): void
	{
		parent::startup();

		$method = $this->getHttpRequest()->getMethod();

		if ($method !== 'GET' && $method !== 'HEAD') {
			$this->getHttpResponse()->addHeader('Allow', 'GET, HEAD');
			$this->sendErrorResponse(405, 'Method not allowed');
		}

		if (!Debugger::isEnabled()) {
			$this->sendErrorResponse(403, 'Access denied');
		}

		$logDirectory = Debugger::$logDirectory;

		if ($logDirectory === null) {
			$this->sendErrorResponse(500, 'Log directory is not configured');
		}

		$this->logDir = $logDirectory;
	}

	/**
	 * Lazily build LogReader bound to the current $logDir.
	 */
	protected function reader(): LogReader
	{
		if ($this->cachedReader === null || $this->cachedReader->getLogDir() !== $this->logDir) {
			$this->cachedReader = new LogReader($this->logDir);
		}

		return $this->cachedReader;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	protected function sendJsonPayload(array $payload, int $code = 200): never
	{
		$this->getHttpResponse()->setCode($code);
		$this->sendResponse(new JsonResponse($payload));
	}

	protected function sendErrorResponse(int $code, string $message): never
	{
		$this->sendJsonPayload(['error' => $message, 'code' => $code], $code);
	}
}
