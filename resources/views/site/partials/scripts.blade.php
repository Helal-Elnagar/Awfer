<script> 
    // Configure toastr options
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": false,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut",
        "toastClass": "toast",
        "iconClasses": {
            "error": "toast-error",
            "info": "toast-info",
            "success": "toast-success",
            "warning": "toast-warning"
        }
    };
    
    $(document).on('submit','.myform',function (e) {
        e.preventDefault()
        var form = $(this);
        var url = form.attr('action')
        $.ajax({
            url: url,
            method: 'post',
            data: new FormData(form[0]),
            dataType:'json',
            processData: false,
            contentType: false,
            success: function(response){
                $('.error_meassages').remove();
                toastr.success(response.message)
                form[0].reset()
            },
            error: function (xhr) {
                $('.error_meassages').remove();
                if(xhr.responseJSON && xhr.responseJSON.errors) {
                    $.each(xhr.responseJSON.errors, function(key,value) {
                        form.find('input[name=' + key + ']').after('<small class="form-text error_meassages text-danger">' + value + '</small>');
                        form.find('textarea[name=' + key + ']').after('<small class="form-text error_meassages text-danger">' + value + '</small>');
                        form.find('select[name=' + key + ']').after('<small class="form-text error_meassages text-danger">' + value + '</small>');
                    });
                }
            },
        });
    });

    // Navigation link handler
    $(document).on('click', '.nav-link', function(e) {
        e.preventDefault();
        var targetSection = $(this).data('section');
        var targetElement = $('#' + targetSection);
        
        // Check if the target section exists on the current page
        if (targetElement.length > 0) {
            // Section exists, scroll to it
            $('html, body').animate({
                scrollTop: targetElement.offset().top - 80
            }, 800);
        } else {
            // Section doesn't exist, redirect to home page with anchor
            window.location.href = '{{ route("intro") }}#' + targetSection;
        }
    });
</script>