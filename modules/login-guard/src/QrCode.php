<?php
/**
 * Minimal QR code encoder for the two-factor setup screen.
 *
 * @package DovetailStudio\LoginGuard
 */

namespace DovetailStudio\LoginGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Encodes text as a QR code (byte mode, error correction level M, versions
 * 1 to 10, up to 213 bytes) and draws it as inline SVG.
 *
 * It exists so the two-factor secret never leaves the server: sending the
 * otpauth:// URI to an online QR service would hand the secret to a third
 * party. The algorithm follows ISO/IEC 18004.
 */
final class QrCode {

	/**
	 * Level M block structure per version: [ EC codewords per block, [ [ blocks, data codewords ], … ] ].
	 */
	const BLOCKS = array(
		1  => array( 10, array( array( 1, 16 ) ) ),
		2  => array( 16, array( array( 1, 28 ) ) ),
		3  => array( 26, array( array( 1, 44 ) ) ),
		4  => array( 18, array( array( 2, 32 ) ) ),
		5  => array( 24, array( array( 2, 43 ) ) ),
		6  => array( 16, array( array( 4, 27 ) ) ),
		7  => array( 18, array( array( 4, 31 ) ) ),
		8  => array( 22, array( array( 2, 38 ), array( 2, 39 ) ) ),
		9  => array( 22, array( array( 3, 36 ), array( 2, 37 ) ) ),
		10 => array( 26, array( array( 4, 43 ), array( 1, 44 ) ) ),
	);

	/** Alignment pattern centres per version. */
	const ALIGNMENT = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	/** Remainder bits after the codewords, per version. */
	const REMAINDER = array(
		1  => 0,
		2  => 7,
		3  => 7,
		4  => 7,
		5  => 7,
		6  => 7,
		7  => 0,
		8  => 0,
		9  => 0,
		10 => 0,
	);

	/** @var int */
	private $size = 0;

	/** @var array<int,array<int,bool>> modules[y][x], true = dark. */
	private $modules = array();

	/** @var array<int,array<int,bool>> Function pattern map. */
	private $function = array();

	/**
	 * Encode text into a module matrix.
	 *
	 * @param string $text Text (UTF-8), at most 213 bytes.
	 * @return array<int,array<int,bool>> Rows of modules, true = dark.
	 * @throws \InvalidArgumentException When the text is too long.
	 */
	public static function matrix( string $text ): array {
		$qr = new self();

		return $qr->encode( $text );
	}

