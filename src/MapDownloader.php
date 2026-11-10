<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use holonet\sc2utils\model\MapRef;

/**
 * Downloads a map from the Battle.net depot: the depot the replay names first, then the fallback depots with the
 * same hash. The file name on the depot is the SHA-256 of the content, which is checked before anything is written,
 * so any depot that has the file is safe to use.
 */
class MapDownloader {
	/**
	 * tried after the replay's own depot.
	 */
	public const array FALLBACK_REGIONS = array('us', 'eu', 'kr');

	/**
	 * @param float $timeout seconds per request
	 */
	public function __construct(
		private readonly float $timeout = 60.0,
		private readonly string $userAgent = 'sc2utils',
	) {}

	/**
	 * Downloads the map to $target (overwritten) and returns the URL it came from.
	 * Throws MapDownloadException naming every URL tried and why it failed; the target is then left as it was.
	 */
	public function download(MapRef $map, string $target): string {
		$errors = array();
		foreach ($this->urls($map) as $url) {
			try {
				$body = $this->fetch($url);
			} catch (MapDownloadException $e) {
				$errors[] = "{$url}: {$e->getMessage()}";

				continue;
			}

			if (!hash_equals(strtolower($map->hash), hash('sha256', $body))) {
				$errors[] = "{$url}: the SHA-256 doesn't match";

				continue;
			}

			if (file_put_contents($target, $body) !== strlen($body)) {
				throw new MapDownloadException("Cannot write {$target}");
			}

			return $url;
		}

		throw new MapDownloadException("Cannot download map {$map->hash}:\n".implode("\n", $errors));
	}

	/**
	 * The status code of the last response in a header list (after redirects it holds one status line per
	 * response), 0 if there is none.
	 *
	 * @param list<string> $headers
	 */
	public static function status(array $headers): int {
		$status = 0;
		foreach ($headers as $line) {
			if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
				$status = (int)$match[1];
			}
		}

		return $status;
	}

	/**
	 * The URLs in the order download() tries them.
	 *
	 * @return list<string>
	 */
	public function urls(MapRef $map): array {
		$urls = array($map->depotUrl);
		foreach (self::FALLBACK_REGIONS as $region) {
			$urls[] = new MapRef($map->name, $map->hash, $region, $map->type)->depotUrl;
		}

		return array_values(array_unique(array_filter($urls, static fn (?string $url): bool => $url !== null)));
	}

	protected function fetch(string $url): string {
		$context = stream_context_create(array('http' => array(
			'timeout' => $this->timeout,
			'user_agent' => $this->userAgent,
			'follow_location' => 1,
			'max_redirects' => 3,
			// a 404 returns its body instead of false and a warning, the status check decides
			'ignore_errors' => true,
		)));

		http_clear_last_response_headers();
		$body = @file_get_contents($url, false, $context);
		$headers = http_get_last_response_headers();

		if ($headers === null) {
			// no HTTP response at all: DNS, connection, timeout
			throw new MapDownloadException(error_get_last()['message'] ?? 'no response');
		}

		$status = self::status($headers);
		if ($status !== 200 || $body === false) {
			throw new MapDownloadException("HTTP {$status}");
		}

		return $body;
	}
}
