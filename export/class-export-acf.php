<?php
class FF_Export_ACF {
    
    function get_acf_fields($post_id){
        $include_fields = [
            'name',
            'type',
            'value',
            'post_type',
        ];

        $post_acf_fields = [];

        $acf_fields = get_field_objects($post_id);
        foreach( $acf_fields as $acf_field ){
            if( !$acf_field['value'] ) continue;
            $post_acf_fields[] = $this->acf_field_trim_data( $acf_field, $include_fields );
        }

        return $post_acf_fields;
    }

    function acf_field_trim_data( $field, $include_fields ){

        $field = $this->acf_field_format_values($field);

        $format_field = [];
        foreach( $field as $key => $key_value ) {
            if( !$key_value ) continue;
            if( !in_array( $key, $include_fields ) ) continue;
            $format_field[$key] = $key_value;
        }

        return $format_field;
    }

    function acf_field_format_values( $field ){

        $type = $field['type'];
        
        if( $type == 'image' ) {
            
            // Image
            $field['value'] = $field['value']['url'];

        }
        else if ( $type == 'gallery' ) {

            // Gallery
            $urls = [];
            foreach( $field['value'] as $image ) {
                $urls[] = $image['url'];
            }
            $field['value'] = $urls;

        }
        else if ( $type == 'post_object' || $type == 'relationship' ) {
            
            // Post Object & Relationship
            if( is_array( $field['value'] ) ) {
                // multiple
                $titles = [];
                foreach( $field['value'] as $post_id ) {
                    $titles[] = get_the_title($post_id);
                }
                $field['value'] = $titles;
            }
            else {
                // single
                $field['value'] = get_the_title($field['value']);
            }

        }
        else if ( $type == 'repeater' ) {

            // Repeater
            $field = $this->acf_field_format_repeater( $field );
        }

        return $field;
    }
    
    function acf_field_format_repeater( $field ){
        
        $format_value = [];
        foreach( $field['value'] as $row ) {
            $format_sub_value = [];
            foreach( $row as $sub_key => $sub_value ) {
                $format_sub_value[$sub_key] = $this->acf_field_format_sub_field($sub_value);
            }
            $format_value[] = $format_sub_value;
        }
        
        $field['value'] = $format_value;
        return $field;
    }

    function acf_field_format_sub_field( $sub_field ){

        if ( is_object( $sub_field ) ) {
            // Post Object - Single
            return $this->acf_repeater_object_data($sub_field);
        }

        if( is_array( $sub_field ) ) {

            if( $sub_field['type'] == 'image' ) {
                // Image - Single
                return $this->acf_repeater_image_data($sub_field);
            }
            
            if ( 
                is_array( $sub_field[0] ) &&
                isset( $sub_field[0]['type'] ) &&
                $sub_field[0]['type'] == 'image'
            ) {
                // Gallery - Multiple images
                return $this->acf_repeater_gallery_data($sub_field);
            }

            if ( is_object( $sub_field[0] ) ) {
                // Post Objects - Multiple
                return $this->acf_repeater_object_data_multiple($sub_field);
            }

        }
        
        return $sub_field;
    }

    function acf_repeater_image_data( $sub_field ){
        return [
            'type' => 'image',
            'value' => $sub_field['url'],
        ];
    }

    function acf_repeater_gallery_data( $sub_field ){
        $urls = [];
        foreach( $sub_field as $image ) {
            $urls[] = $image['url'];
        }
        return [
            'type' => 'gallery',
            'value' => $urls,
        ];
    }

    function acf_repeater_object_data( $sub_field ){
        return [
            'type' => 'post_object',
            'post_type' => $sub_field->post_type,
            'post_title' => $sub_field->post_title,
        ];
    }

    function acf_repeater_object_data_multiple( $sub_field ){

        $post_objects = [];
        foreach( $sub_field as $post_object ) {
            $post_objects[] = [
                'post_type' => $post_object->post_type,
                'post_title' => $post_object->post_title,
            ];
        }

        return [
            'type' => 'post_objects',
            'value' => $post_objects,
        ];
    }

}