	/**
	 * Draw text as an SVG QR code.
	 *
	 * @param string $text  Text to encode.
	 * @param string $label Accessible name of the image.
	 * @param int    $scale Pixels per module.
	 * @return string SVG markup.
	 */
	public static function svg( string $text, string $label, int $scale = 4 ): string {
		$matrix = self::matrix( $text );
		$size   = count( $matrix );
		$full   = $size + 8;
		$path   = '';

		foreach ( $matrix as $y => $row ) {
			foreach ( $row as $x => $dark ) {
				if ( $dark ) {
					$path .= 'M' . ( $x + 4 ) . ' ' . ( $y + 4 ) . 'h1v1h-1z';
				}
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr( $label ) . '" width="' . ( $full * $scale ) . '" height="' . ( $full * $scale ) . '" viewBox="0 0 ' . $full . ' ' . $full . '" shape-rendering="crispEdges">'
			. '<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
	}

	/**
	 * Build the symbol.
	 *
	 * @param string $text Text.
	 * @return array<int,array<int,bool>>
	 * @throws \InvalidArgumentException When the text is too long.
	 */
	private function encode( string $text ): array {
		$length  = strlen( $text );
		$version = 0;

		foreach ( self::BLOCKS as $v => $spec ) {
			$capacity = $this->data_codewords( $v ) * 8 - 4 - ( $v < 10 ? 8 : 16 );

			if ( $length * 8 <= $capacity ) {
				$version = $v;

				break;
			}
		}

		if ( 0 === $version ) {
			throw new \InvalidArgumentException( 'Text too long for the QR code.' );
		}

		$this->size     = $version * 4 + 17;
		$this->modules  = array_fill( 0, $this->size, array_fill( 0, $this->size, false ) );
		$this->function = $this->modules;

		$this->draw_function_patterns( $version );
		$this->draw_codewords( $this->codewords( $text, $version ), self::REMAINDER[ $version ] );

		$best    = 0;
		$penalty = PHP_INT_MAX;

		for ( $mask = 0; $mask < 8; $mask++ ) {
			$this->apply_mask( $mask );
			$this->draw_format( $mask );
			$score = $this->penalty();

			if ( $score < $penalty ) {
				$best    = $mask;
				$penalty = $score;
			}

			$this->apply_mask( $mask ); // XOR again to undo.
		}

		$this->apply_mask( $best );
		$this->draw_format( $best );

		return $this->modules;
	}

	/**
	 * Data codewords of a version at level M.
	 *
	 * @param int $version Version.
	 * @return int
	 */
	private function data_codewords( int $version ): int {
		$total = 0;

		foreach ( self::BLOCKS[ $version ][1] as $group ) {
			$total += $group[0] * $group[1];
		}

		return $total;
	}

	/**
	 * Data and error correction codewords, interleaved.
	 *
	 * @param string $text    Text.
	 * @param int    $version Version.
	 * @return int[]
	 */
	private function codewords( string $text, int $version ): array {
		$bits = '0100' . str_pad( decbin( strlen( $text ) ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );

		foreach ( str_split( $text ) as $char ) {
			$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		$capacity = $this->data_codewords( $version ) * 8;
		$bits    .= str_repeat( '0', min( 4, $capacity - strlen( $bits ) ) );
		$bits    .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
		$data     = array_map( 'bindec', str_split( $bits, 8 ) );

		$needed = intdiv( $capacity, 8 );

		for ( $pad = 0xEC, $have = count( $data ); $have < $needed; $pad ^= 0xEC ^ 0x11, $have++ ) {
			$data[] = $pad;
		}

		// Split into blocks and compute each block's error correction.
		$ec_len = self::BLOCKS[ $version ][0];
		$blocks = array();
		$ecc    = array();
		$offset = 0;

		foreach ( self::BLOCKS[ $version ][1] as $group ) {
			for ( $b = 0; $b < $group[0]; $b++ ) {
				$block    = array_slice( $data, $offset, $group[1] );
				$offset  += $group[1];
				$blocks[] = $block;
				$ecc[]    = $this->reed_solomon( $block, $ec_len );
			}
		}

		$out = array();
		$max = max( array_map( 'count', $blocks ) );

		for ( $i = 0; $i < $max; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block[ $i ] ) ) {
					$out[] = $block[ $i ];
				}
			}
		}

		for ( $i = 0; $i < $ec_len; $i++ ) {
			foreach ( $ecc as $block ) {
				$out[] = $block[ $i ];
			}
		}

		return $out;
	}

	/**
	 * Multiply in GF(256) with the QR polynomial 0x11D.
	 *
	 * @param int $a Factor.
	 * @param int $b Factor.
	 * @return int
	 */
	private function gf_multiply( int $a, int $b ): int {
		$result = 0;

		for ( $i = 7; $i >= 0; $i-- ) {
			$result  = ( $result << 1 ) ^ ( ( $result >> 7 ) * 0x11D );
			$result ^= ( ( $b >> $i ) & 1 ) * $a;
		}

		return $result & 0xFF;
	}

	/**
	 * Reed-Solomon error correction codewords of a block.
	 *
	 * @param int[] $data   Data codewords.
	 * @param int   $degree Number of error correction codewords.
	 * @return int[]
	 */
	private function reed_solomon( array $data, int $degree ): array {
		// Generator polynomial (x - 2^0)(x - 2^1)…, highest coefficient dropped.
		$generator                = array_fill( 0, $degree, 0 );
		$generator[ $degree - 1 ] = 1;
		$root                     = 1;

		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$generator[ $j ] = $this->gf_multiply( $generator[ $j ], $root );

				if ( $j + 1 < $degree ) {
					$generator[ $j ] ^= $generator[ $j + 1 ];
				}
			}

			$root = $this->gf_multiply( $root, 0x02 );
		}

