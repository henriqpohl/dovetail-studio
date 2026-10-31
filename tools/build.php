<?php
/**
 * Build step for Dovetail Studio. No npm needed.
 *
 *     php tools/build.php
 *
 * 1. Extracts the translatable strings of the Virtual Assistant front.js into
 *    modules/virtual-assistant/src/services/i18n-js.php, so they are translated
 *    with the plugin's .po files like any PHP string.
 * 2. Writes languages/dovetail-studio.pot from every __(), _e(), esc_html__() …
 *    call with the 'dovetail-studio' text domain.
 * 3. Compiles every languages/*.po into its .mo (no gettext tools needed).
 * 4. Writes the minified front-end assets listed in $files below (add new
 *    module scripts and styles there).
 *
 * Run it in the repository before committing; sites never run it, and tools/
 * is left out of the plugin ZIP (.distignore).
 *
 * No npm needed. The minifiers are deliberately conservative: comments and
 * indentation go, line breaks stay (so JavaScript's automatic semicolon
 * insertion behaves exactly as in the source), strings and regular
 * expressions are copied untouched. The plugin serves a .min file only when
 * it is at least as new as its source, so a forgotten rebuild falls back to
 * the readable file instead of shipping stale code.
 *
 * @package DovetailStudio
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );

dovetail_build_js_strings( $root );
dovetail_build_pot( $root );

foreach ( glob( $root . '/languages/*.po' ) as $po ) {
	dovetail_build_mo( $po );
}

$files = array(
	'modules/virtual-assistant/assets/js/front.js'        => 'js',
	'modules/virtual-assistant/assets/js/front-loader.js' => 'js',
	'modules/virtual-assistant/assets/css/front.css'      => 'css',
	'modules/virtual-assistant/assets/css/front-tab.css'  => 'css',
	'modules/login-guard/assets/js/recaptcha.js'          => 'js',
	'assets/admin/dovetail-admin.js'                      => 'js',
	'modules/virtual-assistant/assets/js/admin-json.js'   => 'js',
	'modules/virtual-assistant/assets/js/admin-doors.js'  => 'js',
);

foreach ( $files as $relative => $type ) {
	$source = $root . '/' . $relative;
	$target = preg_replace( '/\.(js|css)$/', '.min.$1', $source );
	$input  = (string) file_get_contents( $source );
	$output = 'js' === $type ? dovetail_minify_js( $input ) : dovetail_minify_css( $input );
	file_put_contents( $target, $output );
	touch( $target, max( filemtime( $source ), time() ) );
	printf( "%-58s %7d -> %7d bytes\n", $relative, strlen( $input ), strlen( $output ) );
}

/**
 * Remove comments and redundant whitespace from JavaScript, keeping line breaks.
 *
 * @param string $src Source.
 * @return string
 */
