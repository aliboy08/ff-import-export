<?php
if( !$selected_post_type ) return;
if( !in_array( $selected_post_type, $default_types ) ) return;
$taxonomies = get_object_taxonomies( $selected_post_type );
if( !$taxonomies ) return;
?>

<div class="more_settings_con mb-40 mt-40">

    <p><span class="button toggle_btn active">Taxonomy options</span></p>
    
    <div class="more_settings">

        <div class="select_taxonomies">

            <?php
            foreach( $taxonomies as $taxonomy ) {
                echo '
                <p>
                    <label>
                        <input type="checkbox" name="taxonomies[]" value="'. $taxonomy .'" checked >
                        Include '. $taxonomy .'
                    </label>
                </p>';
            }
            ?>

        </div>

    </div>

</div>