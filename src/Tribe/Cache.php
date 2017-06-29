<?php

/**
 * Manage setting and expiring cached data
 *
 * Select actions can be used to force cached
 * data to expire. Implemented so far:
 *  - save_post
 *
 * When used in its ArrayAccess API the cache will provide non persistent storage.
 */
class Tribe__Cache implements ArrayAccess {
	const NO_EXPIRATION  = 0;
	const NON_PERSISTENT = - 1;

	/**
	 * @var array
	 */
	protected $non_persistent_keys = array();

	public static function setup() {
		wp_cache_add_non_persistent_groups( array( 'tribe-events-non-persistent' ) );
	}

	/**
	 * @param string $id
	 * @param mixed  $value
	 * @param int    $expiration
	 * @param string $expiration_trigger
	 *
	 * @return bool
	 */
	public function set( $id, $value, $expiration = 0, $expiration_trigger = '' ) {
		$key = $this->get_id( $id, $expiration_trigger );

		if ( $expiration == self::NON_PERSISTENT ) {
			$group      = 'tribe-events-non-persistent';
			$this->non_persistent_keys[] = $key;
			$expiration = 1;
		} else {
			$group = 'tribe-events';
		}


		return wp_cache_set( $key, $value, $group, $expiration );
	}

	/**
	 * @param        $id
	 * @param        $value
	 * @param int    $expiration
	 * @param string $expiration_trigger
	 *
	 * @return bool
	 */
	public function set_transient( $id, $value, $expiration = 0, $expiration_trigger = '' ) {
		return set_transient( $this->get_id( $id, $expiration_trigger ), $value, $expiration );
	}

	/**
	 * @param string $id
	 * @param string $expiration_trigger
	 *
	 * @return mixed
	 */
	public function get( $id, $expiration_trigger = '' ) {
		$group = in_array( $id, $this->non_persistent_keys ) ? 'tribe-events-non-persistent' : 'tribe-events';

		return wp_cache_get( $this->get_id( $id, $expiration_trigger ), $group );
	}

	/**
	 * @param string $id
	 * @param string $expiration_trigger
	 *
	 * @return mixed
	 */
	public function get_transient( $id, $expiration_trigger = '' ) {
		return get_transient( $this->get_id( $id, $expiration_trigger ) );
	}

	/**
	 * @param string $id
	 * @param string $expiration_trigger
	 *
	 * @return bool
	 */
	public function delete( $id, $expiration_trigger = '' ) {
		return wp_cache_delete( $this->get_id( $id, $expiration_trigger ), 'tribe-events' );
	}

	/**
	 * @param string $id
	 * @param string $expiration_trigger
	 *
	 * @return bool
	 */
	public function delete_transient( $id, $expiration_trigger = '' ) {
		return delete_transient( $this->get_id( $id, $expiration_trigger ) );
	}

	/**
	 * @param string $key
	 * @param string $expiration_trigger
	 *
	 * @return string
	 */
	public function get_id( $key, $expiration_trigger = '' ) {
		$last = empty( $expiration_trigger ) ? '' : $this->get_last_occurrence( $expiration_trigger );
		$id   = $key . $last;
		if ( strlen( $id ) > 40 ) {
			$id = md5( $id );
		}

		return $id;
	}

	/**
	 * @param string $action
	 *
	 * @return int
	 */
	public function get_last_occurrence( $action ) {
		return (int) get_option( 'tribe_last_' . $action, time() );
	}

	/**
	 * @param string $action
	 * @param int    $timestamp
	 */
	public function set_last_occurrence( $action, $timestamp = 0 ) {
		if ( empty( $timestamp ) ) {
			$timestamp = time();
		}
		update_option( 'tribe_last_' . $action, (int) $timestamp );
	}

	/**
	 * Builds a key from an array of components and an optional prefix.
	 *
	 * @param mixed  $components Either a single component of the key or an array of key components.
	 * @param string $prefix
	 * @param bool   $sort Whether component arrays should be sorted or not to generate the key; defaults to `true`.
	 *
	 * @return string The resulting key.
	 */
	public function make_key( $components, $prefix = '', $sort = true ) {
		$key = '';
		$components = is_array( $components ) ? $components : array( $components );
		foreach ( $components as $component ) {
			if ( $sort && is_array( $component ) ) {
				$is_associative = count( array_filter( array_keys( $component ), 'is_numeric' ) ) < count( array_keys( $component ) );
				if ( $is_associative ) {
					ksort( $component );
				} else {
					sort( $component );
				}
			}
			$key .= maybe_serialize( $component );
		}

		return $this->get_id( $prefix . md5( $key ) );
	}

	/**
	 * Whether an offset exists
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset An offset to check for.
	 * @return boolean true on success or false on failure.
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 * @since TBD
	 */
	public function offsetExists( $offset ) {
		return in_array( $offset, $this->non_persistent_keys );
	}

	/**
	 * Offset to retrieve
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset The offset to retrieve.
	 * @return mixed Can return all value types.
	 *
	 * @since TBD
	 */
	public function offsetGet( $offset ) {
		return $this->get( $offset );
	}

	/**
	 * Offset to set
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value  The value to set.
	 *
	 * @return void
	 *
	 * @since TBD
	 */
	public function offsetSet( $offset, $value ) {
		$this->set( $offset, $value, self::NON_PERSISTENT );
	}

	/**
	 * Offset to unset
	 *
	 * @link  http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 *
	 * @since TBD
	 */
	public function offsetUnset( $offset ) {
		$this->delete( $offset );
	}
}