function dovetail_minify_js( $src ) {
	$out         = '';
	$len         = strlen( $src );
	$i           = 0;
	$last        = '';   // Last significant character written.
	$word        = '';   // Last identifier/keyword written, for regex detection after `return` etc.
	$space       = '';   // Pending whitespace: '', ' ' or "\n".
	$is_id       = static function ( $c ) {
		return '' !== $c && ( ctype_alnum( $c ) || '_' === $c || '$' === $c || '\\' === $c || ord( $c ) > 127 );
	};
	$regex_after = array( 'return', 'typeof', 'case', 'in', 'of', 'new', 'delete', 'void', 'throw', 'instanceof', 'do', 'else', 'yield', 'await' );

	$flush = static function ( $next ) use ( &$out, &$space, &$last, $is_id ) {
		if ( "\n" === $space && '' !== $out ) {
			$out .= "\n";
		} elseif ( ' ' === $space && ( ( $is_id( $last ) && $is_id( $next ) ) || ( ( '+' === $last || '-' === $last ) && $last === $next ) ) ) {
			$out .= ' ';
		}

		$space = '';
	};

	while ( $i < $len ) {
		$c = $src[ $i ];
		$n = $i + 1 < $len ? $src[ $i + 1 ] : '';

		// Whitespace.
		if ( ' ' === $c || "\t" === $c || "\r" === $c || "\n" === $c ) {
			if ( "\n" === $c ) {
				$space = "\n";
			} elseif ( '' === $space ) {
				$space = ' ';
			}

			++$i;

			continue;
		}

		// Comments (keep /*! … */ licence comments).
		if ( '/' === $c && '/' === $n ) {
			while ( $i < $len && "\n" !== $src[ $i ] ) {
				++$i;
			}

			continue;
		}

		if ( '/' === $c && '*' === $n ) {
			$end  = strpos( $src, '*/', $i + 2 );
			$end  = false === $end ? $len : $end + 2;
			$text = substr( $src, $i, $end - $i );

			if ( '!' === ( $src[ $i + 2 ] ?? '' ) ) {
				$flush( '/' );
				$out .= $text;
				$last = '/';
			} elseif ( false !== strpos( $text, "\n" ) ) {
				$space = "\n";
			} elseif ( '' === $space ) {
				$space = ' ';
			}

			$i = $end;

			continue;
		}

		// Strings and template literals: copied verbatim.
		if ( '"' === $c || "'" === $c || '`' === $c ) {
			$flush( $c );
			$j = $i + 1;

			while ( $j < $len && $src[ $j ] !== $c ) {
				$j += '\\' === $src[ $j ] ? 2 : 1;
			}

			$out .= substr( $src, $i, $j - $i + 1 );
			$i    = $j + 1;
			$last = $c;
			$word = '';

			continue;
		}

		// Regular expression literal: after an operator, an opening bracket, or a keyword such as return.
		if ( '/' === $c && ( '' === $last || false !== strpos( '(,=:[!&|?{};+-*%<>~^', $last ) || in_array( $word, $regex_after, true ) ) ) {
			$flush( $c );
			$j        = $i + 1;
			$in_class = false;

			while ( $j < $len ) {
				$ch = $src[ $j ];

				if ( '\\' === $ch ) {
					$j += 2;

					continue;
				}

				if ( '[' === $ch ) {
					$in_class = true;
				} elseif ( ']' === $ch ) {
					$in_class = false;
				} elseif ( '/' === $ch && ! $in_class ) {
					break;
				}

				++$j;
			}

			++$j;

			while ( $j < $len && ctype_alpha( $src[ $j ] ) ) {
				++$j;   // Flags.
			}

			$out .= substr( $src, $i, $j - $i );
			$i    = $j;
			$last = '/';
			$word = '';

			continue;
		}

		// Identifiers, numbers and keywords.
		if ( $is_id( $c ) ) {
			$flush( $c );
			$j = $i;

			while ( $j < $len && ( $is_id( $src[ $j ] ) || ( '.' === $src[ $j ] && ctype_digit( $src[ $i ] ) ) ) ) {
				++$j;
			}

			$word = substr( $src, $i, $j - $i );
			$out .= $word;
			$i    = $j;
			$last = substr( $word, -1 );

			continue;
		}

		// Punctuation and operators.
		$flush( $c );
		$out .= $c;
		$last = $c;
		$word = '';
		++$i;
	}

	return trim( $out ) . "\n";
}

/**
 * Remove comments and redundant whitespace from CSS.
 *
 * @param string $src Source.
 * @return string
 */
