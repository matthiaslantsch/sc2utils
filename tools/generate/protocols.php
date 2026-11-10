<?php
/**
 * Turn the JSON lines of tools/generate/protocols.py into the protocol data files in src/protocol/data/.
 *
 * Usage (from the repo root; tools/generate/protocols.sh runs the whole pipe):
 *     tools/.venv/bin/python tools/generate/protocols.py | php tools/generate/protocols.php [output dir]
 *
 * Writes one protocolNNNNN.php per protocol and builds.php (the sorted list of builds).
 */
const DEFAULT_OUTPUT = __DIR__.'/../../src/protocol/data';

function fail(string $message): never {
	fwrite(\STDERR, "protocols.php: {$message}\n");

	exit(1);
}

function checkInt(mixed $value, string $where): int {
	if (!is_int($value)) {
		fail("{$where}: expected an integer, got ".json_encode($value));
	}

	return $value;
}

/**
 * @return array{int, int}
 */
function bounds(mixed $bounds, string $where): array {
	if (!is_array($bounds) || count($bounds) !== 2) {
		fail("{$where}: expected bounds [offset, bits], got ".json_encode($bounds));
	}

	return array(checkInt($bounds[0], $where), checkInt($bounds[1], $where));
}

/**
 * @param array<mixed> $typeinfo
 */
function typeinfo(array $typeinfo, int $count, string $where): array {
	$checkType = static function (mixed $type) use ($count, $where): int {
		$type = checkInt($type, $where);
		if ($type < 0 || $type >= $count) {
			fail("{$where}: type id {$type} out of range");
		}

		return $type;
	};

	list($kind, $args) = $typeinfo + array(null, null);
	if (!is_string($kind) || !is_array($args)) {
		fail("{$where}: malformed typeinfo ".json_encode($typeinfo));
	}

	switch ($kind) {
		case '_bool':
		case '_fourcc':
		case '_null':
			if ($args !== array()) {
				fail("{$where}: {$kind} takes no arguments");
			}

			return array(substr($kind, 1));
		case '_int':
		case '_blob':
		case '_bitarray':
			if (count($args) !== 1) {
				fail("{$where}: {$kind} takes the bounds only");
			}

			return array(substr($kind, 1), ...bounds($args[0], $where));
		case '_array':
			if (count($args) !== 2) {
				fail("{$where}: _array takes bounds and a type");
			}

			return array('array', ...bounds($args[0], $where), $checkType($args[1]));
		case '_optional':
			if (count($args) !== 1) {
				fail("{$where}: _optional takes a type");
			}

			return array('optional', $checkType($args[0]));
		case '_choice':
			if (count($args) !== 2 || !is_array($args[1])) {
				fail("{$where}: _choice takes bounds and a field map");
			}
			$fields = array();
			foreach ($args[1] as $tag => $field) {
				if (!is_array($field) || count($field) !== 2 || !is_string($field[0])) {
					fail("{$where}: malformed choice field ".json_encode($field));
				}
				$fields[checkInt(filter_var($tag, \FILTER_VALIDATE_INT), $where)] = array($field[0], $checkType($field[1]));
			}
			ksort($fields);

			return array('choice', ...bounds($args[0], $where), $fields);
		case '_struct':
			if (count($args) !== 1 || !is_array($args[0])) {
				fail("{$where}: _struct takes a field list");
			}
			$fields = array();
			foreach ($args[0] as $field) {
				if (!is_array($field) || count($field) !== 3 || !is_string($field[0])) {
					fail("{$where}: malformed struct field ".json_encode($field));
				}
				$fields[] = array($field[0], $checkType($field[1]), checkInt($field[2], $where));
			}

			return array('struct', $fields);
		default:
			fail("{$where}: unknown typeinfo kind {$kind}");
	}
}

/**
 * @return array<int, array{int, string}>|null
 */
function eventMap(mixed $map, int $count, string $where): ?array {
	if ($map === null) {
		return null;
	}
	if (!is_array($map)) {
		fail("{$where}: expected an event map");
	}
	$events = array();
	foreach ($map as $id => $event) {
		if (!is_array($event) || count($event) !== 2 || !is_string($event[1])) {
			fail("{$where}: malformed event ".json_encode($event));
		}
		$type = checkInt($event[0], $where);
		if ($type < 0 || $type >= $count) {
			fail("{$where}: type id {$type} out of range");
		}
		$events[checkInt(filter_var($id, \FILTER_VALIDATE_INT), $where)] = array($type, $event[1]);
	}
	ksort($events);

	return $events;
}

function export(mixed $value): string {
	if ($value === null) {
		return 'null';
	}
	if (is_int($value)) {
		// PHP_INT_MIN has no literal, -9223372036854775808 would be a float
		return $value === \PHP_INT_MIN ? 'PHP_INT_MIN' : (string)$value;
	}
	if (is_string($value)) {
		return var_export($value, true);
	}
	if (is_array($value)) {
		$list = array_is_list($value);
		$items = array();
		foreach ($value as $key => $item) {
			$items[] = ($list ? '' : export($key).' => ').export($item);
		}

		return 'array('.implode(', ', $items).')';
	}
	fail('cannot export '.get_debug_type($value));
}

/**
 * Multi-line form for the top level maps: one entry per line.
 *
 * @param array<int, mixed> $map
 */
function exportLines(array $map, bool $indexComments = false): string {
	$lines = array();
	foreach ($map as $key => $item) {
		$lines[] = "\t\t".($indexComments ? "/* {$key} */ " : export($key).' => ').export($item).',';
	}

	return "array(\n".implode("\n", $lines)."\n\t)";
}

