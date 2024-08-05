<?php
if( !$selected_post_type ) return;
if( !in_array( $selected_post_type, $default_types ) ) return;

// $prefix = 'query_option_';
$prefix = '';
?>

<div class="more_settings_con mb-30 mt-30">

    <p><span class="button toggle_btn active">Query options</span></p>
    <!-- <h3>Query Options</h3> -->
    
    <div class="more_settings">
        
        <table>
            <tr>
                <td>Showposts</td>
                <td><input type="text" name="<?php echo $prefix; ?>showposts" value="-1"></td>
            </tr>
            <tr>
                <td>Date from</td>
                <td><input type="text" name="<?php echo $prefix; ?>date_from" value="" class="datepicker date_from"></td>
            </tr>
            <tr>
                <td>Date to</td>
                <td><input type="text" name="<?php echo $prefix; ?>date_to" value="" class="datepicker date_to"></td>
            </tr>

            <tr>
                <td>Offset</td>
                <td><input type="text" name="<?php echo $prefix; ?>offset" value=""></td>
            </tr>

        </table>
        
    </div>

</div>