function dovetail_minify_css( $src ) {
	$out = '';
	$len = strlen( $src );
	$i   = 0;

	while ( $i < $len ) {
		$c = $src[ $i ];

		if ( '/' === $c && '*' === ( $src[ $i + 1 ] ?? '' ) ) {
			$end = strpos( $src, '*/', $i + 2 );
			$i   = false === $end ? $len : $end + 2;

			continue;
		}

		if ( '"' === $c || "'" === $c ) {
			$j = $i + 1;

			while ( $j < $len && $src[ $j ] !== $c ) {
				$j += '\\' === $src[ $j ] ? 2 : 1;
			}

			$out .= substr( $src, $i, $j - $i + 1 );
			$i    = $j + 1;

			continue;
		}

		if ( ctype_space( $c ) ) {
			while ( $i < $len && ctype_space( $src[ $i ] ) ) {
				++$i;
			}

			$prev = substr( $out, -1 );
			$next = $src[ $i ] ?? '';

			// Spaces around these never matter; everywhere else (selectors, calc()) one space is kept.
			if ( '' !== $out && false === strpos( '{};,>', $prev ) && false === strpos( '{};,>!', $next ) ) {
				$out .= ' ';
			}

			continue;
		}

		if ( ';' === $c && '}' === ( ltrim( substr( $src, $i + 1, 40 ) )[0] ?? '' ) ) {
			++$i;   // Last semicolon of a block.

			continue;
		}

		$out .= $c;
		++$i;
	}

	return trim( $out ) . "\n";
}

/**
 * Translatable strings of front.js: the first literal argument of every __() and _f() call.
 *
 * @param string $root Plugin root.
 */
function dovetail_build_js_strings( $root ) {
	$src = (string) file_get_contents( $root . '/modules/virtual-assistant/assets/js/front.js' );
	preg_match_all( '/(?<![\w$.])(?:__|_f)\(\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')\s*[,)]/s', $src, $m );
	$strings = array();

	foreach ( $m[1] as $literal ) {
		$value = json_decode( '"' . str_replace( array( "\\'", '"' ), array( "'", '\\"' ), substr( $literal, 1, -1 ) ) . '"' );

		if ( '"' === $literal[0] ) {
			$value = json_decode( $literal );
		}

		if ( is_string( $value ) && '' !== $value ) {
			$strings[ $value ] = true;
		}
	}

	$php  = "<?php\n/**\n * GENERATED by tools/build.php from assets/js/front.js. Do not edit: run `php tools/build.php`.\n *\n * Every translatable string of the front-end, as a literal __() call so .pot tools find it.\n */\nif (!defined('ABSPATH')) exit;\n\n";
	$php .= "function dt_va_js_strings() {\n    return array(\n";

	foreach ( array_keys( $strings ) as $value ) {
		$lit  = '"' . addcslashes( $value, "\\\"\$\n\r\t" ) . '"';
		$php .= ( false !== strpos( $value, '%' ) ? "        /* translators: placeholders are filled in by the assistant (company name, times, links). Keep any HTML tags. */\n" : '' );
		$php .= "        $lit => __($lit, 'dovetail-studio'),\n";
	}

	$php .= "    );\n}\n";
	file_put_contents( $root . '/modules/virtual-assistant/src/services/i18n-js.php', $php );
	printf( "%-58s %7d strings\n", 'modules/virtual-assistant/src/services/i18n-js.php', count( $strings ) );
}

/**
 * languages/dovetail-studio.pot from the PHP sources.
 *
 * @param string $root Plugin root.
 */
