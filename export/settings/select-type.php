<p>
    <select id="export_type" name="export_post_type" onchange="this.closest('form').submit()">
        <option value="">Select Type</option>
        <?php
        foreach( $types as $value => $label ) {
            $selected = ( $selected_post_type == $value ) ? ' selected="selected"' : '';
            echo '<option value="'. $value .'"'. $selected .'>'. $label .'</option>';
        }
        ?>
    </select>
</p>