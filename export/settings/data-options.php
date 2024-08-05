<?php
if( !$selected_post_type ) return;
if( !in_array( $selected_post_type, $default_types ) ) return;
?>

<div class="more_settings_con mb-40 mt-40">

    <p><span class="button toggle_btn active">Data options</span></p>
    
    <div class="more_settings">

        <!-- <h3>Data Options</h3> -->

        <p><label><input type="checkbox" name="include_featured_image" value="1" checked>Include featured image</label></p>

        <p><label><input type="checkbox" name="all_post_metas" value="1">Include all post meta</label></p>

        <p>
            <div class="bold mb-10">Specific post metas (1 for each line)</div>
            <textarea name="specific_post_metas" rows=5 class="w_260"></textarea>
        </p>
        
        <?php if( class_exists( 'ACF' ) ) : ?>
        <p><label><input type="checkbox" name="include_acf_fields" value="1" checked>Include acf fields</label></p>

        <!-- <p><label><input type="checkbox" name="include_acf_repeater" value="1">Include acf repeater</label></p> -->

        <?php endif; ?>
        
    </div>

</div>