<?php
if( !function_exists( 'pre_debug' ) ) {
    function pre_debug( $s, $return = false ) {
        if( $return ) {
            return '<pre>'. print_r( $s, true ) .'</pre>';
        }
        echo '<pre>'. print_r( $s, true ) .'</pre>';
    }
}