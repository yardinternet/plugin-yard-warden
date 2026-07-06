<?php

declare(strict_types=1);

namespace Yard\Warden\LimitLogin;

/**
 * Tracks failed login attempts via WordPress transients and enforces
 * lockouts when configurable thresholds are exceeded.
 *
 * Three dimensions are tracked per attempt:
 *   - ip_user  : IP + username (targeted brute-force from one source)
 *   - ip       : IP only (credential stuffing across many users)
 *   - username : username only (distributed brute-force from many IPs)
 */
class Limiter
{
	protected const KEY_PREFIX = 'yw_ll:';
	protected const LOCK_PREFIX = 'yw_ll:lock:';

	public const DEFAULT_THRESHOLD_IP_USER = 5;
	public const DEFAULT_THRESHOLD_IP = 50;
	public const DEFAULT_THRESHOLD_USERNAME = 3;

	public const DEFAULT_WINDOW_IP_USER = 300;      // 5 min
	public const DEFAULT_WINDOW_IP = 3600;           // 60 min
	public const DEFAULT_WINDOW_USERNAME = 1500;     // 25 min

	public const DEFAULT_LOCKOUT_IP_USER = 300;      // 5 min
	public const DEFAULT_LOCKOUT_IP = 3600;           // 60 min
	public const DEFAULT_LOCKOUT_USERNAME = 1500;     // 25 min

	public function increment(string $dimension, string $identifier, int $windowSeconds): int
	{
		$key = $this->key($dimension, $identifier);
		$count = (int) get_transient($key);
		$count++;
		set_transient($key, $count, $windowSeconds);

		return $count;
	}

	public function getCount(string $dimension, string $identifier): int
	{
		return (int) get_transient($this->key($dimension, $identifier));
	}

	public function clear(string $dimension, string $identifier): void
	{
		delete_transient($this->key($dimension, $identifier));
		delete_transient($this->lockKey($dimension, $identifier));
	}

	public function setLockout(string $dimension, string $identifier, int $durationSeconds): void
	{
		set_transient(
			$this->lockKey($dimension, $identifier),
			true,
			$durationSeconds
		);
	}

	public function isLockedOut(string $dimension, string $identifier): bool
	{
		return false !== get_transient($this->lockKey($dimension, $identifier));
	}

	/**
	 * @return int Number of rows deleted.
	 */
	public function clearAll(): int
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like('_transient_' . self::KEY_PREFIX) . '%',
				$wpdb->esc_like('_transient_timeout_' . self::KEY_PREFIX) . '%'
			)
		);
	}

	protected function key(string $dimension, string $identifier): string
	{
		return self::KEY_PREFIX . $dimension . ':' . hash_hmac('sha256', $identifier, wp_salt('auth'));
	}

	protected function lockKey(string $dimension, string $identifier): string
	{
		return self::LOCK_PREFIX . $dimension . ':' . hash_hmac('sha256', $identifier, wp_salt('auth'));
	}
}
