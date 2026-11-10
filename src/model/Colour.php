<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\model;

final class Colour {
	/**
	 * The standard player colours by RGB hex (sc2reader's COLOR_CODES).
	 */
	private const array NAMES = array(
		'B4141E' => 'Red',
		'0042FF' => 'Blue',
		'1CA7EA' => 'Teal',
		'EBE129' => 'Yellow',
		'540081' => 'Purple',
		'FE8A0E' => 'Orange',
		'168000' => 'Green',
		'CCA6FC' => 'Light Pink',
		'1F01C9' => 'Violet',
		'525494' => 'Light Grey',
		'106246' => 'Dark Green',
		'4E2A04' => 'Brown',
		'96FF91' => 'Light Green',
		'232323' => 'Dark Grey',
		'E55BB0' => 'Pink',
		'FFFFFF' => 'White',
		'000000' => 'Black',
	);

	public string $hex {
		get => sprintf('%02X%02X%02X', $this->r, $this->g, $this->b);
	}

	public string $name {
		get => self::NAMES[$this->hex] ?? $this->hex;
	}

	public function __construct(
		public readonly int $r,
		public readonly int $g,
		public readonly int $b,
		public readonly int $a = 255,
	) {}

	/**
	 * @param array{m_r: int, m_g: int, m_b: int, m_a: int} $colour the colour struct of replay.details
	 */
	public static function fromDetails(array $colour): self {
		return new self($colour['m_r'], $colour['m_g'], $colour['m_b'], $colour['m_a']);
	}
}
