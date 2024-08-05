function FF_Import(options){
    var _ = this;
    _.options = options;
    
    _.before_init();
    _.init();
    _.general_event_listeners();
    // _.type_select_init();
    _.media_upload_init();
    _.after_init();
    console.log('FF_Import', this);
}

FF_Import.prototype.before_init = function(){}
FF_Import.prototype.after_init = function(){}

FF_Import.prototype.init = function(){
    var _ = this;

    // _.type = '';
    _.type = _.options.type;
    
    _.ajax_html = document.querySelector('#ajax_html');
    
    _.progress_con = document.querySelector('.import_progress');
    _.progress_current = _.progress_con.querySelector('.current');
    _.progress_total = _.progress_con.querySelector('.total');

    _.import_start_btn = document.querySelector('#import_start');
    if( !_.import_start_btn ) return;

    _.import_start_btn.addEventListener('click', () => {
        _.import_start();
    });
}

FF_Import.prototype.general_event_listeners = function(){
    var _ = this;
    document.querySelectorAll('.on_change_set').forEach( el => {
        el.addEventListener('change', () => {
            _[el.dataset.optionKey] = el.value;
        })
    })
}

FF_Import.prototype.media_upload_init = function(){
    var _ = this;
    var el = document.querySelector('.import_file_upload');
    var el_value = el.querySelector('.value');
    var el_label = el.querySelector('.label');
    if( !el ) return;

    _.file = false;

    var wp_media_frame = wp.media.frames.file_frame = wp.media({
        library: {
            type: 'application/json',
        },
        uploader: {
            type: 'application/json',
        },
        multiple: false
    });
    wp_media_frame.on( 'select', () => {
        _.file = wp_media_frame.state().get('selection').first().toJSON();
        el_value.textContent = _.file.filename;
        
        _.loading(el);
        _.prepare(()=>{
            _.loading_complete(el);
        });
    });
    el_label.addEventListener('click', () => {
        wp_media_frame.open();
    });
}

FF_Import.prototype.prepare = function(cb){
    var _ = this;

    _.items_to_process_per_batch = document.querySelector('#import_items_to_process_per_batch').value;
    
    var ajax_data = {
        action: 'ff_import_prepare',
        file: _.file,
    }
    
    jQuery.post(ajaxurl, ajax_data, function(res) {
        _.items = res.items;
        _.prepare_item_data(_.items);
        _.display_preview(res.preview_html);
        _.after_prepare(res);
        if( typeof cb === 'function' ) cb(res);
    });
}

FF_Import.prototype.prepare_item_data = function(items){
    var _ = this;

    _.selected_item_data = {};
    _.item_data = {
        others: [],
    };

    _.item_field_keys = {};
    
    var item = items[0];

    _.prepare_item_data_single( item, 'others', 'featured_image' );

    _.prepare_item_data_object( item, 'post_data' );
    _.prepare_item_data_object( item, 'post_meta' );

    // _.prepare_item_data_array( item, 'image_fields' );
    // _.prepare_item_data_array( item, 'gallery_fields' );
    // _.prepare_item_data_array( item, 'post_object_fields' );
    // _.prepare_item_data_array( item, 'acf_fields', 'all' );

    _.prepare_item_data_array( item, 'taxonomies', 'taxonomy' );
    
    
    items.forEach(item=>{
        _.prepare_item_data_array( item, 'acf_fields', 'all' );
    })

}

FF_Import.prototype.prepare_item_data_single = function(item, data_group, key){
    var _ = this;
    if( typeof item[key] === 'undefined' ) return;
    _.item_data[data_group].push(key);
}

FF_Import.prototype.prepare_item_data_object = function(item, data_group){
    var _ = this;
    if( typeof item[data_group] === 'undefined' ) return;
    _.item_data[data_group] = Object.keys(item[data_group]);
}

FF_Import.prototype.prepare_item_data_array = function(item, data_group, key = "meta_key"){
    var _ = this;

    if( typeof item[data_group] === 'undefined' ) return;
    if( !Array.isArray(item[data_group]) ) return;

    // console.log({item, data_group, key})

    if( typeof _.item_data[data_group] === 'undefined' ) {
        _.item_field_keys[data_group] = [];
        _.item_data[data_group] = [];
    }

    let fields = _.item_data[data_group];
    let fields_keys = _.item_field_keys[data_group];

    // var fields = [];
    item[data_group].forEach(row => {
        var field_item = ( key == 'all' ) ? row : row[key];
        if( fields_keys.indexOf(field_item.name) === -1 ) {
            fields_keys.push(field_item.name);
            fields.push(field_item);
        }
    });

    // if( !fields.length ) return;
    // _.item_data[data_group] = fields;
}

