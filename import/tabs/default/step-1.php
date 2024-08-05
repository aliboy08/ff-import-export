<form action="<?php echo get_bloginfo('url'); ?>/wp-admin/admin.php?page=ff-import&tab=default" method="post">

    <p class="flex v-center gap-20">

        <label class="bold">Select Type</label>

        <select name="import_post_type" onchange="this.closest('form').submit()">
            <option value="">Select</option>
            <?php
            foreach( $types as $value => $label ) {
                $selected = ( $selected_post_type == $value ) ? ' selected="selected"' : '';
                echo '<option value="'. $value .'"'. $selected .'>'. $label .'</option>';
            }
            ?>
        </select>
    </p>

</form>