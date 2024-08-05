<div class="ff_ie_left">

    <?php
    $selected_post_type = isset($_POST['import_post_type']) ? $_POST['import_post_type'] : '';

    $post_types = get_post_types([
        'public' => true,
    ]);

    $exclude_types = [
        'attachment',
    ];

    $types = [];
    foreach( $post_types as $post_type ) {
        if( in_array( $post_type, $exclude_types ) ) continue;
        $types[$post_type] = $post_type;
    }
    ?>
    
    <?php
    include 'step-1.php';
    include 'step-2.php';
    ?>
    
</div>

<div class="ff_ie_right">
    <div class="ff_ie_preview"></div>
</div>