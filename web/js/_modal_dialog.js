function popup_info_clear() {
    $('.popup_info').removeClass('popup_error').removeClass('popup_success').html('').hide();
}

function add_msg_error(msg) {
    $('.popup_info').addClass('popup_error').html(msg).show('fast');
}

function add_msg_success(msg) {
    $('.popup_info').addClass('popup_success').html(msg).show('fast');
}

function popup_settitle(text) {
    $('.popup_label').html(text);
}

function popup_setcontent(text) {
    $('.popup_main-block').html(text);
}

function popup_clear() {
    $('.popup_label').html('');
    $('.popup_main-block').html('');
    popup_info_clear();
}
$(document).ready(function($) {
    $(document).on('click', '.popup-close', function() {
        $(this).parents('.popup-fade').fadeOut('', function() {popup_clear();});
        return false;
    });

    $(document).keydown(function(e) {
        if (e.keyCode === 27) {
            e.stopPropagation();
            $('.popup-fade').fadeOut('', function() {popup_clear();});
        }
    });

    $(document).on('click', '.popup-fade', function(e) {
        if ($(e.target).closest('.popup').length == 0) {
            $(this).fadeOut('', function() {popup_clear();});
        }
    });
});