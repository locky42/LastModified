<?php
/**
 * Plugin Name: LastModified
 * Description: Enable Last-Modified for pages
 * Plugin URI:  https://github.com/locky42/LastModified
 * Author URI:  https://github.com/locky42
 * Author:      Zinchenko Maxym
 * Version:     1.1
 * License:     WTFPL
 * License URI: http://www.wtfpl.net/
 *
 * Network:     true
 */

/* one month */
define('EXPIRES', 2592000);

register_activation_hook( __FILE__, 'install' );
function install(){
    global $wpdb;
    global $table_prefix;
    $columns = $wpdb->get_results(
    'SELECT `COLUMN_NAME` 
         FROM `INFORMATION_SCHEMA`.`COLUMNS`
         WHERE `TABLE_SCHEMA`=\''.DB_NAME.'\' 
         AND `TABLE_NAME`=\''.$table_prefix.'terms\';', ARRAY_N);
    $columns_tmp = [];
    foreach ($columns as $column) {
        $columns_tmp[] = $column[0];
    }
    $columns = $columns_tmp;
    unset($columns_tmp);
    if(!in_array('term_modified_gmt', $columns)) {
        $wpdb->query('ALTER TABLE `'.$table_prefix.'terms` ADD `term_modified_gmt` DATETIME NOT NULL DEFAULT \'0000-00-00 00:00:00\' AFTER `term_group`;');
    }
}

function set_headers($time) {
    $strtotime = strtotime($time);
    $modified_gmt = gmdate("D, d M Y H:i:s", $strtotime);
    if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $request = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        if (!empty($request) && $request >= $strtotime ) {
            header('Cache-Control: must-revalidate, proxy-revalidate, max-age=3600');
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            exit();
        }
    }
    header('ETag: "'.md5($strtotime).'"');
    header('Last-Modified: ' . $modified_gmt . ' GMT');
    header('Cache-Control: must-revalidate, proxy-revalidate, max-age=3600');
    $expires = time()+EXPIRES;
    header('Expires: '.gmdate("D, d M Y H:i:s", $expires). ' GMT');
}


add_action( 'template_redirect', 'last_modified', 0);
function  last_modified($input) {
    if(!function_exists('is_admin') || !function_exists('is_page')) {
        return $input;
    }
    if(!is_admin()) {
        global $post;
        if(!empty($_POST) || !$post) {
            return $input;
        }
        if(function_exists('is_cart') && function_exists('is_checkout') && function_exists('is_checkout_pay_page')) {
            if(is_cart() || is_checkout() || is_checkout_pay_page()) {
                return $input;
            }
        }
        $taxonomy= get_term_by('slug', get_query_var( 'term' ), get_query_var('taxonomy'));
        if(is_single($post) || is_page()) {
            set_headers($post->post_modified_gmt);
        } elseif($taxonomy) {
            if(isset($taxonomy->term_modified_gmt) && $taxonomy->term_modified_gmt != '0000-00-00 00:00:00') {
                set_headers($taxonomy->term_modified_gmt);
            }
        }
    }
    return $input;
}

add_action('save_post', 'update_terms_by_post', 10, 2);
function update_terms_by_post($post_id, $post = null){
    if ($post && !wp_is_post_revision($post_id)){
        remove_action('save_post', 'update_terms_by_post');

        if($post->post_type == 'product') {
            $_pf = new WC_Product_Factory();
            $product = $_pf->get_product($post->ID)->get_data();
            $category_ids = $product['category_ids'];
            foreach ($category_ids as $category_id) {
                global $wpdb;
                global $table_prefix;
                $wpdb->query(
                    'UPDATE '.$table_prefix.'terms 
                    SET `term_modified_gmt` = \''.$post->post_modified_gmt.'\' 
                    WHERE term_id = \''.$category_id.'\'');
            }
        }

        add_action('save_post', 'update_terms_by_post');
    }
}

add_action( 'edit_term', 'update_term', 10);
function update_term($id) {
    global $wpdb;
    global $table_prefix;
    $wpdb->query(
        'UPDATE '.$table_prefix.'terms 
        SET `term_modified_gmt` = \''.gmdate("Y-m-d H:i:s", time()).'\' 
        WHERE term_id = \''.$id.'\'');
}
