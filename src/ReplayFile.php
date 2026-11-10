<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils;

use Generator;
use holonet\sc2utils\archive\MpqArchive;
use holonet\sc2utils\decode\AttributeEntry;
use holonet\sc2utils\decode\AttributesFile;
use holonet\sc2utils\decode\ReplayInfoFile;
use holonet\sc2utils\model\ReplayHeader;
use holonet\sc2utils\protocol\BitPackedCodec;
use holonet\sc2utils\protocol\DecodeException;
use holonet\sc2utils\protocol\EventStream;
use holonet\sc2utils\protocol\Protocol;
use holonet\sc2utils\protocol\VersionedCodec;

/**
 * An open replay file (ReplayParser::open()): the archive, its header, the protocol for the rest, and the decoded
 * files, raw as s2protocol decodes them.
 *
 * Every method throws DecodeException if the file is missing or the protocol doesn't fit it.
 *
 * @psalm-type Details = array{m_title: string, m_playerList: list<array<string, mixed>>, m_cacheHandles: list<string>, m_timeUTC?: int, m_timeLocalOffset?: int, m_gameSpeed?: int, ...<string, mixed>}
 * @psalm-type InitData = array{m_syncLobbyState: array{m_userInitialData: list<array<string, mixed>>, m_gameDescription: array<string, mixed>, m_lobbyState: array<string, mixed>}, ...<string, mixed>}
 */
class ReplayFile {
	/**
	 * @var list<AttributeEntry>|null
	 */
	private ?array $attributes = null;

	/**
	 * @var Details|null
	 */
	private ?array $details = null;

	/**
	 * @var InitData|null
	 */
	private ?array $initdata = null;

	private ?ReplayInfoFile $legacyInfo = null;

	/**
	 * @param Protocol $protocol the nearest protocol not above the base build; the oldest one (15405) for the WoL betas
	 */
	public function __construct(
		private readonly MpqArchive $archive,
		public readonly ReplayHeader $header,
		public readonly Protocol $protocol,
	) {}

	/**
	 * replay.attributes.events, empty if the file has none.
	 *
	 * @return list<AttributeEntry>
	 */
	public function attributes(): array {
		return $this->attributes ??= AttributesFile::read(
			$this->has('replay.attributes.events') ? $this->read('replay.attributes.events') : '',
			$this->header->version->baseBuild,
		)->entries;
	}

	/**
	 * replay.details. A field the protocol doesn't know (skipped) means the protocol doesn't fit.
	 *
	 * @return Details
	 */
	public function details(): array {
		if ($this->details === null) {
			$codec = new VersionedCodec($this->protocol, $this->read('replay.details'));
			/** @var Details $details */
			$details = $codec->decode($this->protocol->details);
			if (!$codec->atEnd() || $codec->skipped() > 0) {
				throw new DecodeException("Protocol {$this->protocol->build} does not fit replay.details of build {$this->header->version}");
			}
			$this->details = $details;
		}

		return $this->details;
	}

	/**
	 * @return Generator<int, array<string, mixed>>
	 */
	public function gameEvents(): Generator {
		return EventStream::game($this->protocol, $this->read('replay.game.events'));
	}

	/**
	 * Whether the archive has the file or its ".backup" copy.
	 */
	public function has(string $name): bool {
		return $this->archive->has($name) || $this->archive->has("{$name}.backup");
	}

	/**
	 * replay.initData. No protocol reads it for the WoL betas before 0.19.
	 *
	 * @return InitData
	 */
	public function initdata(): array {
		/** @var InitData */
		return $this->initdata ??= new BitPackedCodec($this->protocol, $this->read('replay.initData'))->decode($this->protocol->initdata);
	}

	/**
	 * replay.info, the only metadata file of the WoL betas 0.2 to 0.10; null for every other replay.
	 */
	public function legacyInfo(): ?ReplayInfoFile {
		if ($this->legacyInfo === null && $this->archive->has('replay.info')) {
			$this->legacyInfo = ReplayInfoFile::fromBytes($this->archive->read('replay.info'));
		}

		return $this->legacyInfo;
	}

	/**
	 * @return Generator<int, array<string, mixed>>
	 */
	public function messageEvents(): Generator {
		return EventStream::message($this->protocol, $this->read('replay.message.events'));
	}

	/**
	 * A file of the archive, or its ".backup" copy if the file itself is missing: Blizzard's anonymised replay packs
	 * keep only replay.details.backup and replay.initData.backup (sc2reader reads them the same way).
	 */
	public function read(string $name): string {
		return $this->archive->has($name) ? $this->archive->read($name) : $this->archive->read("{$name}.backup");
	}

	/**
	 * @return Generator<int, array<string, mixed>>
	 */
	public function trackerEvents(): Generator {
		return EventStream::tracker($this->protocol, $this->read('replay.tracker.events'));
	}
}
