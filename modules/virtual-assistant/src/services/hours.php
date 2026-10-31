<?php

/** Opening hours and time zone: one source (Settings > Company > Opening hours, site time zone) for PHP and front.js. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Site time zone (Settings > General). */
function dt_va_tz() {
	return wp_timezone();
}

function dt_va_now() {
	return new DateTime( 'now', dt_va_tz() );
}

/** Saved opening hours: open/close as decimal hours (17.5 = 17:30), days 0 = Sunday … 6 = Saturday. */
function dt_va_hours() {
	$h    = dt_va_public_value( 'OPENING_HOURS' );
	$h    = is_array( $h ) ? $h : array();
	$days = isset( $h['days'] ) && is_array( $h['days'] )
		? array_values( array_unique( array_map( 'intval', $h['days'] ) ) )
		: array( 1, 2, 3, 4, 5 );
	sort( $days );

	return array(
		'open'   => isset( $h['open'] ) ? (float) $h['open'] : 9.0,
		'close'  => isset( $h['close'] ) ? (float) $h['close'] : 17.5,
		'days'   => array_values(
			array_filter(
				$days,
				function ( $d ) {
					return $d >= 0 && $d <= 6;
				}
			)
		),
		'closed' => isset( $h['closed'] ) && is_array( $h['closed'] ) ? $h['closed'] : array(),
	);
}

/** Whether a day is a working day: an open weekday that is not a closed date (holiday). */
function dt_va_is_open_day( DateTime $d ) {
	$hours = dt_va_hours();

	if ( ! in_array( (int) $d->format( 'w' ), $hours['days'], true ) ) {
		return false;
	}

	$ymd = $d->format( 'Y-m-d' );

	foreach ( $hours['closed'] as $range ) {
		if ( is_array( $range )
			&& isset( $range['from'], $range['to'] )
			&& $ymd >= $range['from']
			&& $ymd <= $range['to']
		) {
			return false;
		}
	}

	return true;
}

function dt_va_decimal_hour( DateTime $d ) {
	return (int) $d->format( 'G' ) + ( (int) $d->format( 'i' ) ) / 60;
}

function dt_va_studio_open() {
	$hours = dt_va_hours();
	$now   = dt_va_now();
	$h     = dt_va_decimal_hour( $now );

	return dt_va_is_open_day( $now ) && $h >= $hours['open'] && $h < $hours['close'];
}

/** A decimal hour as the site shows times, e.g. 9 → "9:00 am" or "09:00" depending on Settings > General. */
function dt_va_format_hour( $decimal ) {
	$d = dt_va_now();
	$d->setTime( (int) floor( $decimal ), (int) round( ( $decimal - floor( $decimal ) ) * 60 ), 0 );

	return wp_date( (string) get_option( 'time_format', 'g:i a' ), $d->getTimestamp(), dt_va_tz() );
}

/** Next opening time strictly after now (today when the office has not opened yet), or null when no day is open. */
function dt_va_next_opening( $after_today = false ) {
	$hours = dt_va_hours();

	if ( ! $hours['days'] ) {
		return null;
	}

	$now = dt_va_now();

	// Up to 90 days ahead, so a long closure (e.g. a summer break) still finds the reopening day.
	for ( $i = $after_today ? 1 : 0; $i <= 90; $i++ ) {
		$d = clone $now;

		if ( $i ) {
			$d->modify( "+$i day" );
		}

		if ( ! dt_va_is_open_day( $d ) ) {
			continue;
		}

		$d->setTime(
			(int) floor( $hours['open'] ),
			(int) round( ( $hours['open'] - floor( $hours['open'] ) ) * 60 ),
			0
		);

		if ( $d > $now ) {
			return $d;
		}
	}

	return null;
}

/** "at 9:00 am today", "at 9:00 am tomorrow", "on Monday at 9:00 am", or "on the next working day". */
function dt_va_reopens() {
	$next = dt_va_next_opening();

	if ( ! $next ) {
		return __( 'on the next working day', 'dovetail-studio' );
	}

	$time = dt_va_format_hour( dt_va_hours()['open'] );
	$days = (int) dt_va_now()->setTime( 0, 0 )->diff( ( clone $next )->setTime( 0, 0 ) )->format( '%a' );

	if ( 0 === $days ) {
		/* translators: %s: opening time. */
		return sprintf( __( 'at %s today', 'dovetail-studio' ), $time );
	}

	/* translators: %s: opening time. */
	if ( 1 === $days ) {
		/* translators: %s: opening time. */
		return sprintf( __( 'at %s tomorrow', 'dovetail-studio' ), $time );
	}

	/* translators: 1: weekday name, or a date beyond the coming week (e.g. "2 January"), 2: opening time. */
	return sprintf( __( 'on %1$s at %2$s', 'dovetail-studio' ), wp_date( $days < 7 ? 'l' : 'j F', $next->getTimestamp(), dt_va_tz() ), $time );
}

/** Open days as text: "Monday to Friday" ($short: "Mon to Fri"), "Monday, Wednesday and Friday", or "" when none. */
function dt_va_days_label( $short = false ) {
	$days = dt_va_hours()['days'];

	if ( ! $days ) {
		return '';
	}

	$name = function ( $d ) use ( $short ) {
		// 2023-01-01 was a Sunday: +$d days gives the weekday $d, named in the site language.
		$ts = strtotime( '2023-01-01 12:00:00 UTC' ) + $d * DAY_IN_SECONDS;

		return wp_date( $short ? 'D' : 'l', $ts, new DateTimeZone( 'UTC' ) );
	};

	if ( 7 === count( $days ) ) {
		return __( 'every day', 'dovetail-studio' );
	}

	$contiguous = count( $days ) > 2 && count( $days ) - 1 === $days[ count( $days ) - 1 ] - $days[0];

	if ( $contiguous ) {
		/* translators: 1: first weekday, 2: last weekday, e.g. "Monday to Friday". */
		return sprintf( __( '%1$s to %2$s', 'dovetail-studio' ), $name( $days[0] ), $name( $days[ count( $days ) - 1 ] ) );
	}

	$names = array_map( $name, $days );

	if ( 1 === count( $names ) ) {
		return $names[0];
	}

	$last = array_pop( $names );

	/* translators: 1: comma-separated weekdays, 2: last weekday, e.g. "Monday, Wednesday and Friday". */
	return sprintf( __( '%1$s and %2$s', 'dovetail-studio' ), implode( ', ', $names ), $last );
}

/** UTC datetime of the start of the day $n open days ago (calendar days when no day is open). */
function dt_va_working_days_ago( $n ) {
	$days  = dt_va_hours()['days'];
	$d     = dt_va_now();
	$count = 0;
	$guard = 0;

	while ( $count < $n && $guard++ < 60 ) {
		$d->modify( '-1 day' );

		if ( ! $days || dt_va_is_open_day( $d ) ) {
			++$count;
		}
	}

	$d->setTimezone( new DateTimeZone( 'UTC' ) );

	return $d->format( 'Y-m-d H:i:s' );
}

/** Values front.js needs to agree with the server. */
function dt_va_hours_config() {
	return array(
		'timezone'  => wp_timezone_string(),
		'tzOffset'  => (int) round( dt_va_now()->getOffset() / 60 ),
		'openLabel' => dt_va_format_hour( dt_va_hours()['open'] ),
		'daysLabel' => dt_va_days_label(),
		'daysShort' => dt_va_days_label( true ),
	);
}