function dovetail_build_pot( $root ) {
	$funcs   = array(
		'__'         => 'single',
		'_e'         => 'single',
		'esc_html__' => 'single',
		'esc_html_e' => 'single',
		'esc_attr__' => 'single',
		'esc_attr_e' => 'single',
		'_x'         => 'context',
		'esc_html_x' => 'context',
		'esc_attr_x' => 'context',
		'_n'         => 'plural',
	);
	$entries = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $it as $file ) {
		$path = (string) $file;

		if ( '.php' !== substr( $path, -4 ) || preg_match( '#/(\.git|tests|tools|vendor|node_modules)/#', $path ) ) {
			continue;
		}

		$rel     = ltrim( substr( $path, strlen( $root ) ), '/' );
		$tokens  = token_get_all( (string) file_get_contents( $path ) );
		$comment = '';
		$count   = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$t = $tokens[ $i ];

			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$comment = preg_match( '/translators:\s*(.+?)\s*(\*\/)?$/s', $t[1], $cm ) ? trim( preg_replace( '/\s*\*\s*/', ' ', $cm[1] ) ) : $comment;

				continue;
			}

			if ( ';' === $t || '{' === $t || '}' === $t ) {
				$comment = '';   // A translators comment belongs to the call in the same statement.

				continue;
			}

			if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $funcs[ $t[1] ] ) ) {
				continue;
			}

			// Collect the literal arguments of this call.
			$j = $i + 1;

			while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				++$j;
			}

			if ( '(' !== $tokens[ $j ] ) {
				continue;
			}

			$args  = array();
			$depth = 0;
			$cur   = null;

			for ( ++$j; $j < $count; $j++ ) {
				$a = $tokens[ $j ];

				if ( '(' === $a || '[' === $a ) {
					++$depth;
				} elseif ( ')' === $a || ']' === $a ) {
					if ( 0 === $depth-- ) {
						break;
					}
				} elseif ( ',' === $a && 0 === $depth ) {
					$args[] = $cur;
					$cur    = null;

					continue;
				}

				if ( 0 === $depth && is_array( $a ) && T_CONSTANT_ENCAPSED_STRING === $a[0] && null === $cur ) {
					$cur = eval( 'return ' . $a[1] . ';' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Literal string token only.
				} elseif ( ! ( is_array( $a ) && T_WHITESPACE === $a[0] ) ) {
					$cur = is_string( $cur ) && is_array( $a ) && T_CONSTANT_ENCAPSED_STRING === $a[0] ? $cur : ( is_string( $cur ) ? $cur : false );
				}
			}

			$args[] = $cur;
			$kind   = $funcs[ $t[1] ];
			$domain = 'plural' === $kind ? ( $args[3] ?? null ) : ( 'context' === $kind ? ( $args[2] ?? null ) : ( $args[1] ?? null ) );

			if ( 'dovetail-studio' !== $domain || ! is_string( $args[0] ) ) {
				$comment = '';

				continue;
			}

			$ctx = 'context' === $kind && is_string( $args[1] ) ? $args[1] : '';
			$key = $ctx . "\4" . $args[0];

			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = array(
					'msgid'   => $args[0],
					'ctx'     => $ctx,
					'plural'  => 'plural' === $kind && is_string( $args[1] ) ? $args[1] : null,
					'refs'    => array(),
					'comment' => '',
				);
			}

			$entries[ $key ]['refs'][] = $rel . ':' . $t[2];

			if ( '' !== $comment ) {
				$entries[ $key ]['comment'] = $comment;
			}

			$comment = '';
		}
	}

	// Module names and descriptions from the bundled manifests (shown translated on the Studio screens).
	foreach ( glob( $root . '/modules/*/module.php' ) as $manifest ) {
		$text = (string) file_get_contents( $manifest );

		foreach ( array( 'name', 'description' ) as $field ) {
			if ( preg_match( "/'" . $field . "'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $text, $mm ) ) {
				$msgid = stripslashes( $mm[1] );
				$key   = "\4" . $msgid;

				if ( ! isset( $entries[ $key ] ) ) {
					$entries[ $key ] = array(
						'msgid'   => $msgid,
						'ctx'     => '',
						'plural'  => null,
						'refs'    => array(),
						'comment' => 'Module ' . $field . '.',
					);
				}

				$entries[ $key ]['refs'][] = ltrim( substr( $manifest, strlen( $root ) ), '/' );
			}
		}
	}

	$q   = static function ( $s ) {
		$s = addcslashes( $s, "\\\"\t" );

		return false === strpos( $s, "\n" ) ? '"' . $s . '"' : "\"\"\n\"" . str_replace( "\n", "\\n\"\n\"", $s ) . '"';
	};
	$pot = "# Copyright (C) Dovetail Consultancy\n# This file is distributed under the GPL-2.0-or-later.\nmsgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Dovetail Studio\\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"X-Domain: dovetail-studio\\n\"\n\n";

	foreach ( $entries as $e ) {
		if ( '' !== $e['comment'] ) {
			$pot .= '#. translators: ' . $e['comment'] . "\n";
		}

		$pot .= '#: ' . implode( ' ', array_slice( array_unique( $e['refs'] ), 0, 6 ) ) . "\n";

		if ( false !== strpos( $e['msgid'], '%' ) ) {
			$pot .= "#, php-format\n";
		}

		if ( '' !== $e['ctx'] ) {
			$pot .= 'msgctxt ' . $q( $e['ctx'] ) . "\n";
		}

		$pot .= 'msgid ' . $q( $e['msgid'] ) . "\n";

		if ( null !== $e['plural'] ) {
			$pot .= 'msgid_plural ' . $q( $e['plural'] ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
		} else {
			$pot .= "msgstr \"\"\n\n";
		}
	}

	if ( ! is_dir( $root . '/languages' ) ) {
		mkdir( $root . '/languages' );
	}

	file_put_contents( $root . '/languages/dovetail-studio.pot', $pot );
	printf( "%-58s %7d strings\n", 'languages/dovetail-studio.pot', count( $entries ) );
}

/**
 * Compiles a .po file into a .mo file (GNU gettext binary format).
 *
 * @param string $po Path to the .po file.
 */
function dovetail_build_mo( $po ) {
	$entries = array();
	$cur     = array();
	$field   = null;
	$flush   = static function () use ( &$cur, &$entries ) {
		if ( isset( $cur['msgid'] ) ) {
			$key = ( isset( $cur['msgctxt'] ) ? $cur['msgctxt'] . "\4" : '' ) . $cur['msgid'] . ( isset( $cur['msgid_plural'] ) ? "\0" . $cur['msgid_plural'] : '' );
			$val = isset( $cur['msgstr'] ) ? $cur['msgstr'] : implode( "\0", $cur['plurals'] ?? array() );

			if ( '' === $cur['msgid'] || '' !== str_replace( "\0", '', $val ) ) {
				$entries[ $key ] = $val;
			}
		}

		$cur = array();
	};
	$unq     = static function ( $s ) {
		return stripcslashes( substr( $s, 1, -1 ) );
	};

	foreach ( file( $po ) as $line ) {
		$line = rtrim( $line, "\r\n" );

		if ( '' === trim( $line ) ) {
			$flush();
			$field = null;

			continue;
		}

		if ( '#' === $line[0] ) {
			continue;
		}

		if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr)(?:\[(\d+)\])?\s+(".*")$/', $line, $m ) ) {
			if ( 'msgid' === $m[1] && isset( $cur['msgid'] ) ) {
				$flush();
			}

			if ( 'msgstr' === $m[1] && '' !== $m[2] ) {
				$cur['plurals'][ (int) $m[2] ] = $unq( $m[3] );
				$field                         = array( 'plurals', (int) $m[2] );
			} else {
				$cur[ $m[1] ] = $unq( $m[3] );
				$field        = $m[1];
			}

			continue;
		}

		if ( '"' === $line[0] && null !== $field ) {
			if ( is_array( $field ) ) {
				$cur['plurals'][ $field[1] ] .= $unq( $line );
			} else {
				$cur[ $field ] .= $unq( $line );
			}
		}
	}

	$flush();
	ksort( $entries, SORT_STRING );

	$n    = count( $entries );
	$ids  = '';
	$strs = '';
	$offs = array();

	foreach ( $entries as $id => $str ) {
		$offs[] = array( strlen( $id ), strlen( $ids ), strlen( $str ), strlen( $strs ) );
		$ids   .= $id . "\0";
		$strs  .= $str . "\0";
	}

	$key_start = 28 + 16 * $n;
	$val_start = $key_start + strlen( $ids );
	$mo        = pack( 'V7', 0x950412de, 0, $n, 28, 28 + 8 * $n, 0, $key_start );

	foreach ( $offs as $o ) {
		$mo .= pack( 'V2', $o[0], $key_start + $o[1] );
	}

	foreach ( $offs as $o ) {
		$mo .= pack( 'V2', $o[2], $val_start + $o[3] );
	}

	$mo    .= $ids . $strs;
	$target = preg_replace( '/\.po$/', '.mo', $po );
	file_put_contents( $target, $mo );
	printf( "%-58s %7d strings\n", 'languages/' . basename( $target ), $n - 1 );
}
