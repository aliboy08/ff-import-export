<?php
pre_debug('assign terms');

$file_url = 'http://localhost/plugins/wp-content/uploads/2024/03/export-post-2024-03-06-07-32-28.json';
$items = file_get_contents($file_url);
if( $items ) {
    $items = json_decode($items, true);
}

$post_id = 42;
$taxonomy = 'category';

$item = $items[1];

$taxonomy_data = $item['taxonomies'][0];

temp_create_terms($taxonomy_data);

function temp_create_terms($taxonomy_data){
    
    foreach( $taxonomy_data['terms'] as $term ) {
        // create parent terms
        create_nested_term_parents($term, $taxonomy_data);

        // create current term
        $parent_id = get_parent_term_id($term, $taxonomy_data);
        temp_create_term($term, $taxonomy_data['taxonomy'], $parent_id);
    }
}

function create_nested_term_parents($term, $taxonomy_data){
    if( !isset($term['nested']) || !$term['nested'] ) return;

    $taxonomy = $taxonomy_data['taxonomy'];

    $parent_term_ids = [];
    foreach( $term['nested'] as $sub_level ) {
        check_nested_parent_term_ids($sub_level, $parent_term_ids);
    }
    // create deepset levels first
    $parent_term_ids = array_reverse($parent_term_ids);

    foreach( $parent_term_ids as $parent_id ) {
        $parent = get_parent_term_data($parent_id, $taxonomy_data['parent_terms']);
        $grand_parent_id = get_parent_term_id($parent, $taxonomy_data);
        temp_create_term( $parent, $taxonomy, $grand_parent_id );
    }
    
}

function check_nested_parent_term_ids($term_nested, &$parent_term_ids){
    $parent_term_ids[] = $term_nested['id'];
    if( isset($term_nested['parent']) ) {
        foreach( $term_nested['parent'] as $sub_level ) {
            check_nested_parent_term_ids( $sub_level, $parent_term_ids );
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

function get_parent_term_id( $term, $taxonomy_data ){
    $parent_id = 0;
    if( $term['parent'] ) {
        $parent = get_parent_term_data($term['parent'], $taxonomy_data['parent_terms']);
        $parent_query = term_exists($parent['name'], $taxonomy_data['taxonomy']);
        if( $parent_query ) {
            $parent_id = $parent_query['term_id'];
        }
    }
    return $parent_id;
}

function temp_create_term($term, $taxonomy, $parent = 0){
    
    if( term_exists( $term['name'], $taxonomy ) ) return;

    $term_args = [
        'description' => $term['description'],
        'slug' => $term['slug'],
    ];

    if( $parent ) $term_args['parent'] = $parent;

    pre_debug([
        'create_term',
        'term_name' => $term['name'],
        'taxonomy' => $taxonomy,
        'term_args' => $term_args,
    ]);
   
    $term_id = wp_insert_term( $term['name'], $taxonomy, $term_args);
}