FF_Import.prototype.after_prepare = function(res){
    var _ = this;
    _.map_fields_init();
    _.enable_start();
}

FF_Import.prototype.import_start = function(){
    var _ = this;
    if( !this.import_start_btn.classList.contains('enable') ) return;

    _.map_fields = _.get_map_fields();

    _.progress_start();
    _.prepare_batches();
    _.process_batches();
}

FF_Import.prototype.map_fields_init = function(){
    var _ = this;
    _.import_start_btn.classList.remove('enable');
    
    var html = '<div class="map_fields">';

    html += _.select_fields_group_html('post_data', { map_field: false });
    html += _.select_fields_group_html('others', { map_field: false } );
    // html += _.select_fields_group_html('image_fields');
    // html += _.select_fields_group_html('gallery_fields');
    // html += _.select_fields_group_html('post_object_fields', { map_post_type: true });

    html += _.select_fields_group_html('acf_fields');

    html += _.select_fields_group_html('post_meta');
    html += _.select_fields_group_html('taxonomies', { map_field: false, map_taxonomy: true });

    html += '</div>';

    ajax_html.innerHTML = html;

    _.toggle_buttons_init();
}

FF_Import.prototype.select_fields_group_html = function( group, args = {} ){
    var _ = this;

    if( typeof _.item_data[group] === 'undefined' ) return '';
    if( !_.item_data[group].length ) return '';
    
    if ( typeof args.map_post_type === 'undefined' ) args.map_post_type = false;
    if ( typeof args.map_taxonomy === 'undefined' ) args.map_taxonomy = false;
    if ( typeof args.map_field === 'undefined' ) args.map_field = true;

    // console.log('select_fields_group_html > start', group, args)

    var post_data_defaults = [
        'post_title',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_status',
    ];
    
    var items_html = '';
    _.item_data[group].forEach(item => {

        var field_key = item;

        var additional_options = '';
        
        var enable_map_post_type = false;
        if( group == 'acf_fields' ) {
            field_key = item.name;
            if( typeof item.post_type !== 'undefined' ) {
                enable_map_post_type = true;
            }
        }
        
        if( args.map_field ) {
            additional_options += _.select_field_html_map_meta_key(field_key);
        }

        if( args.map_post_type || enable_map_post_type ) {
            additional_options += _.select_field_html_map_post_type(field_key);
        }

        if( args.map_taxonomy ) {
            additional_options += _.select_field_html_map_taxonomy(field_key);
        }

        var selected = '';
        if( group == 'post_data' ) {
            if( post_data_defaults.indexOf(field_key) !== -1 ) {
                selected = ' checked';
            }
        }

        items_html += `
        <div class="item flex-vertical gap-10">
            <label><input class="field" type="checkbox" value="${field_key}"${selected}>${field_key}</label>
            ${additional_options}
        </div>`;
    })

    var html = `
    <div class="fields_group ${group} mb-40 mt-40" data-group="${group}">
        <h3>Select ${group}</h3>
        <div class="flex-wrap gap-10 mb-20">
            <span class="button btn_style_1 toggle_all_on">Select all</span>
            <span class="button btn_style_1 toggle_all_off">Unselect all</span>
        </div>
        <div class="checkboxes grid cols-2 gap-10">${items_html}</div>
    </div>
    `;

    return html;
}

FF_Import.prototype.select_field_html_map_meta_key = function(item){

    if( typeof item === 'undefined' ) return '';

    var item_value = item;
    if( typeof item.taxonomy !== 'undefined' ) {
        item_value = item.taxonomy;
    }

    var html = `
    <div class="flex gap-10 v-center">
        <span class="shrink-0">Map meta key</span>
        <input type="text" class="map_field w_full" value="${item_value}"/>
    </div>
    `;
    return html;
}

FF_Import.prototype.select_field_html_map_post_type = function(item){

    var _ = this;

    var options_html = '';
    options_html += '<option value="">Default</option>';
    options_html += '<option value="title_only">Title only</option>';
    Object.keys( _.options.available_post_types ).forEach(post_type => {
        options_html += '<option value="'+ post_type +'">'+ post_type +'</option>';
    })

    var html = `
    <div class="flex gap-10 v-center">
        <span class="shrink-0">Map post type</span>
        <select class="map_post_type w_full">${options_html}</select>
    </div>
    `;

    return html;
}

FF_Import.prototype.select_field_html_map_taxonomy = function(item){

    var _ = this;

    var options_html = '<option value="">Select</option>';
    _.options.available_taxonomies.forEach(taxonomy => {
        var selected = item == taxonomy ? ' selected' : '';
        options_html += '<option value="'+ taxonomy +'"'+ selected +'>'+ taxonomy +'</option>';
    })

    var html = `
    <div class="flex gap-10 v-center">
        <span class="shrink-0">Map taxonomy</span>
        <select class="map_taxonomy w_full">${options_html}</select>
    </div>
    `;

    return html;
}

