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
use holonet\sc2utils\decode\LegacyHeader;
use holonet\sc2utils\decode\ReplayMapper;
use holonet\sc2utils\gamedata\Attributes;
use holonet\sc2utils\model\replay\Replay;
use holonet\sc2utils\protocol\DecodeException;
use holonet\sc2utils\protocol\ProtocolRegistry;
use holonet\sc2utils\protocol\UnsupportedBuildException;
use holonet\sc2utils\protocol\VersionedCodec;

class ReplayParser {
	public function __construct(
		private readonly ProtocolRegistry $registry = new ProtocolRegistry(),
	) {}

	/**
	 * Reads the header and picks the protocol, nothing else. Every file with a replay header opens.
	 *
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
}
