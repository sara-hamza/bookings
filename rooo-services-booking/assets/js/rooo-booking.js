jQuery(function($){
    var step = 1;
    $(document).on('click', '.rooo-category', function(){
        var category = $(this).data('id');
        $('.rooo-categories').hide();
        $('.rooo-subservices[data-category="'+category+'"]').show();
    });

    $(document).on('click', '.rooo-subservice', function(){
        var service = $(this).data('id');
        $('#rooo_service_id').val(service);
        $('.rooo-subservices').hide();
        $('.rooo-booking-form').show();
    });

    $('.rooo-booking-form').on('submit', function(e){
        e.preventDefault();
        var data = $(this).serialize();
        $.post(rooo_ajax.ajax_url, data, function(resp){
            $('.rooo-booking-form').html('<p>'+resp.data.message+'</p>');
        });
    });
});
