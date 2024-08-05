function FF_Export(){
    var _ = this;
    _.container = document.querySelector('.ff_ie');
    _.form = document.querySelector('#export_form');
    _.type = _.container.querySelector('#export_type');

    _.event_listeners();
    _.datepicker_init();
    _.preview_init();
    _.more_settings_init();
    _.post_meta_fields_init();
}

FF_Export.prototype.export = function(){
    var _ = this;
    
    var form_data = _.get_form_data();
    var is_custom = typeof ff_export_default_types[form_data.export_post_type] === 'undefined' ? 1 : '';

    var ajax_data = {
        action: 'ff_export_data',
        form_data,
        is_custom,
    }

    _.loading(_.form);
    jQuery.post(ajaxurl, ajax_data, function(response) {
        _.download(response.file);
        _.loading_complete(_.form);
    });
}

FF_Export.prototype.preview_init = function(){
    var _ = this;
    
    _.preview_btn = document.querySelector('#export_preview_btn');
    if( !_.preview_btn ) return;

    _.preview_el = document.querySelector('.ff_ie_preview');
    _.preview_btn.addEventListener('click',()=>{
        _.preview();
    });
}

FF_Export.prototype.preview = function(){
    var _ = this;
    if( _.preview_btn.loading_el ) return;
    _.preview_el.classList.add('show');

    var form_data = _.get_form_data();
    var is_custom = typeof ff_export_default_types[form_data.export_post_type] === 'undefined' ? 1 : '';
    
    var ajax_data = {
        action: 'ff_export_preview',
        form_data,
        is_custom,
    }

    _.loading(_.preview_btn);
    jQuery.post(ajaxurl, ajax_data, function(response) {
        console.log('preview', response);
        _.display_preview(response);
        _.loading_complete(_.preview_btn);
    });  
}

FF_Export.prototype.display_preview = function(response){
    var html = response.items.length ? response.preview_html : 'No results...';
    this.preview_el.innerHTML = html;
}

FF_Export.prototype.get_form_data = function(){
    var data = {};

    jQuery(this.form).serializeArray().forEach(item=>{
        if( !item.value ) return;

        if( item.name.indexOf('[]') !== - 1 ) {
            // multiple
            var field_name = item.name.replace('[]', '');
            if( typeof data[field_name] === 'undefined' ) {
                data[field_name] = [];
            }
            data[field_name].push(item.value);
            return;
        }

        data[item.name] = item.value;

    });

    return data;
}

FF_Export.prototype.download = function(file){
    fetch(file.url)
    .then(response => response.blob())
    .then(blob => {
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = file.name;
        link.click();
        setTimeout(()=>{
            jQuery.post(ajaxurl, { action: 'ff_export_clean', nonce: ff_export_nonce, file });
        }, 2000);
  })
  .catch(console.error);
}

FF_Export.prototype.loading = function(el){
    if( el.loading_el ) return;

    // create loading element
    var loading_el = document.createElement('div');
    loading_el.classList.add('spinner', 'show');

    el.loading_el = loading_el;
    el.after(el.loading_el);
}

FF_Export.prototype.loading_complete = function(el){
    if( typeof el.loading_el === 'undefined' || !el.loading_el ) return;
    el.loading_el.remove();
    el.loading_el = null;
}

FF_Export.prototype.event_listeners = function(){
    var _ = this;

    // _.container.querySelectorAll('.onchange_submit').forEach(input=>{
    //     input.addEventListener('change', ()=>{
    //         input.closest('form').submit();
    //     })
    // })

    var submit_btn = document.querySelector('#export_start');
    if( submit_btn ) {
        submit_btn.addEventListener('click', () => {
            _.export();
        });
    }
}

FF_Export.prototype.datepicker_init = function(){
    var dp_settings = {
        // maxDate: 0,
        changeMonth: true,
        changeYear: true,
        dateFormat: 'mm/dd/yy',
    }
    this.container.querySelectorAll('.datepicker').forEach(input=>{
        jQuery(input).datepicker(dp_settings); 
    });
}

FF_Export.prototype.more_settings_init = function(){
    var _ = this;
    _.container.querySelectorAll('.more_settings_con').forEach(function(con){
        var btn = con.querySelector('.toggle_btn');
        var more_settings = con.querySelector('.more_settings');
        if(!btn || !more_settings) return;
        btn.addEventListener('click', ()=>{
            btn.classList.toggle('active');
            more_settings.style.display = btn.classList.contains('active') ? 'block' : 'none';
        });
    });
}

FF_Export.prototype.post_meta_fields_init = function(){
    var _ = this;
    var all_cb = _.container.querySelector('input[name="all_post_metas"]');
    var specific_input = _.container.querySelector('textarea[name="specific_post_metas"]');
    if( !specific_input ) return;
    specific_input.addEventListener('change',()=>{
        if( specific_input.value ) {
            console.log('have value, clear all');
            all_cb.checked = false;
        } 
    });
}

document.addEventListener('DOMContentLoaded', ()=>{
    var ff_export = new FF_Export();
});