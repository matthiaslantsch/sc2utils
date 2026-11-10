<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

use InvalidArgumentException;
use Stringable;

/**
 * A Battle.net account ("toon"): region id, realm and account id.
 */
final class Toon implements Stringable {
	/**
	 * sc2reader's profile URL template.
	 */
	private const string PROFILE_URL = 'https://starcraft2.com/en-us/profile/%d/%d/%d';

	public string $profileUrl {
		get => sprintf(self::PROFILE_URL, $this->region, $this->realm, $this->id);
	}

	/**
	 * @param int $region 1 us, 2 eu, 3 kr/tw, 5 cn, 6 sea, 98 public test
	 */
	public function __construct(
		public readonly int $region,
		public readonly int $realm,
		public readonly int $id,
	) {}

	/**
	 * The handle as the lobby slots have it, e.g. "2-S2-1-2513209".
	 */
	public function __toString(): string {
		return "{$this->region}-S2-{$this->realm}-{$this->id}";
	}

	/**
	 * @param array{m_region: int, m_realm: int, m_id: int} $toon the toon struct of replay.details
	 */
	public static function fromDetails(array $toon): self {
		return new self($toon['m_region'], $toon['m_realm'], $toon['m_id']);
	}

	public static function fromHandle(string $handle): self {
		if (preg_match('/^(\d+)-S2-(\d+)-(\d+)$/', $handle, $match) !== 1) {
			throw new InvalidArgumentException("Not a toon handle: '{$handle}'");
		}

		return new self((int)$match[1], (int)$match[2], (int)$match[3]);
	}
}
