<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\gamedata;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A Battle.net ladder season, e.g. "2026 Season 3". Dates from https://liquipedia.net/starcraft2/Battle.net_Seasons.
 */
class Season {
	/**
	 * number, start, lock, close (UTC dates), the year being the start's; a season without a close ends where the next
	 * one starts.
	 */
	private const array SEASONS = array(
		// beta
		array(1, '2010-02-17', null, '2010-03-25'),
		array(2, '2010-03-25', null, '2010-04-24'),
		array(3, '2010-04-24', null, '2010-05-22'),
		array(4, '2010-05-22', null, '2010-06-04'),
		array(5, '2010-06-04', null, '2010-06-07'),
		array(6, '2010-07-07', null, '2010-07-19'),
		// release
		array(7, '2010-07-27', '2011-03-22', '2011-03-29'),
		array(1, '2011-03-29', '2011-07-05', '2011-07-26'),
		array(2, '2011-07-26', '2011-10-10', '2011-10-24'),
		array(3, '2011-10-24', '2011-12-13', '2011-12-19'),
		array(4, '2011-12-19', '2012-02-14', '2012-02-20'),
		array(1, '2012-02-20', '2012-04-03', '2012-04-09'),
		array(2, '2012-04-09', '2012-06-05', '2012-06-11'),
		array(3, '2012-06-11', '2012-09-04', '2012-09-11'),
		array(4, '2012-09-11', '2012-10-24', '2012-11-01'),
		array(5, '2012-11-01', '2012-12-27', '2013-01-03'),
		array(1, '2013-01-03', '2013-03-04', '2013-03-11'),
		array(2, '2013-03-11', '2013-04-24', '2013-05-01'),
		array(3, '2013-05-01', '2013-06-03', '2013-06-10'),
		array(4, '2013-06-10', '2013-08-19', '2013-08-26'),
		array(5, '2013-08-26', '2013-11-04', '2013-11-11'),
		array(6, '2013-11-11', '2013-12-27', '2014-01-03'),
		array(1, '2014-01-03', '2014-04-07', '2014-04-14'),
		array(2, '2014-04-14', '2014-06-23', '2014-07-07'),
		array(3, '2014-07-07', '2014-11-04', '2014-11-10'),
		array(4, '2014-11-10', '2015-01-05', '2015-01-12'),
		array(1, '2015-01-12', '2015-04-06', '2015-04-13'),
		array(2, '2015-04-13', '2015-06-22', '2015-06-29'),
		array(3, '2015-06-29', '2015-11-03', '2015-11-09'),
		array(4, '2015-11-09', '2016-01-24', '2016-01-31'),
		array(1, '2016-01-31', '2016-03-21', '2016-03-28'),
		array(2, '2016-03-28', '2016-06-13', '2016-06-20'),
		array(3, '2016-06-20', '2016-07-09', '2016-07-12'),
		array(4, '2016-07-12', '2016-10-11', '2016-10-18'),
		array(5, '2016-10-18', '2016-11-15', '2016-11-22'),
		array(6, '2016-11-22', '2017-01-17', '2017-01-24'),
		array(1, '2017-01-24', '2017-04-25', '2017-05-02'),
		array(2, '2017-05-02', null, null),
		array(3, '2017-07-19', null, null),
		// Liquipedia: "after 19 October 2017"
		array(4, '2017-10-19', null, null),
		array(1, '2018-01-23', '2018-05-08', '2018-05-15'),
		array(2, '2018-05-15', '2018-08-07', '2018-08-14'),
		array(3, '2018-08-14', '2018-11-13', '2018-11-20'),
		array(4, '2018-11-20', '2019-01-16', '2019-01-22'),
		array(1, '2019-01-22', '2019-05-15', '2019-05-22'),
		array(2, '2019-05-22', '2019-08-14', '2019-08-22'),
		array(3, '2019-08-22', null, '2019-11-25'),
		array(4, '2019-11-26', '2020-03-10', '2020-03-17'),
		array(1, '2020-03-17', '2020-06-03', '2020-06-10'),
		array(2, '2020-06-10', '2020-09-24', '2020-09-30'),
		array(3, '2020-09-30', '2021-01-19', '2021-01-26'),
		array(1, '2021-01-26', null, '2021-04-06'),
		array(2, '2021-04-06', '2021-07-12', '2021-07-19'),
		array(3, '2021-07-19', null, '2021-11-10'),
		array(4, '2021-11-10', null, '2022-01-05'),
		array(1, '2022-01-05', null, '2022-03-31'),
		array(2, '2022-03-31', '2022-07-11', '2022-07-18'),
		array(3, '2022-07-18', null, '2022-11-10'),
		array(4, '2022-11-10', null, '2023-01-05'),
		array(1, '2023-01-05', null, '2023-04-01'),
		array(2, '2023-04-01', null, '2023-07-19'),
		array(3, '2023-07-19', null, '2023-11-10'),
		array(4, '2023-11-10', null, '2024-01-05'),
		array(1, '2024-01-05', '2024-03-25', '2024-04-01'),
		array(2, '2024-04-01', null, '2024-07-19'),
		array(3, '2024-07-19', '2024-11-03', '2024-11-10'),
		array(4, '2024-11-10', '2024-12-29', '2025-01-05'),
		array(1, '2025-01-05', '2025-03-25', '2025-04-01'),
		array(2, '2025-04-01', '2025-07-12', '2025-07-19'),
		array(3, '2025-07-19', '2025-11-03', '2025-11-10'),
		array(4, '2025-11-10', '2025-12-29', '2026-01-05'),
		array(1, '2026-01-05', '2026-03-25', '2026-04-01'),
		array(2, '2026-04-01', '2026-07-12', '2026-07-19'),
		array(3, '2026-07-20', '2026-11-02', '2026-11-09'),
		array(4, '2026-11-10', null, null),
	);

	/**
	 * @param int $number within the year
	 * @param DateTimeImmutable $start UTC midnight
	 * @param DateTimeImmutable|null $lock when league placements froze, if known
	 * @param DateTimeImmutable|null $close when the season ended, if known; null for the newest
	 */
	public function __construct(
		public int $year,
		public int $number,
		public DateTimeImmutable $start,
		public ?DateTimeImmutable $lock,
		public ?DateTimeImmutable $close,
	) {}

	/**
	 * @return list<static>
	 */
	public static function all(): array {
		return array_map(fn ($season) => static::make(...$season), static::SEASONS);
	}

	public static function at(DateTimeImmutable $time): ?self {
		$seasons = self::all();
		foreach ($seasons as $i => $season) {
			$end = $season->close ?? ($seasons[$i + 1] ?? null)?->start;
			if ($time >= $season->start && ($end === null || $time < $end)) {
				return $season;
			}
		}

		return null;
	}

	public function name(): string {
		return "{$this->year} Season {$this->number}";
	}

	private static function make(int $number, string $start, ?string $lock, ?string $close): self {
		$utc = new DateTimeZone('UTC');
		$start = new DateTimeImmutable($start, $utc);
		$lock = $lock !== null ? new DateTimeImmutable($lock, $utc) : $lock;
		$close = $close !== null ? new DateTimeImmutable($close, $utc) : $close;

		return new static((int)$start->format('Y'), $number, $start, $lock, $close);
	}
}
