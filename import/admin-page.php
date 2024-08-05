<?php
if ( ! defined( 'ABSPATH' ) ) die();

wp_enqueue_style( 'ff-import-export', FFIE_PLUGIN_URL . 'assets/css/ff-import-export.css' );

wp_enqueue_media();
wp_enqueue_script( 'ff-import', FFIE_PLUGIN_URL . 'import/js/import.js', [], null, true );

$custom_types = apply_filters( 'ff_import_custom_types', [] );

$tabs = [
    [
        'title' => 'Default',
        'slug' => 'default',
        'file' => FFIE_PLUGIN_DIR . 'import/tabs/default/default.php',
    ],
];

if( $custom_types ) {
    $tabs[] = [
        'title' => 'Custom',
        'slug' => 'custom',
        'file' => FFIE_PLUGIN_DIR . 'import/tabs/custom/custom.php',
    ];
}

$default_tab = $tabs[0]['slug'];
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
?>

<h1>FF Import</h1>

<nav class="nav-tab-wrapper">
    <?php
    foreach( $tabs as $tab ) {
        $tab_url = '?page=ff-import&tab='. $tab['slug'];
        $active = $current_tab == $tab['slug'] ? ' nav-tab-active' : '';
        echo '<a href="'. $tab_url .'" class="nav-tab'. $active .'">'. $tab['title'] .'</a>';
    }
    ?>
</nav>

<div class="ff_ie">
    <?php
    foreach( $tabs as $tab ) {
        if( $current_tab == $tab['slug'] ) {
            include $tab['file'];
        }
    }
    ?>
</div>