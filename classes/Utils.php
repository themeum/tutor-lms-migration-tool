<?php 

class Utils {

    public function fetch_history($vendor) {
        global $wpdb;
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT  * FROM {$wpdb->prefix}tutor_migration
                WHERE `migration_vendor` = %s
                ORDER BY ID DESC
                LIMIT %d, %d",
                $vendor, 0, 20
            )
        );
        // return $vendor;
        return $result;
    }

    public function ld_course_count(){
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'sfwd-courses' AND post_status = 'publish';");
    }

    
    public function ld_orders_count(){
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'sfwd-transactions' AND post_status = 'publish';");
    }


    public function lp_course_count(){
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_course' AND post_status = 'publish';" );
    }

    public function lp_orders_count(){
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'lp_order';" );
    }

    public function lp_reviews_count(){
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(comments.comment_ID) FROM {$wpdb->comments} comments INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = comments.comment_ID AND cm.meta_key = '_lpr_rating' WHERE comments.comment_type = 'review';" );
    }

}