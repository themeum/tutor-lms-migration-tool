<?php
/**
 * Migrate nav
 *
 * @package TutorLMSMigrationTool\Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.3.0
 */

?>
<div class="tutor-migration-tab">
	<ul class="tutor-nav">
		<li class="tutor-nav-item">
			<a class="tutor-nav-link is-active" href="#" data-tutor-nav-target="tutor-auto-migrate-tab">
				<?php esc_html_e( 'Auto Migrate', 'tutor-lms-migration-tool' ); ?>
			</a>
		</li>
		<li class="tutor-nav-item">
			<a class="tutor-nav-link" href="#" data-tutor-nav-target="tutor-manual-migrate-tab">
				<?php esc_html_e( 'Upload File', 'tutor-lms-migration-tool' ); ?>
			</a>
		</li>
		<li class="tutor-nav-item tutor-nav-more tutor-d-none">
			<a class="tutor-nav-link tutor-nav-more-item" href="#">
				<span class="tutor-mr-4"><?php esc_html_e( 'More', 'tutor-lms-migration-tool' ); ?></span> 
				<span class="tutor-nav-more-icon tutor-icon-times"></span>
			</a>
			<ul class="tutor-nav-more-list tutor-dropdown"></ul>
		</li>
	</ul>
</div>
