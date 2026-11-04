<?php
/**
 * This file is part of the holonet sc2 replay parser library
 * (c) Matthias Lantsch.
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author  Matthias Lantsch <matthias.lantsch@bluewin.ch>
 */

namespace holonet\sc2utils\decoders;

use holonet\bitstream\format\BinaryFormatParser;
use holonet\bitstream\Stream;
use holonet\sc2utils\ParserException;
use holonet\sc2utils\resources\Replay;

/**
 * DecoderBase class just holding constructor and stream as well as a reference
 * to the replay object being parsed.
 */
abstract class DecoderBase {
	protected Replay $replay;

	public function __construct(
		protected Stream $stream
	) {}

	/**
	 * accepts the replay object and delegates the call to the child class.
	 * @return array with raw data (return value of the child method)
	 */
	public function decode(?Replay $replay = null): array {
		$this->replay = $replay;

		return $this->doDecode();
	}

	protected function binaryFormatParse(string $subfile): array {
		$formatTree = $this->replay->versionClass()::getFileFormat($subfile);
		$parser = new BinaryFormatParser($this->stream, $formatTree);
		$data = $parser->parse();

		if (!$this->stream->eof()) {
			throw new ParserException("Did not use all bytes while decoding {$subfile}", 1_000);
		}

		return $data;
	}

	/**
	 * force each decoder class to implement a doDecode method for the actual decoding process.
	 */
	abstract protected function doDecode(): void;
}
