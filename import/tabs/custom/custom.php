<div class="ff_ie_left">
    
    <p class="flex v-center gap-20">

        <label class="bold">Select Type</label>

        <select class="on_change_set" data-option-key="custom_type">
            <option value="">Select</option>
            <?php
            foreach( $custom_types as $value => $label ) {
                echo '<option value="'. $value .'">'. $label .'</option>';
            }
            ?>
        </select>
    </p>

    <p class="import_file_upload">
        <span class="label button">Select file</span>
        <span class="value ml-10"></span>
    </p>

    <p> 
        Items to process per batch
        <input class="w_80 ml-10" type="number" value=1 id="import_items_to_process_per_batch">
    </p>

    <div id="ajax_html"></div>
    
    <p><span class="button button-primary" id="import_start">Start</span></p>

    <p class="import_progress">
        <span class="l">Import progress:</span>
        <span class="current">0</span>
        <span class="sep"> / </span>
        <span class="total">0</span>
        <span class="spinner"></span>
    </p>

</div>

<div class="ff_ie_right">
    <div class="ff_ie_preview"></div>
</div>

<?php
wp_enqueue_script( 'ff-import-custom', FFIE_PLUGIN_URL . 'import/tabs/custom/js/import-custom.js', ['ff-import'], null, true );
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new FF_Import({
        type: 'custom',
    });
});
</script>