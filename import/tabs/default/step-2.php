<?php
if( !$selected_post_type ) return;
$available_taxonomies = get_object_taxonomies( $selected_post_type );
?>

<p class="import_file_upload">
    <span class="label button">Select file</span>
    <span class="value ml-10"></span>
</p>

<p> 
    Items to process per batch
    <input class="w_80 ml-10" type="number" value=1 id="import_items_to_process_per_batch">
</p>

<div id="ajax_html"></div>

<p><span class="button button-primary" id="import_start">Import Start</span></p>

<p class="import_progress">
    <span class="l">Import progress:</span>
    <span class="current">0</span>
    <span class="sep"> / </span>
    <span class="total">0</span>
    <span class="spinner"></span>
</p>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
    new FF_Import({
        type: '<?php echo $selected_post_type; ?>',
        available_post_types: <?php echo json_encode($post_types); ?>,
        available_taxonomies: <?php echo json_encode($available_taxonomies); ?>,
    });
});
</script>