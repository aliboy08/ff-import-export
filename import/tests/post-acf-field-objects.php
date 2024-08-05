<?php
$post_id = 135;
$acf_fields = [];
$field_objects = get_field_objects($post_id);
foreach( $field_objects as $field ) {
    $type = $field['type'];
    if( in_array($type, ['image', 'gallery', 'post_object', 'relationship']) ) {
        $acf_fields[$type][] = $field['name'];
    }
}

$item = [];

$post_metas = get_post_meta($post_id);
foreach( $post_metas as $meta_key => $meta_value ) {
    $meta_value = $meta_value[0];

    // Image
    if( in_array( $meta_key, $acf_fields['image'] ) ) {
        $image_id = $meta_value;
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        $item['image_fields'][] = [
            'meta_key' => $meta_key,
            'url' => $image_url,
        ];
    }
    
    // Gallery
    if( in_array( $meta_key, $acf_fields['gallery'] ) ) {
        $image_urls = [];
        $image_ids = maybe_unserialize($meta_value);
        foreach( $image_ids as $image_id ) {
            $image_url = wp_get_attachment_image_url($image_id, 'full');
            if( !$image_url ) continue;
            $image_urls[] = $image_url;
        }

        if( $image_urls ) {
            $item['gallery_fields'][] = [
                'meta_key' => $meta_key,
                'urls' => $image_urls,
            ];
        }
    }

    // Post Object
    if( in_array($meta_key, $acf_fields['post_object']) || in_array($meta_key, $acf_fields['relationship']) ) {

        $post_object_field = [
            'meta_key' => $meta_key,
        ];

        $post_id = maybe_unserialize($meta_value);
        if( is_array( $post_id ) ) {
            // multiple
            $post_ids = $post_id;
            $post_titles = [];
            foreach( $post_ids as $post_id ) {
                $post_titles[] = get_the_title($post_id);
            }
            $post_object_field['post_titles'] = $post_titles;
        }
        else {
            // single 
            $post_object_field['post_title'] = get_the_title($post_id);
        }

        $item['post_object_fields'][] = $post_object_field;
    }
    
}

pre_debug($item);