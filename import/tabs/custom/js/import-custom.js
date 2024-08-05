FF_Import.prototype.after_prepare = function(){
    this.enable_start();
}

FF_Import.prototype.process_batches_ajax_data = function(){
    var _ = this
    var ajax_data = {
        action: 'ff_import_process_items',
        custom_type: _.custom_type,
    };
    return ajax_data;
}