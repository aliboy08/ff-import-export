<?php
namespace FFIE;

class Export {
    
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_action( 'wp_ajax_ff_export_data', [ $this, 'export_ajax' ] );
        add_action( 'wp_ajax_ff_export_clean', [ $this, 'export_clean_ajax' ] );
        add_action( 'wp_ajax_ff_export_preview', [ $this, 'export_preview_ajax' ] );
    }
    
    function create_admin_menu() {

        $page_title = __( 'Export', 'ff' );
        $menu_title = __( 'Export', 'ff' );
        $capability = 'install_plugins';
        $parent_slug = 'fivebyfive';
        $menu_slug  = 'ff-export';
        $function   = [ $this, 'options_page' ];

        add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    }

    function options_page() {
        include_once 'admin-page.php';
    }

    function export_ajax() {
        $response = [
            // 'debug' => [],
            'post' => $_POST,
            'items' => [],
        ];
        $items = $this->get_items($_POST);
        $response['items'] = $items;
        $response['file'] = $this->create_file($response);
        wp_send_json($response);
    }

    function export_clean_ajax() {
        if( !wp_verify_nonce($_POST['nonce'], "ff_export_nonce") ) exit;
        $file = $_POST['file']['path'];
        if( strpos( $file, 'ff-import-export/temp/') === false ) exit;
        unlink($file);
        exit;
    }

    function export_preview_ajax() {
        $response = [
            // 'debug' => [],
            // 'post' => $_POST,
            'items' => [],
        ];

        // only show 1 item in preview
        $_POST['form_data']['showposts'] = $_POST['form_data']['preview_num'];

        // ob_start();
        $items = $this->get_items($_POST);
        // $response['debug'][] = ob_get_clean();
        
        $response['items'] = $items;
        // $response['debug']['items'] = $items;
        // $response['debug']['count'] = count($items);

        $preview_data = $items ? $items : 'No results...';

        $response['preview_html'] = '<pre>'. print_r($preview_data, true) .'</pre>';

        wp_send_json($response);
    }
    
    function get_items($payload){
        $form_data = $payload['form_data'];
        $type = $form_data['export_post_type'];

        if( $payload['is_custom'] ) {
            $items = apply_filters( 'ff_export_data_'. $type, [], $this );
        }
        else {
            $items = $this->get_items_default( $form_data );
        }

        return $items;
    }

    function get_items_default( $form_data ){

        $args = $this->get_query_args($form_data);
        $data_settings = $this->get_data_settings($form_data);

        $query = new \WP_Query($args);

        $items = [];
        
        foreach( $query->posts as $post ) {

            $item = [
                'post_data' => $post,
                'post_meta' => $this->get_post_meta_data($post->ID, $data_settings),
                'featured_image' => $this->get_featured_image_url($post->ID, $data_settings),
            ];

            if( $data_settings['include_acf_fields'] ) {
                $item['acf_fields'] = $this->get_acf_fields( $post->ID );
            }
            
            if( $data_settings['include_taxonomies'] ) {
                $item['taxonomies'] = $this->get_taxonomy_terms( $post->ID, $form_data['taxonomies'] );
            }
            
            $items[] = $item;
        }

        return $items;
    }

    function get_query_args($form_data){

        $post_type = $form_data['export_post_type'];
        $num = -1;

        if( isset($form_data['showposts']) ) {
            $num = (int)$form_data['showposts'];
        }

        $args = [
            'post_type' => $post_type,
            'showposts' => $num,
        ];

         if( isset($form_data['offset']) ) {
            $args['offset'] = (int)$form_data['offset'];
        }

        $date_query = [];
        if( isset($form_data['date_from']) ) {
            $date_query['after'] = $this->get_date_arr($form_data['date_from']);
        }

        if( isset($form_data['date_to']) ) {
            $date_query['before'] = $this->get_date_arr($form_data['date_to']);
        }

        if( $date_query ) {
            $date_query['inclusive'] = true;
            $args['date_query'] = $date_query; 
        }

        return $args;
    }

    function get_data_settings($form_data){
        $data_settings = [];
        $data_settings['featured_image'] = isset($form_data['include_featured_image']);
        $data_settings['post_meta'] = $this->get_data_settings_post_meta($form_data);
        $data_settings['include_acf_fields'] = isset($form_data['include_acf_fields']);
        $data_settings['include_taxonomies'] = isset($form_data['taxonomies']);
        return $data_settings;
    }
    
    function get_data_settings_post_meta($form_data){
        $post_metas = [
            'specific' => [],
            'all' => false,
        ];

        if( isset($form_data['specific_post_metas']) ) {
            $items = explode(PHP_EOL, $form_data['specific_post_metas']);
            foreach( $items as $item ) {
                $item = trim($item);
                if( !$item ) continue;
                $post_metas['specific'][] = $item;
            }
        }

        if( isset($form_data['all_post_metas']) && !count($post_metas['specific']) ) {
            $post_metas['all'] = true;
        }
        
        return $post_metas;
    }

    function get_post_meta_data($post_id, $data_settings){

        $post_metas = [];

        if ( $data_settings['post_meta']['all'] ) {
            // all post metas
            $post_metas = get_post_meta($post_id);
        }
        else if( count($data_settings['post_meta']['specific']) ) {
            // specific post metas
            foreach( $data_settings['post_meta']['specific'] as $post_meta_key ) {
                $post_meta = get_post_meta( $post_id, $post_meta_key, true );
                if( $post_meta ) {
                    $post_metas[$post_meta_key] = $post_meta;
                }
            }
        }
        
        return $post_metas;
    }
    
    function get_acf_fields($post_id){
        
        $acf_fields = get_field_objects($post_id);
        if( !$acf_fields ) return [];

        $include_fields = [
            'name',
            'type',
            'value',
            'post_type',
        ];

        $post_acf_fields = [];
        foreach( $acf_fields as $acf_field ){
            if( !$acf_field['value'] ) continue;

            $field = $this->acf_field_format_values($acf_field);

            $format_field = [];
            foreach( $field as $key => $key_value ) {
                if( !$key_value ) continue;
                if( !in_array( $key, $include_fields ) ) continue;
                $format_field[$key] = $key_value;
            }

            $post_acf_fields[] = $format_field;
        }

        return $post_acf_fields;
    }

    function acf_field_format_values( $field ){

        $type = $field['type'];
        
        if( $type == 'image' ) {

            $field['value'] = $field['value']['url'];

        }
        else if ( $type == 'gallery' ) {
            
            $urls = [];
            foreach( $field['value'] as $image ) {
                $urls[] = $image['url'];
            }
            $field['value'] = $urls;

        }
        else if ( $type == 'post_object' || $type == 'relationship' ) {
            
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
        else if ( $type == 'repeater' || $type == 'flexible_content' ) {
            $field = $this->acf_field_format_group_multiple( $field );
        }
        else if ( $type == 'group' ) {
            $field = $this->acf_field_format_group( $field );
        }

        return $field;
    }
    
    function acf_field_format_group( $field ){
        $format_value = [];
        foreach( $field['value'] as $sub_key => $sub_value ) {
            $format_value[$sub_key] = $this->acf_field_format_sub_field($sub_value);
        }
        $field['value'] = $format_value;
        return $field;
    }

    function acf_field_format_group_multiple( $field ){
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

            if( 
                isset( $sub_field['type'] ) &&
                $sub_field['type'] == 'image'
            ) {
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

    function get_taxonomy_terms( $post_id, $taxonomies ){

        $taxonomies_data = [];
        $parent_terms = [];

        foreach( $taxonomies as $taxonomy ) {
            
            $terms = wp_get_post_terms($post_id, $taxonomy);
            if( !$terms ) continue;

            $final_terms = [];
            foreach( $terms as $term ) {
                if( $term->slug == 'uncategorized' ) continue;
                
                if( $term->parent ) {
                    $term->nested = [];
                    $this->set_nested_terms($term, $parent_terms, $term->nested);
                }
                
                $final_terms[] = $term;
            }
            
            $taxonomies_data[] = [
                'taxonomy' => $taxonomy,
                'terms' => $final_terms,
                'parent_terms' => $parent_terms,
            ];
        }
    
        return $taxonomies_data;
    }

    function set_nested_terms(&$term, &$parent_terms, &$term_nest){

        $parent_term = get_term($term->parent, $term->taxonomy);

        if( !isset($parent_terms[$parent_term->term_id]) ) {
            $parent_terms[$parent_term->term_id] = $parent_term;
        }

        $term_nest[$parent_term->term_id] = [
            'id' => $parent_term->term_id,
        ];
        
        if( $parent_term->parent ) {
            $term_nest[$parent_term->term_id]['parent'] = [];
            $this->set_nested_terms($parent_term, $parent_terms, $term_nest[$parent_term->term_id]['parent']);
        }
    }

    // function get_taxonomy_terms_old( $post_id, $taxonomies ){

    //     $taxonomy_terms_all = [];

    //     foreach( $taxonomies as $taxonomy ) {
            
    //         $terms = wp_get_post_terms( $post_id, $taxonomy );
    //         if( !$terms ) continue;

    //         $taxonomy_terms = [
    //             'taxonomy' => $taxonomy,
    //             'terms' => [],
    //         ];
    //         foreach( $terms as $term ) {
    //             $taxonomy_terms['terms'][] = $term->name;
    //         }

    //         $taxonomy_terms_all[] = $taxonomy_terms;

    //     }

    //     return $taxonomy_terms_all;
    // }

    // function get_parent_terms_data($terms){
    
    //     $data = [
    //         'existing_parents' => [],
    //         'included_parents' => [],
    //         'included_parents_terms' => [],
    //     ];
        
    //     foreach( $terms as $term ) {
    //         if( $term->parent !== 0 ) {
    //             // child
                
    //             if( in_array( $term->parent, $data['existing_parents']) ) {
    //                 continue; // parent data already included
    //             }
    
    //             if( !isset($data['included_parents'][$term->parent]) ) {
    //                 // parent data not included, get data
    //                 $included_parent = get_term($term->parent, $term->taxonomy);
    //                 $data['included_parents'][$term->parent] = $included_parent;
    //                 $data['included_parents'][$term->parent]->child_terms = [];
    //             }
    
    //             $data['included_parents'][$term->parent]->child_terms[] = $term->term_id;
    //             $data['included_parents_terms'][] = $term->term_id;
    //         }
    //         else {
    //             // parent
    //             if( !in_array( $term->term_id, $data['existing_parents']) ) {
    //                 $data['existing_parents'][] = $term->term_id;
    //             }
    //         }
            
    //     }
    
    //     return $data;
    // }

    function get_featured_image_url( $post_id, $data_settings = false ) {
        if( $data_settings && !$data_settings['featured_image'] ) return '';
        if( !has_post_thumbnail( $post_id ) ) return '';
        return get_the_post_thumbnail_url($post_id, 'full');
    }

    function get_date_arr($date_string){
        // format: MM/DD/YYYY
        $temp = explode('/',  $date_string);
        return [
            'year' => $temp[2],
            'month' => $temp[0],
            'day' => $temp[1],
        ];
    }

    function create_file($data){

        $type = $data['post']['form_data']['export_post_type'];

        $file_extension = 'json';
        $file_name = 'export-'. $type . '-' . date('Y-m-d-h-i-s') .'.'. $file_extension;
        $file_path = FFIE_PLUGIN_DIR .'temp/'. $file_name;
        $file = fopen($file_path, "w") or die('Unable to create file');
        
        $content = json_encode($data['items']);
        fwrite($file, $content);
        fclose($file);

        return [
            'path' => $file_path,
            'url' => FFIE_PLUGIN_URL . 'temp/'. $file_name,
            'name' => $file_name,
        ];
    }

    function get_array_fields($arr){
        if( !is_array($arr) || !isset($arr[0]) ) return [];
        $array_item = (array)$arr[0];
        return array_keys($array_item);
    }
    
}