<?php 
/**
 * Products Interface.
 *
 * @package TutorLMSMigrationTool
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */
namespace Themeum\TutorLMSMigrationTool\Interfaces;

/**
 * Product interface for product migration class.
 */
interface Product {

    /**
     * Product migration method.
     *
     * @since 2.3.0
     *
     * @param integer $course_id the course id.
     * @param string  $course_title the course title.
     *
     * @throws \Throwable
     *
     * @return void
     */
    public function migrate( int $course_id, string $course_title );
}