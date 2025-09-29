<?php
/**
 * Migration mapper.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool;

/**
 * Class Migration Map
 */
class MigrationMapper {
	/**
	 * Map key
	 *
	 * @since 2.4.0
	 *
	 * @var string
	 */
	private $map_key;

	/**
	 * Constructor
	 *
	 * @param string $map_key map key.
	 *
	 * @throws \InvalidArgumentException If map key is empty.
	 */
	public function __construct( $map_key ) {
		if ( empty( $map_key ) ) {
			throw new \InvalidArgumentException( 'Map key cannot be empty' );
		}

		$this->map_key = $map_key;
	}

	/**
	 * Get map
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_map(): array {
		return get_option( $this->map_key, array() );
	}

	/**
	 * Get map by key
	 *
	 * @since 2.4.0
	 *
	 * @param string $key Map key.
	 *
	 * @return array
	 */
	public function get_map_by_key( $key ): array {
		$map = $this->get_map();

		return $map[ $key ];
	}

	/**
	 * Set map
	 *
	 * @since 2.4.0
	 *
	 * @param array $map Map data.
	 *
	 * @return bool
	 */
	public function set_map( array $map ): bool {
		return update_option( $this->map_key, $map );
	}

	/**
	 * Set map by key
	 *
	 * @since 2.4.0
	 *
	 * @param string $key   Map key.
	 * @param mixed  $value Map value.
	 *
	 * @return bool
	 */
	public function set_map_by_key( $key, $value ): bool {
		$map         = $this->get_map();
		$map[ $key ] = $value;

		return $this->set_map( $map );
	}

	/**
	 * Clear map
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function clear_map(): bool {
		return delete_option( $this->map_key );
	}
}
