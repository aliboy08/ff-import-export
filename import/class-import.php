<?php
namespace FFIE;

use \WP_Query as WP_Query;

class Import {

    public $template_dir = FFIE_PLUGIN_DIR .'import/templates/';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_filter( 'upload_mimes', [ $this, 'allow_json' ] );

        add_action( 'wp_ajax_ff_import_prepare', [ $this, 'prepare_ajax' ] );
        add_action( 'wp_ajax_ff_import_process_items', [ $this, 'process_items_ajax' ] );
    }

    function create_admin_menu() {

        $page_title = __( 'Import', 'ff' );
        $menu_title = __( 'Import', 'ff' );
        $capability = 'install_plugins';
        $parent_slug = 'fivebyfive';
        $menu_slug  = 'ff-import';
        $function   = [ $this, 'options_page' ];

        add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    }

    function prepare_ajax() {
        $response = [
            'post' => $_POST,
            'items' => [],
            'preview_html' => '',
        ];

        $items = file_get_contents($_POST['file']['url']);
        if( $items ) {
            $items = json_decode($items, true);
            $response['items'] = $items;
            $response['preview_html'] = '<pre>'. print_r($items, true) .'</pre>';
        }
    
        wp_send_json($response);
    }

    function process_items_ajax() {
        // Custom
        if( isset($_POST['custom_type']) ) {
            $this->process_items_custom();
            exit;
        }

        // Default
        $this->process_items_default();
        exit;
    }

    function process_items_default(){
        $response = [
            'post' => $_POST,
            'debug' => [
                'process_items_default'
            ],
        ];

        $post_type = $_POST['type'];
        $items = $_POST['items'] ?? [];
        $map_fields = $_POST['map_fields'] ?? [];

        $response['debug']['map_fields'] = $map_fields;

        ob_start();
        foreach( $items as $item ) {
            $post_id = $this->create_update_item_post( $item, $post_type, $map_fields );
            $response['debug']['post_ids'][] = $post_id;
            $this->update_item_default( $post_id, $item, $map_fields );
        }
        $response['debug'][] = ob_get_clean();

        wp_send_json($response);
    }

    function process_items_custom(){
        $response = [
            // 'post' => $_POST,
        ];

        $items = $_POST['items'];
        
        ob_start();
        foreach( $items as $item ) {
            do_action('ff_import_data_'. $_POST['custom_type'], $item, $this);
        }
        $response['debug'] = ob_get_clean();

        wp_send_json($response);
    }

    function allow_json( $mimes ){
        $mimes['json'] = 'application/json';
        return $mimes;
    }

    function options_page() {
        include_once 'admin-page.php';
    }
    
    function create_update_item_post( $item, $post_type, $map_fields = [] ){

        if( !isset($item['post_data']) ) $item['post_data'] = [];
        if( !isset($map_fields['post_data']) ) $map_fields['post_data'] = [];

        $post_title = $item['post_data']['post_title'];

        $post_id = $this->get_post_id( $post_title, $post_type );

        if( !$post_id ) {
            // create new post
            $post_data = $this->prepare_data_create_post( $post_type, $item['post_data'], $map_fields['post_data'] );
            $post_id = wp_insert_post($post_data);
        }
        else {
            // post already exists - update post data
            $post_data = $this->prepare_data_update_post( $post_id, $item['post_data'], $map_fields['post_data'] );
            if( $post_data ) {
                wp_update_post($post_data);
            }
        }

        return $post_id;
    }

    function update_item_default( $post_id, $item, $map_fields ){
        if( !$post_id ) return;

        // others
        // featured image
        $this->update_item_data_others( $post_id, $item, $map_fields );

        // image_fields
        // $this->update_item_data_image_fields( $post_id, $item, $map_fields );

        // // gallery_fields
        // $this->update_item_data_gallery_fields( $post_id, $item, $map_fields );

        // // post_object_fields
        // $this->update_item_data_post_object_fields( $post_id, $item, $map_fields );

        // post metas
        $this->update_item_data_post_metas( $post_id, $item, $map_fields );

        // acf_fields
        $this->update_item_data_acf_fields( $post_id, $item, $map_fields );

        // taxonomies
        $this->update_item_data_taxonomies( $post_id, $item, $map_fields );

    }

    function update_item_data_others( $post_id, $item, $map_fields ) {
        if( !$post_id ) return;
        if( !isset($map_fields['others']) ) return;

        foreach( $map_fields['others'] as $field ) {
            
            // featured image
            if( $field['key'] == 'featured_image' ) {
                if( !isset($item['featured_image']) ) continue;
                $this->update_post_thumbnail( $post_id, $item['featured_image'] );
            }

        }
    }

    function update_item_data_post_metas( $post_id, $item, $map_fields ) {
        if( !$post_id ) return;

        $group_key = 'post_meta';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[ $field['key'] ] = $field;
        }

        $metas = [];
        foreach( $item[$group_key] as $meta_key => $meta_value ) {

            // only include selected fields
            if( !isset( $selected_fields[$meta_key] ) ) continue;

            $map_to_key = $selected_fields[$meta_key]['map_to'];
            if( $map_to_key ) $meta_key = $map_to_key;

            $metas[$map_to_key] = $meta_value[0];
            
        }
        
        if( !$metas ) return;
        $this->update_post_metas( $post_id, $metas );

    }

    function update_item_data_acf_fields( $post_id, $item, $map_fields ) {
        if( !class_exists( 'ACF' ) ) return;
        if( !$post_id ) return;

        $group_key = 'acf_fields';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[ $field['key'] ] = $field;
        }

        $metas = [];
        foreach( $item[$group_key] as $item_group_field ) {

            $meta_key = $item_group_field['name'];

            // only include selected fields
            if( !isset( $selected_fields[$meta_key] ) ) continue;

            $map_to_key = $selected_fields[$meta_key]['map_to'];
            if( !$map_to_key ) $map_to_key = $meta_key; // fallback - empty key

            $type = $item_group_field['type'];
            
            // Image Fields
            if( $type == 'image' || $type == 'gallery' ) {
                
                $img_ids = $this->get_image_ids( $item_group_field['value'] );
                if( $img_ids ) {
                    $metas[$map_to_key] = $img_ids;
                }
                
            }
            else if( $type == 'file' ) {

                $attachment_id = $this->upload_file( $item_group_field['value']['url'] );
                if( $attachment_id ) {
                    $metas[$map_to_key] = $attachment_id;
                }
                
            }
            // Post Fields
            else if ( $type == 'post_object' || $type == 'relationship' ) {
                
                $map_to_post_type = $selected_fields[$meta_key]['map_post_type'];
                if( !$map_to_post_type ) {
                    // default
                    $map_to_post_type = $item_group_field['post_type'];
                }

                if( $map_to_post_type == 'title_only' ) {
                    $metas[$map_to_key] = $item_group_field['value'];
                }
                else {
                    $post_ids = $this->get_post_ids_from_title( $item_group_field['value'], $map_to_post_type );
                    if( $post_ids ) {
                        $metas[$map_to_key] = $post_ids;
                    }
                }
                
            }
            else if ( $type == 'repeater' || $type == 'flexible_content' ) {
                $this->acf_update_field_rows( $post_id, $item_group_field['value'], $map_to_key );
            }
            else if ( $type == 'group' ) {
                $this->acf_update_field_group( $post_id, $item_group_field['value'], $map_to_key );
            }
            else {
                $metas[$map_to_key] = $item_group_field['value'];
            }
            
        }
        
        if( !$metas ) return;
        $this->update_post_metas( $post_id, $metas );

    }
    
    function update_item_data_image_fields( $post_id, $item, $map_fields ) {
        if( !$post_id ) return;

        $group_key = 'image_fields';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[ $field['key'] ] = $field['map_to'];
        }

        $metas = [];
        foreach( $item[$group_key] as $item_group_field ) {
            $meta_key = $item_group_field['meta_key'];

            // only include selected fields
            if( !isset( $selected_fields[ $meta_key ] ) ) continue;

            $map_to_key = $selected_fields[ $meta_key ];
            if( !$map_to_key ) $map_to_key = $meta_key; // fallback - empty key
            
            $image_url = $item_group_field['url'];

            $attachment_id = $this->upload_image_from_url( $image_url, $post_id );
            if( $attachment_id ) {
                $metas[$map_to_key] = $attachment_id;
            }
        }

        if( !$metas ) return;

        $this->update_post_metas( $post_id, $metas );
    }

    function update_item_data_gallery_fields( $post_id, $item, $map_fields ) {
        if( !$post_id ) return;

        $group_key = 'gallery_fields';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[ $field['key'] ] = $field['map_to'];
        }

        $metas = [];
        foreach( $item[$group_key] as $item_group_field ) {

            $meta_key = $item_group_field['meta_key'];

            // only include selected fields
            if( !isset( $selected_fields[ $meta_key ] ) ) continue;

            $map_to_key = $selected_fields[ $meta_key ];
            if( !$map_to_key ) $map_to_key = $meta_key; // fallback - empty key

            $image_urls = $item_group_field['urls'];

            $image_ids = [];
            foreach( $image_urls as $image_url ) {
                $image_id = $this->upload_image_from_url( $image_url, $post_id );
                if( $image_id ) {
                    $image_ids[] = $image_id;
                }
            }
            
            if( $image_ids ) {
                $metas[$map_to_key] = $image_ids;
            }
        }

        if( !$metas ) return;

        $this->update_post_metas( $post_id, $metas );
        
    }

    function update_item_data_post_object_fields( $post_id, $item, $map_fields ) {
        if( !$post_id ) return;

        $group_key = 'post_object_fields';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[ $field['key'] ] = $field;
        }

        $metas = [];
        foreach( $item[$group_key] as $item_group_field ) {

            $meta_key = $item_group_field['meta_key'];

            // only include selected fields
            if( !isset( $selected_fields[ $meta_key ] ) ) continue;

            $map_to_key = $selected_fields[$meta_key]['key'];
            if( !$map_to_key ) $map_to_key = $meta_key; // fallback - empty key

            $map_to_post_type = '';
            if( isset( $selected_fields[ $meta_key ]['map_post_type'] ) ) {
                $map_to_post_type = $selected_fields[ $meta_key ]['map_post_type'];
                if( $map_to_post_type == 'title_only' ) $map_to_post_type = ''; 
            }

            $set_meta_value = '';
            $meta_value = '';
            if( isset( $item_group_field['post_titles'] ) ) {
                // multiple
                $meta_value = $item_group_field['post_titles'];
            }
            else if ( isset( $item_group_field['post_title'] ) ) {
                // single
                $meta_value = $item_group_field['post_title'];
            }
            
            if( !$map_to_post_type ) {
                // Title only
                $set_meta_value = $meta_value;
            }
            else {
                // get post ids from title
                if( is_array( $meta_value ) ) {
                    // multiple
                    $set_meta_value = [];
                    foreach( $meta_value as $post_title ) {
                        $item_post_id = $this->get_post_id( $post_title, $map_to_post_type );
                        if( $item_post_id ) {
                            $set_meta_value[] = $item_post_id;
                        }
                    }
                }
                else {
                    // single
                    $item_post_id = $this->get_post_id( $meta_value, $map_to_post_type );
                    if( $item_post_id ) {
                        $set_meta_value = $item_post_id;
                    }
                }
            }
            

            if( $set_meta_value ) $metas[ $map_to_key ] = $set_meta_value;
            
        }
        
        if( !$metas ) return;

        $this->update_post_metas( $post_id, $metas );
        
    }

    function get_acf_group_field_values( $data ){
        
        $values = [];

        foreach( $data as $key => $value ) {

            if( !is_array( $value ) ) {
                $values[$key] = $value;
                continue;
            } 
            
            $type = $value['type'];

            // Image fields
            if( $type == 'image' || $type == 'gallery' ) {
                $img_ids = $this->get_image_ids( $value['value'] );
                if( $img_ids ) {
                    $values[$key] = $img_ids;
                }
            }
            // Post fields - single
            else if ( $type == 'post_object' ) {
                $query_post_id = $this->get_post_ids_from_title( $value['post_title'], $value['post_type'] );
                $values[$key] = $query_post_id ? $query_post_id : $value;
            }
            // Post fields - multiple
            else if( $type == 'post_objects' ) {
                $query_post_ids = [];
                foreach( $value['value'] as $post ) {
                    $query_post_id = $this->get_post_ids_from_title( $post['post_title'], $post['post_type'] );
                    if( $query_post_id ) $query_post_ids[] = $query_post_id;
                }
                $values[$key] = $query_post_ids ? $query_post_ids : $value;
            }

        }

        return $values;

    }

    function acf_update_field_rows( $post_id, $data, $meta_key ){

        $meta_value = [];
        foreach( $data as $row ) {
            $row_values = $this->get_acf_group_field_values( $row );
            if( $row_values ) {
                $meta_value[] = $row_values;
            }
        }
        
        if( !$meta_value ) return;

        update_field( $meta_key, $meta_value, $post_id );
    }

    function acf_update_field_group( $post_id, $data, $meta_key ) {
        $meta_value = $this->get_acf_group_field_values( $data );
        if( !$meta_value ) return;

        update_field( $meta_key, $meta_value, $post_id );
    }

    function update_item_data_taxonomies( $post_id, $item, $map_fields ) {

        $group_key = 'taxonomies';

        if( !isset($item[$group_key]) ) return;
        if( !isset($map_fields[$group_key]) ) return;
        
        $selected_fields = [];
        foreach( $map_fields[$group_key] as $field ) {
            $selected_fields[$field['key']] = $field;
        }

        foreach( $item[$group_key] as $item_group_field ) {

            $taxonomy = $item_group_field['taxonomy'];

            // only include selected fields
            if( !isset( $selected_fields[ $taxonomy ] ) ) continue;

            $map_to_taxonomy = $selected_fields[$taxonomy]['map_taxonomy'];
            if( !$map_to_taxonomy ) continue;

            $item_group_field['taxonomy'] = $map_to_taxonomy;
            
            $terms = $item_group_field['terms'];

            $this->create_terms( $item_group_field );

            $this->assign_terms( $post_id, $terms, $map_to_taxonomy, true );

        }

    }

    function prepare_data_create_post( $post_type, $item_post_data, $map_fields_post_data ) {

        $post_data = [
            'post_type' => $post_type,
            'post_title' => $item_post_data['post_title'],
        ];

        $exclude_fields = [
            'ID',
        ];

        foreach( $map_fields_post_data as $field ) {

            $field_key = $field['key'];

            // exclude field
            if( in_array( $field_key, $exclude_fields ) ) continue;

            // already set
            if( isset( $post_data[$field_key] ) ) continue;

            // no item data
            if( !isset( $item_post_data[$field_key] ) ) continue;

            $post_data[$field_key] = $item_post_data[$field_key];
        }

        // set publish default
        // if( !isset($map_fields['post_status']) ) {
        //     $post_data['post_status'] = 'publish';
        // }

        return $post_data;
    }

    function prepare_data_update_post( $post_id, $item_post_data, $map_fields_post_data ) {

        if( !$map_fields_post_data ) return false;

        $post_data = [
            'ID' => $post_id,
        ];

        $exclude_fields = [
            'post_type',
        ];

        foreach( $map_fields_post_data as $field ) {

            $field_key = $field['key'];

            // exclude field
            if( in_array( $field_key, $exclude_fields ) ) continue;

            // already set
            if( isset( $post_data[$field_key] ) ) continue;

            // no item data
            if( !isset( $item_post_data[$field_key] ) ) continue;

            $post_data[$field_key] = $item_post_data[$field_key];
        }

        return $post_data;
    }

    function get_term_id( $term_name, $taxonomy ) {
        $term = term_exists( $term_name, $taxonomy );
        if( $term ) {
            return $term['term_id'];
        }
        else {
            // create new
            $term_id = wp_insert_term( $term_name, $taxonomy );
            return $term_id;
        }
        return false;
    }

    function remove_extra_spaces($str){
        $str = str_replace(PHP_EOL, ' ', $str);
        $str = str_replace('   ', ' ', $str);
        $str = str_replace('  ', ' ', $str);
        return $str;
    }
    
    function update_post_thumbnail( $post_id, $image_url ){
        if( !$post_id || !$image_url ) return;
        $image_id = $this->upload_image_from_url( $image_url, $post_id );
        if( $image_id ) {
            update_post_meta( $post_id, '_thumbnail_id', $image_id );
        }
    }

    function update_post_metas($post_id, $metas) {

        foreach( $metas as $meta_key => $meta_value ) {

            if( !$meta_value ) {
                continue;
            }

            // if( is_array( $meta_value ) ) {
            //     // array - do not check change
            //     update_post_meta($post_id, $meta_key, $meta_value);
            //     continue;
            // }
            
            // only update if value changed
            $current_meta = get_post_meta( $post_id, $meta_key, true);
            if( $current_meta == $meta_value ) {
                continue; // skip, no change
            }

            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }

    function get_image_ids( $image_url ) {

        if( !$image_url ) return false;

        if( is_array( $image_url ) ) {
            // multiple
            $image_urls = $image_url;
            $image_ids = [];
            foreach( $image_urls as $image_url ) {
                $image_id = $this->upload_image_from_url( $image_url );
                if( $image_id ) {
                    $image_ids[] = $image_id;
                }
            }
            return $image_ids;
        }
        else {
            // single
            $image_id = $this->upload_image_from_url( $image_url );
            return $image_id;
        }
    }

    function get_post_ids_from_title( $post_title, $post_type ) {

        if( !$post_title ) return false;

        if( is_array( $post_title ) ) {
            // multiple
            $post_titles = $post_title;
            $post_ids = [];
            foreach( $post_titles as $post_title ) {
                $post_id = $this->get_post_id( $post_title, $post_type );
                if( $post_id ) {
                    $post_ids[] = $post_id;
                }
            }
            return $post_ids;
        }
        else {
            // single
            return $this->get_post_id( $post_title, $post_type );
        }
    }

    function get_post_id( $title, $post_type ){

        // fix for title search issue
        $title = $this->text_normalize($title);

        $args = [
            'post_type' => $post_type,
            'title' => $title,
            'showposts' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
        ];
        $q = new WP_Query($args);
        
        if( !$q->posts ) return false;
        return $q->posts[0];
    }

    function text_normalize($text){

        // fix for title search issue
        $text = str_replace('–','-', $text);

        return $text;
    }

    function assign_terms($post_id, $terms, $taxonomy, $append = false ) {

        $term_ids = [];
    
        foreach( $terms as $term ) {
            
            $term_name = $term['name'];

            $term = term_exists( $term_name, $taxonomy );
    
            if( $term ) {
                $term_id = $term['term_id'];
            }
            else {
                $term_id = wp_insert_term( $term_name, $taxonomy );
            }
            
            if( $term_id ) {
                $term_ids[] = $term_id;
            }
        }
    
        if( $term_ids ) {
            wp_set_post_terms( $post_id, $term_ids, $taxonomy, $append );
        }
        
    }

    function create_terms($taxonomy_data){
        foreach( $taxonomy_data['terms'] as $term ) {
            // create parent terms
            $this->create_nested_term_parents($term, $taxonomy_data);

            // create current term
            $parent_id = $this->get_parent_term_id($term, $taxonomy_data);
            $this->create_term($term, $taxonomy_data['taxonomy'], $parent_id);
        }
    }
    
    function create_nested_term_parents($term, $taxonomy_data){
        if( !isset($term['nested']) || !$term['nested'] ) return;
    
        $taxonomy = $taxonomy_data['taxonomy'];
    
        $parent_term_ids = [];
        foreach( $term['nested'] as $sub_level ) {
            $this->check_nested_parent_term_ids($sub_level, $parent_term_ids);
        }
        // create deepset levels first
        $parent_term_ids = array_reverse($parent_term_ids);
    
        foreach( $parent_term_ids as $parent_id ) {
            $parent = $this->get_parent_term_data($parent_id, $taxonomy_data['parent_terms']);
            $grand_parent_id = $this->get_parent_term_id($parent, $taxonomy_data);
            $this->create_term($parent, $taxonomy, $grand_parent_id);
        }
        
    }
    
    function check_nested_parent_term_ids($term_nested, &$parent_term_ids){
        $parent_term_ids[] = $term_nested['id'];
        if( isset($term_nested['parent']) ) {
            foreach( $term_nested['parent'] as $sub_level ) {
                $this->check_nested_parent_term_ids( $sub_level, $parent_term_ids );
            }
        }
    }
    
    function get_parent_term_data($parent_id, $parent_terms){
        foreach( $parent_terms as $parent_term ) {
            if( $parent_term['term_id'] == $parent_id ) {
                return $parent_term;
            }
        }
        return false;
    }
    
    function create_term($term, $taxonomy, $parent = 0){
        
        if( term_exists( $term['name'], $taxonomy ) ) return;
    
        $term_args = [
            'description' => $term['description'],
            'slug' => $term['slug'],
        ];
    
        if( $parent ) $term_args['parent'] = $parent;
       
        $term_id = wp_insert_term( $term['name'], $taxonomy, $term_args);
    }

    function get_parent_term_id( $term, $taxonomy_data ){
        $parent_id = 0;
        if( $term['parent'] ) {
            $parent = $this->get_parent_term_data($term['parent'], $taxonomy_data['parent_terms']);
            $parent_query = term_exists($parent['name'], $taxonomy_data['taxonomy']);
            if( $parent_query ) {
                $parent_id = $parent_query['term_id'];
            }
        }
        return $parent_id;
    }

    function upload_image_from_url($image_url, $post_id = '', $description = ''){
        
        $path_info = pathinfo($image_url);
        $file_name = $path_info['filename'];
        $attachment_id = $this->get_attachment_id_by_filename($file_name);
    
        if( !$attachment_id ) {
            // only upload if it doesn't exist in the media yet
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_id = media_sideload_image( $image_url, $post_id, $description, 'id' );
        }
    
        return $attachment_id;
    }
    
    function upload_file( $file_url ) {
        $path_info = pathinfo($file_url);
        $file_name = $path_info['filename'];
        $attachment_id = $this->get_attachment_id_by_filename($file_name);
    
        if( !$attachment_id ) {
            // does not exist yet, upload
            $attachment_id = $this->upload_file_from_url($file_url);
        }
    
        return $attachment_id;
    }
    
    function upload_file_from_url( $file_url ) {
    
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    
        // download to temp dir
        $temp_file = download_url( $file_url );
    
        if( is_wp_error( $temp_file ) ) return false; 
    
        // move the temp file into the uploads directory
        $file = [
            'name'     => basename( $file_url ),
            'type'     => mime_content_type( $temp_file ),
            'tmp_name' => $temp_file,
            'size'     => filesize( $temp_file ),
        ];
        $sideload = wp_handle_sideload($file, ['test_form' => false]);
    
        if( ! empty( $sideload[ 'error' ] ) ) return false; 
    
        // add to media library
        $attachment_id = wp_insert_attachment(
            [
                'guid'           => $sideload[ 'url' ],
                'post_mime_type' => $sideload[ 'type' ],
                'post_title'     => basename( $sideload[ 'file' ] ),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ],
            $sideload[ 'file' ]
        );
    
        if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            return false;
        }
    
        // update medatata, regenerate image sizes
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
    
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] )
        );
    
        return $attachment_id;
    }
    
    function get_attachment_id_by_filename($file_name){
        global $wpdb;
        $result = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid LIKE '%s' LIMIT 1;", '%' . $wpdb->esc_like($file_name) . '%' ));
        if( $result ) {
            return $result[0];
        }
        return false;
    }

}