		$result = array_fill( 0, $degree, 0 );

		foreach ( $data as $byte ) {
			$factor   = $byte ^ array_shift( $result );
			$result[] = 0;

			foreach ( $generator as $k => $coefficient ) {
				$result[ $k ] ^= $this->gf_multiply( $coefficient, $factor );
			}
		}

		return $result;
	}

	/**
	 * Set a function module.
	 *
	 * @param int  $x    Column.
	 * @param int  $y    Row.
	 * @param bool $dark Colour.
	 * @return void
	 */
	private function set_function( int $x, int $y, bool $dark ): void {
		$this->modules[ $y ][ $x ]  = $dark;
		$this->function[ $y ][ $x ] = true;
	}

	/**
	 * Timing, finder and alignment patterns, and the reserved format and version areas.
	 *
	 * @param int $version Version.
	 * @return void
	 */
	private function draw_function_patterns( int $version ): void {
		for ( $i = 0; $i < $this->size; $i++ ) {
			$this->set_function( 6, $i, 0 === $i % 2 );
			$this->set_function( $i, 6, 0 === $i % 2 );
		}

		foreach ( array( array( 3, 3 ), array( $this->size - 4, 3 ), array( 3, $this->size - 4 ) ) as $centre ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$x    = $centre[0] + $dx;
					$y    = $centre[1] + $dy;
					$dist = max( abs( $dx ), abs( $dy ) );

					if ( $x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size ) {
						$this->set_function( $x, $y, 2 !== $dist && 4 !== $dist );
					}
				}
			}
		}

		$positions = self::ALIGNMENT[ $version ];
		$count     = count( $positions );

		foreach ( $positions as $i => $cy ) {
			foreach ( $positions as $j => $cx ) {
				// Skip the three corners taken by the finder patterns.
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $count - 1 === $j ) || ( $count - 1 === $i && 0 === $j ) ) {
					continue;
				}

				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$ring = max( abs( $dx ), abs( $dy ) );

						$this->set_function( $cx + $dx, $cy + $dy, 1 !== $ring );
					}
				}
			}
		}

		$this->draw_format( 0 ); // Reserves the area; redrawn with the chosen mask.

		if ( $version >= 7 ) {
			$rem = $version;

			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}

			$bits = ( $version << 12 ) | $rem;

			for ( $i = 0; $i < 18; $i++ ) {
				$dark = 1 === ( ( $bits >> $i ) & 1 );
				$a    = $this->size - 11 + $i % 3;
				$b    = intdiv( $i, 3 );
				$this->set_function( $a, $b, $dark );
				$this->set_function( $b, $a, $dark );
			}
		}
	}

	/**
	 * Format information (level M and the mask), both copies, and the dark module.
	 *
	 * @param int $mask Mask pattern.
	 * @return void
	 */
	private function draw_format( int $mask ): void {
		$data = ( 0 << 3 ) | $mask; // Level M is 00.
		$rem  = $data;

		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}

		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		/**
		 * Bit i of the format information.
		 *
		 * @param int $i Bit index.
		 * @return bool
		 */
		$bit = static function ( int $i ) use ( $bits ): bool {
			return 1 === ( ( $bits >> $i ) & 1 );
		};

		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set_function( 8, $i, $bit( $i ) );
		}

		$this->set_function( 8, 7, $bit( 6 ) );
		$this->set_function( 8, 8, $bit( 7 ) );
		$this->set_function( 7, 8, $bit( 8 ) );

		for ( $i = 9; $i < 15; $i++ ) {
			$this->set_function( 14 - $i, 8, $bit( $i ) );
		}

		for ( $i = 0; $i < 8; $i++ ) {
			$this->set_function( $this->size - 1 - $i, 8, $bit( $i ) );
		}

		for ( $i = 8; $i < 15; $i++ ) {
			$this->set_function( 8, $this->size - 15 + $i, $bit( $i ) );
		}

		$this->set_function( 8, $this->size - 8, true );
	}

	/**
	 * Place the codewords in the zigzag order, skipping function modules.
	 *
	 * @param int[] $codewords Codewords.
	 * @param int   $remainder Remainder bits (light).
	 * @return void
	 */
	private function draw_codewords( array $codewords, int $remainder ): void {
		$total = count( $codewords ) * 8 + $remainder;
		$i     = 0;

		for ( $right = $this->size - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}

			for ( $vert = 0; $vert < $this->size; $vert++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x      = $right - $j;
					$upward = 0 === ( ( $right + 1 ) & 2 );
					$y      = $upward ? $this->size - 1 - $vert : $vert;

					if ( ! $this->function[ $y ][ $x ] && $i < $total ) {
						$byte = $codewords[ $i >> 3 ] ?? 0;

						$this->modules[ $y ][ $x ] = 1 === ( ( $byte >> ( 7 - ( $i & 7 ) ) ) & 1 );
						++$i;
					}
				}
			}
		}
	}

	/**
	 * XOR a mask pattern over the data modules (applying it twice undoes it).
	 *
	 * @param int $mask Mask pattern 0–7.
	 * @return void
	 */
	private function apply_mask( int $mask ): void {
		for ( $y = 0; $y < $this->size; $y++ ) {
			for ( $x = 0; $x < $this->size; $x++ ) {
				if ( $this->function[ $y ][ $x ] ) {
					continue;
				}

				switch ( $mask ) {
					case 0:
						$invert = 0 === ( $x + $y ) % 2;

						break;
					case 1:
						$invert = 0 === $y % 2;

						break;
					case 2:
						$invert = 0 === $x % 3;

						break;
					case 3:
						$invert = 0 === ( $x + $y ) % 3;

						break;
					case 4:
						$invert = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2;

						break;
					case 5:
						$invert = 0 === $x * $y % 2 + $x * $y % 3;

						break;
					case 6:
						$invert = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2;

						break;
					default:
						$invert = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}

				if ( $invert ) {
					$this->modules[ $y ][ $x ] = ! $this->modules[ $y ][ $x ];
				}
			}
		}
	}

	/**
	 * Penalty score of the current symbol (lower is easier to scan).
	 *
	 * @return int
	 */
	private function penalty(): int {
		$score = 0;
		$dark  = 0;
		$lines = array();

		for ( $y = 0; $y < $this->size; $y++ ) {
			$row    = '';
			$column = '';

			for ( $x = 0; $x < $this->size; $x++ ) {
				$row    .= $this->modules[ $y ][ $x ] ? '1' : '0';
				$column .= $this->modules[ $x ][ $y ] ? '1' : '0';
				$dark   += $this->modules[ $y ][ $x ] ? 1 : 0;

				// Rule 2: 2×2 blocks of one colour.
				if ( $x < $this->size - 1 && $y < $this->size - 1 ) {
					$c = $this->modules[ $y ][ $x ];

					if ( $c === $this->modules[ $y ][ $x + 1 ] && $c === $this->modules[ $y + 1 ][ $x ] && $c === $this->modules[ $y + 1 ][ $x + 1 ] ) {
						$score += 3;
					}
				}
			}

			$lines[] = $row;
			$lines[] = $column;
		}

		foreach ( $lines as $line ) {
			// Rule 1: runs of five or more modules of one colour.
			if ( preg_match_all( '/0{5,}|1{5,}/', $line, $runs ) ) {
				foreach ( $runs[0] as $run ) {
					$score += strlen( $run ) - 2;
				}
			}

			// Rule 3: patterns that look like finder patterns.
			$score += 40 * ( substr_count( $line, '10111010000' ) + substr_count( $line, '00001011101' ) );
		}

		// Rule 4: balance of dark and light modules.
		$total  = $this->size * $this->size;
		$score += 10 * intdiv( abs( $dark * 20 - $total * 10 ), $total );

		return $score;
	}
}