function fileHeader(array $head, ?array $protocol = null): string {
	$commit = substr((string)$head['commit'], 0, 12);
	$lines = array(
		'Generated by tools/generate/protocols.php, do not edit.',
		"Source: s2protocol {$head['s2protocol']} (submodule commit {$commit})",
	);
	if ($protocol !== null) {
		$from = match ($protocol['source']) {
			'legacy' => "at {$head['legacy']} (".substr((string)$head['legacyCommit'], 0, 12).'), legacy generation',
			'supplement' => 'hand-written, derived from an earlier protocol with known changes applied to it',
			default => 'in the submodule commit',
		};
		$lines[] = "{$protocol['file']} {$from}";
		$lines[] = '';
		foreach (explode("\n", (string)$protocol['notice']) as $line) {
			$lines[] = $line;
		}
	}

	return "<?php\n/**\n".implode("\n", array_map(static fn (string $l): string => rtrim(" * {$l}"), $lines))."\n */\n";
}

function render(array $head, array $protocol): string {
	$build = checkInt($protocol['build'], 'build');
	$where = "protocol {$build}";
	if (!is_array($protocol['typeinfos']) || !array_is_list($protocol['typeinfos'])) {
		fail("{$where}: typeinfos must be a list");
	}
	$count = count($protocol['typeinfos']);
	$typeinfos = array();
	foreach ($protocol['typeinfos'] as $i => $typeinfo) {
		if (!is_array($typeinfo)) {
			fail("{$where} typeinfo {$i}: malformed");
		}
		$typeinfos[] = typeinfo($typeinfo, $count, "{$where} typeinfo {$i}");
	}

	$typeId = static function (string $key, bool $nullable = false) use ($protocol, $count, $where): ?int {
		$value = $protocol[$key] ?? null;
		if ($value === null && $nullable) {
			return null;
		}
		$value = checkInt($value, "{$where} {$key}");
		if ($value < 0 || $value >= $count) {
			fail("{$where} {$key}: type id {$value} out of range");
		}

		return $value;
	};

	$hasTracker = $protocol['tracker_event_types'] !== null;
	$scalars = array(
		'build' => $build,
		'svaruint32' => $typeId('svaruint32_typeid'),
		'userId' => $typeId('user_id_typeid'),
		'header' => $typeId('replay_header_typeid'),
		'details' => $typeId('game_details_typeid'),
		'initdata' => $typeId('replay_initdata_typeid'),
		'gameEventId' => $typeId('game_eventid_typeid'),
		'messageEventId' => $typeId('message_eventid_typeid'),
		'trackerEventId' => $typeId('tracker_eventid_typeid', !$hasTracker),
	);
	$maps = array(
		'gameEvents' => eventMap($protocol['game_event_types'], $count, "{$where} game events"),
		'messageEvents' => eventMap($protocol['message_event_types'], $count, "{$where} message events"),
		'trackerEvents' => eventMap($protocol['tracker_event_types'], $count, "{$where} tracker events"),
	);

	$body = array();
	foreach ($scalars as $key => $value) {
		$body[] = "\t'{$key}' => ".export($value).',';
	}
	foreach ($maps as $key => $map) {
		$body[] = "\t'{$key}' => ".($map === null ? 'null' : exportLines($map)).',';
	}
	$body[] = "\t'typeinfos' => ".exportLines($typeinfos, true).',';

	return fileHeader($head, $protocol)."\nreturn array(\n".implode("\n", $body)."\n);\n";
}

function main(array $argv): void {
	$output = $argv[1] ?? DEFAULT_OUTPUT;
	if (!is_dir($output) && !mkdir($output, 0o777, true)) {
		fail("cannot create {$output}");
	}
	$output = realpath($output);

	$head = null;
	$written = array();
	$builds = array();
	while (($line = fgets(\STDIN)) !== false) {
		if (trim($line) === '') {
			continue;
		}

		try {
			$data = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			fail('invalid JSON line: '.$e->getMessage());
		}
		if (!is_array($data)) {
			fail('every line must be a JSON object');
		}
		if ($head === null) {
			foreach (array('s2protocol', 'commit', 'legacy', 'legacyCommit') as $key) {
				if (!is_string($data[$key] ?? null)) {
					fail("first line: missing {$key}");
				}
			}
			$head = $data;

			continue;
		}

		$build = checkInt($data['build'] ?? null, 'build');
		if ($builds !== array() && $build <= end($builds)) {
			fail("protocols must come in ascending build order, {$build} after ".end($builds));
		}
		$name = sprintf('protocol%05d.php', $build);
		file_put_contents("{$output}/{$name}", render($head, $data));
		$written[$name] = true;
		$builds[] = $build;
	}

	if ($head === null || $builds === array()) {
		fail('no input; run it as: tools/.venv/bin/python tools/generate/protocols.py | php tools/generate/protocols.php');
	}

	$list = implode("\n", array_map(static fn (int $b): string => "\t{$b},", $builds));
	file_put_contents("{$output}/builds.php", fileHeader($head)."\nreturn array(\n{$list}\n);\n");

	$removed = 0;
	foreach (glob("{$output}/protocol*.php") ?: array() as $file) {
		if (!isset($written[basename($file)])) {
			unlink($file);
			$removed++;
		}
	}

	fwrite(\STDERR, sprintf("protocols.php: %d protocols (%d to %d) written to %s, %d stale removed\n",
		count($builds), $builds[0], end($builds), $output, $removed));
}

main($argv);
