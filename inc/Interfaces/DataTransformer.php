<?php
/**
 * Data transformer interface for migration
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.4.0
 */

namespace Themeum\TutorLMSMigrationTool\Interfaces;

interface DataTransformer {
	/**
	 * Transform data.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $data data.
	 *
	 * @return mixed
	 */
	public function transform( $data );
}