FF_Import.prototype.toggle_buttons_init = function(){

    document.querySelectorAll('.toggle_all_on').forEach(btn => {
        if( typeof btn.toggle_all_on_init !== 'undefined' ) return;
        btn.toggle_all_on_init = true;

        var checkboxes = btn.closest('.fields_group').querySelectorAll('input[type="checkbox"].field');
        if( !checkboxes ) return;
        btn.addEventListener('click', () => {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            })  
        });
    })

    document.querySelectorAll('.toggle_all_off').forEach(btn => {
        if( typeof btn.toggle_all_off_init !== 'undefined' ) return;
        btn.toggle_all_off_init = true;

        var checkboxes = btn.closest('.fields_group').querySelectorAll('input[type="checkbox"].field');
        if( !checkboxes ) return;
        btn.addEventListener('click', () => {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            })  
        });
    })
}

FF_Import.prototype.get_map_fields = function(){
    var _ = this;
    
    var fields = {};
    document.querySelectorAll('.map_fields .fields_group').forEach(group => {
        var group_data = [];
        
        group.querySelectorAll('.checkboxes .item').forEach(item => {

            var checkbox = item.querySelector('input[type="checkbox"].field');
            if( !checkbox.checked ) return;

            var key = checkbox.value;
            var item_data = {
                key,
            }

            var map_field = item.querySelector('.map_field');
            if( map_field ) {
                item_data.map_to = map_field.value;
            }
            
            var map_post_type_field = item.querySelector('.map_post_type');
            if( map_post_type_field ) {
                item_data.map_post_type = map_post_type_field.value;
            }

            var map_taxonomy = item.querySelector('.map_taxonomy');
            if( map_taxonomy ) {
                item_data.map_taxonomy = map_taxonomy.value;
            }

            group_data.push(item_data);
        })

        fields[group.dataset.group] = group_data;
    })

    return fields;
}

FF_Import.prototype.progress_start = function(){
    var _ = this;
    _.loading = true;
    _.progress_con.classList.remove('complete');
    _.progress_con.classList.add('start');
}

FF_Import.prototype.progress_complete = function(){
    var _ = this;
    _.loading = false;
    _.progress_con.classList.add('complete');
}

FF_Import.prototype.enable_start = function(){
    var _ = this;
    _.import_start_btn.classList.add('enable');
}

FF_Import.prototype.loading = function(el){
    if( el.loading_el ) return;

    // create loading element
    var loading_el = document.createElement('div');
    loading_el.classList.add('spinner', 'show');

    el.loading_el = loading_el;
    el.after(el.loading_el);
}

FF_Import.prototype.loading_complete = function(el){
    if( typeof el.loading_el === 'undefined' || !el.loading_el ) return;
    el.loading_el.remove();
    el.loading_el = null;
}

FF_Import.prototype.prepare_batches = function(){
    var _ = this;

    _.current = 0;
    _.processed_count = 0;

    _.progress_total.textContent = _.items.length;
    _.progress_current.textContent = '0';
    
    _.batches = [];
    
    var batch = [];
    var i = 0;
    var j = 0;
    for( var i = 0; i < _.items.length; i++ ) {
        j++;
        var item = _.items[i];
        batch.push(item);

        if( j == _.items_to_process_per_batch || i == _.items.length - 1 ) {
            _.batches.push(batch);
            batch = [];
            j = 0;
        }
    }
}

FF_Import.prototype.process_batches = function(){
    var _ = this;
    var items = _.batches[_.current];

    console.log('process_batches', _.current, items)

    var ajax_data = _.process_batches_ajax_data();
    ajax_data.items = items;

    jQuery.post(ajaxurl, ajax_data, function(res) {
        console.log('process_batch result', res );
        _.current++;

        _.processed_count += items.length;
        _.progress_current.textContent = _.processed_count;

        if( _.current < _.batches.length ) {
            // process next batch
            _.process_batches();
        }
        else {
            // complete
            _.progress_complete();
        }
    });

}

FF_Import.prototype.process_batches_ajax_data = function(){
    var _ = this;
    var ajax_data = {
        action: 'ff_import_process_items',
        type: _.type,
        map_fields: _.map_fields,
    };
    return ajax_data;
}

FF_Import.prototype.display_preview = function(preview_html){
    var _ = this;
    _.preview_el = document.querySelector('.ff_ie_preview');
    if( !_.preview_el ) return;
    _.preview_el.classList.add('show');
    _.preview_el.innerHTML = preview_html;
}