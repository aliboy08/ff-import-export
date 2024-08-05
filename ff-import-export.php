<?php
/**
 * Plugin Name: FF Import & Export
 * Plugin URI: https://www.fivebyfive.com.au/
 * Description: Import and export posts or other data
 * Version: 2.1.6
 * Author: Five by Five
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) die();

define( 'FFIE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FFIE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFIE_PLUGIN_FILE', __FILE__ );

include_once 'functions.php';

include_once 'import/class-import.php';
new \FFIE\Import();
include_once 'export/class-export.php';
new \FFIE\Export();


// Extension sample:
// add_filter( 'ff_export_types', function( $types ){
//     $types['custom'] = 'Custom';
//     return $types;
// });

// add_filter( 'ff_export_data_custom', function( $data, $ff_export ){
//     $items = [];
//     $args = [
//         'post_type' => 'post',
//         'showposts' => -1,
//     ];
//     $q = new WP_Query($args);
//     foreach( $q->posts as $post ) {

//         $item = [
//             'post_data' => $post,
//             'featured_image' => $ff_export->get_featured_image_url($post->ID),
//         ];

//         $location_id = get_field('location', $post->ID);
//         $location_gallery = get_field('gallery', $location_id);
//         if( $location_gallery ) {
//             $item['gallery'] = []; // array of images here...
//         }

//         $items[] = $item;
//     }
//     return $items;
// }, 10, 2 );


// add_filter('ff_import_custom_types', function($types){
//     $types['custom'] = 'Custom';
//     return $types;
// });


// add_action('ff_import_data_custom', function($item, $ff_import){    

//     $post_type = 'property';
//     $post_title = $item['post_data']['post_title'];

//     // check if post exists
//     $post_id = $ff_import->get_post_id( $post_title, $post_type );
//     if( !$post_id ) {
//         // create new post
//         $post_id = wp_insert_post([
//             'post_title' => $post_title,
//             'post_status' => 'publish',
//             'post_type' => $post_type,
//         ]);
//     }

//     if( !$post_id ) return;

//     // upload featured image
//     $ff_import->update_post_thumbnail( $post_id, $item['featured_image'] );

//     // Post metas
//     $metas = [];

//     // image / gallery
//     // get image ids, upload new images
//     // string of single image url OR array of image urls
//     // $img_ids = $ff_import->get_image_ids( SINGLE_IMAGE_URL_OR_ARRAY_OF_IMAGES );
//     // if( $img_ids ) {
//     //     $metas['META_KEY_HERE'] = $img_ids;
//     // }

//     // post objects
//     // $map_to_post_type = 'post';
//     // // string of single post title OR array of post titles
//     // $post_object_ids = $ff_import->get_post_ids_from_title( POST_TITLE_OR_ARRAY_OF_POST_TITLES, $map_to_post_type );
//     // if( $post_object_ids ) {
//     //     $metas['META_KEY_HERE'] = $post_object_ids;
//     // }

//     // $ff_import->update_post_metas( $post_id, $metas );

//     // taxonomies / terms
//     // $terms = ARRAY_OF_TERM_NAMES_HERE;
//     // $map_to_taxonomy = 'category';
//     // $append = true; // true = add | false = replace existing
//     // $ff_import->assign_terms( $post_id, $terms, $map_to_taxonomy, $append );

// }, 10, 2);