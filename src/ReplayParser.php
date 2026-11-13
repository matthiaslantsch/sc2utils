<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\decode\EventBuilder;
use holonet\sc2utils\decode\LegacyHeader;
use holonet\sc2utils\decode\ReplayMapper;
use holonet\sc2utils\gamedata\Attributes;
use holonet\sc2utils\model\event\message\ChatEvent;
use holonet\sc2utils\model\event\message\PingEvent;
use holonet\sc2utils\model\event\tracker\PlayerStatsEvent;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\model\simulation\PerPlayer;
use holonet\sc2utils\model\simulation\PlayerStatsSeries;
use holonet\sc2utils\model\simulation\Simulation;
use holonet\sc2utils\protocol\DecodeException;
use holonet\sc2utils\protocol\ProtocolRegistry;
use holonet\sc2utils\protocol\UnsupportedBuildException;
use holonet\sc2utils\protocol\VersionedCodec;
use holonet\sc2utils\simulate\Engine;
use holonet\sc2utils\simulate\Plugin;

class ReplayParser {
	public function __construct(
		private readonly ProtocolRegistry $registry = new ProtocolRegistry(),
	) {}

	/**
	 * The chat messages and pings of a replay in order.
	 * @throws UnsupportedBuildException if the message events don't decode
	 * @return list<ChatEvent|PingEvent>
	 */
	public function messages(Replay $replay, ReplayFile $file): array {
		if ($file->header->legacyFormat || !$file->has('replay.message.events')) {
			return array();
		}
		$builder = EventBuilder::for($replay);
		$ret = array();

		try {
			foreach ($file->messageEvents() as $raw) {
				$class = EventBuilder::classOf('message', (string)$raw['_event']);
				if ($class === ChatEvent::class || $class === PingEvent::class) {
					/** @var ChatEvent|PingEvent $event */
					$event = $builder->message($raw);
					$ret[] = $event;
				}
			}
		} catch (DecodeException $e) {
			throw new UnsupportedBuildException("Build {$file->header->version} doesn't decode with protocol {$file->protocol->build}: {$e->getMessage()}", previous: $e);
		}

		return $ret;
	}

	/**
	 * Reads the header and picks the protocol, nothing else. Every file with a replay header opens.
	 * @throws DecodeException
	 */
	public function open(string $path): ReplayFile {
		$archive = MpqArchive::open($path);
		$userData = $archive->userData?->content;
		if ($userData === null) {
			throw new DecodeException("{$path} has no replay header (no MPQ user data)");
		}

		if (LegacyHeader::matches($userData)) {
			// the WoL betas before 0.19 are older than every protocol, but 0.11 to 0.17 have details the oldest one reads
			$header = LegacyHeader::fromBytes($userData)->header();
			$protocol = $this->registry->load($this->registry->builds()[0]);
		} else {
			$latest = $this->registry->latest();
			/** @var array<string, mixed> $raw */
			$raw = new VersionedCodec($latest, $userData)->decode($latest->header);
			$header = ReplayMapper::header($raw);
			$protocol = $this->registry->forBuild($header->version->baseBuild);
		}

		return new ReplayFile($archive, $header, $protocol);
	}

	/**
	 * Every player's stats samples, without the engine; empty for replays without tracker events (before 2.0.8).
	 * @throws UnsupportedBuildException if the tracker events don't decode
	 * @return PerPlayer<PlayerStatsSeries>
	 */
	public function playerStats(Replay $replay, ReplayFile $file): PerPlayer {
		if ($file->header->legacyFormat || $file->protocol->trackerEvents === null || !$file->has('replay.tracker.events')) {
			return new PerPlayer();
		}
		$builder = EventBuilder::for($replay);
		$samples = array();

		try {
			foreach ($file->trackerEvents() as $raw) {
				if (EventBuilder::classOf('tracker', (string)$raw['_event']) === PlayerStatsEvent::class) {
					/** @var PlayerStatsEvent $event */
					$event = $builder->tracker($raw);
					$samples[$event->playerId][] = $event;
				}
			}
		} catch (DecodeException $e) {
			throw new UnsupportedBuildException("Build {$file->header->version} doesn't decode with protocol {$file->protocol->build}: {$e->getMessage()}", 0, $e);
		}
		$entries = array();
		foreach ($replay->players as $player) {
			if (isset($samples[$player->pid])) {
				$entries[$player->pid] = array($player, new PlayerStatsSeries($samples[$player->pid]));
			}
		}

		return new PerPlayer($entries);
	}

	/**
	 * The domain model from the data contained in the given replay file.
	 * @throws UnsupportedBuildException if a file doesn't decode, which means a missing protocol
	 */
	public function replay(ReplayFile $file): Replay {
		try {
			$details = $file->has('replay.details') ? $file->details() : $file->legacyInfo()?->toDetails();
			if ($details === null) {
				throw new DecodeException('The file has neither replay.details nor replay.info');
			}

			return ReplayMapper::replay(
				$file->header,
				$details,
				$file->header->legacyFormat ? null : $file->initdata(),
				Attributes::resolve($file->attributes()),
			);
		} catch (DecodeException $e) {
			throw new UnsupportedBuildException("Build {$file->header->version} doesn't decode with protocol {$file->protocol->build}: {$e->getMessage()}", 0, $e);
		}
	}

	/**
	 * Runs the replay through the engine: the unit model (always) and the given plugins.
	 * @param list<Plugin>|null $plugins in registration order (dependencies first); null for the default plugins
	 * @throws UnsupportedBuildException if a file doesn't decode, which means a missing protocol
	 */
	public function simulate(ReplayFile $file, ?array $plugins = null, ?Replay $replay = null): Simulation {
		$replay ??= $this->replay($file);

		try {
			return new Engine()->run($replay, $file, $plugins ?? Engine::defaultPlugins());
		} catch (DecodeException $e) {
			throw new UnsupportedBuildException("Build {$file->header->version} doesn't decode with protocol {$file->protocol->build}: {$e->getMessage()}", 0, $e);
		}
	}
}
