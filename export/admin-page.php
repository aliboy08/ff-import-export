<?php
if ( ! defined( 'ABSPATH' ) ) die();
wp_enqueue_style( 'ff-import-export', FFIE_URL . '/assets/css/ff-import-export.css' );
wp_enqueue_style( 'jquery-datepicker', '//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css' );

wp_enqueue_script( 'ff-export', FFIE_URL . '/export/js/export.js', [ 'jquery-ui-datepicker' ], null, true );

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