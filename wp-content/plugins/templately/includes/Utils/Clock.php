<?php
/**
 * The current time, behind one filter so tests can set it.
 *
 * @package Templately\Utils
 */

namespace Templately\Utils;

/**
 * Clock: the current Unix time for code whose behaviour depends on elapsed time.
 *
 * Code that compares timestamps (a lock taken over after a stale interval, rows
 * removed after a retention window) reads the time here instead of calling time()
 * directly, so a test can fix the time through the `templately_now` filter instead
 * of waiting on, or racing, the real clock. With no filter added it returns time().
 */
class Clock {

	/**
	 * The current Unix time in seconds.
	 *
	 * A filtered value that is not a positive number (null, false, a non-numeric
	 * string, 0, a negative number) is ignored and time() is returned: with 0, a
	 * lock whose holder died would never be taken over and every finished session
	 * would be removed at the next sweep.
	 *
	 * @return int time(), or the positive value the `templately_now` filter returns.
	 */
	public static function now() {
		/**
		 * Filters the current Unix time Templately uses for elapsed-time checks.
		 *
		 * Meant for tests. A site should not change it. A value that is not a
		 * positive number is ignored.
		 *
		 * @param int $now Unix time in seconds, from time().
		 */
		$now = apply_filters( 'templately_now', time() );

		return ( is_numeric( $now ) && (int) $now > 0 ) ? (int) $now : time();
	}
}
