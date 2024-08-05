<?php
if ( ! defined( 'ABSPATH' ) ) die();
wp_enqueue_style( 'ff-import-export', FFIE_PLUGIN_URL . 'assets/css/ff-import-export.css' );
wp_enqueue_style( 'jquery-datepicker', '//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css' );

wp_enqueue_script( 'ff-export', FFIE_PLUGIN_URL . 'export/js/export.js', [ 'jquery-ui-datepicker' ], null, true );

$post_types = get_post_types([
    'public' => true,
]);

$exclude_types = [
    'attachment',
];
$default_types = [];
foreach( $post_types as $post_type ) {
    if( in_array( $post_type, $exclude_types ) ) continue;
    $default_types[$post_type] = $post_type;
}

echo '<script>
var ff_export_default_types = '. json_encode($default_types) .';
var ff_export_nonce = "'. wp_create_nonce('ff_export_nonce') .'";
</script>';

$types = apply_filters( 'ff_export_types', $default_types );

$selected_post_type = (isset($_POST['export_post_type'])) ? $_POST['export_post_type'] : '';

// function temp_export_get_taxonomy_terms( $post_id, $taxonomies ){

//     $terms_data = [
//         'terms' => [],
//         'parent_terms_data' => [],
//     ];

//     foreach( $taxonomies as $taxonomy ) {
        
//         $terms = wp_get_post_terms( $post_id, $taxonomy );
//         if( !$terms ) continue;
//         $terms_data['terms'] = $terms;

//         $parent_terms_data = get_parent_terms_data($terms);
//         if( $parent_terms_data ) {
//             $terms_data['parent_terms_data'] = $parent_terms_data;
//         }

//     }

//     return $terms_data;
// }

// function get_parent_terms_data($terms){
    
//     $data = [
//         'existing_parents' => [],
//         'terms_with_included_parents_data' => [],
//         'included_parents_data' => [],
//     ];
    
//     foreach( $terms as $term ) {
//         if( $term->parent !== 0 ) {
//             // child

//             if( in_array( $term->parent, $data['existing_parents']) ) {
//                 continue; // parent data already exists
//             }

//             if( !isset($data['included_parents_data'][$term->parent]) ) {
//                 // parent data not included, get data
//                 $included_parent = get_term($term->parent, $term->taxonomy);
//                 $data['included_parents_data'][$term->parent] = $included_parent;
//                 $data['included_parents_data'][$term->parent]->child_terms = [];
//             }

//             $data['included_parents_data'][$term->parent]->child_terms[] = $term->term_id;
//             $data['terms_with_included_parents_data'][] = $term->term_id;
//         }
//         else {
//             // parent
//             if( !in_array( $term->term_id, $data['existing_parents']) ) {
//                 $data['existing_parents'][] = $term->term_id;
//             }
//         }
        
//     }

//     return $data;
// }

// $terms_data = temp_export_get_taxonomy_terms(40, ['category']);
// pre_debug($terms_data);
?>

<div class="ff_ie">

    <div class="ff_ie_left">
        <h1>FF Export</h1>

        <form action="<?php echo get_bloginfo('url'); ?>/wp-admin/admin.php?page=ff-export" id="export_form" method="post">
            <?php
            include_once 'settings/select-type.php';
            include_once 'settings/query-options.php';
            include_once 'settings/data-options.php';
            include_once 'settings/taxonomy-options.php';
            include_once 'settings/buttons.php';
            ?>
        </form>

    </div>

    <div class="ff_ie_right">
        <div class="ff_ie_preview"></div>
    </div>

</div>