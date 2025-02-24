$(document).ready(function() {

  $('.plans-item-checkin label').on('click', function() {

    $('.plans-item-inner').removeClass('active');
    
    $(this).closest('.plans-item-inner').addClass('active');

  });
  
  $('.select-lang').on('click', function() {

    $(this).find('.select-lang-dropdown-items').toggle('slow');

  });

  $('select#members-order').on('change', function() {

    $('#members-search-form').submit();

  });

  $(document).on('submit', 'form#loginForm', function(e) {

    e.preventDefault();

    let form = $(this);
    let email = $(this).find('input[name="email"]');
    let password = $(this).find('input[name="password"]');
    
    let csrf_param = $(document).find('meta[name="csrf-param"]').attr('content');
    let csrf_token = $(document).find('meta[name="csrf-token"]').attr('content');

    form.find('.modal-form-message').hide();

    if (email.val() == '' || password.val() == '') {

      if (email.val() == '') {

        email.addClass('input-error');

      }
      else {

        email.removeClass('input-error');

      }

      if (password.val() == '') {

        password.addClass('input-error');

      }
      else {

        password.removeClass('input-error');

      }

      return;

    }

    let form_data = new FormData();

    form_data.append('email', email.val());
    form_data.append('password', password.val());
    form_data.append(csrf_param, csrf_token);

    $.ajax({
        type: 'POST',
        processData: false,
        contentType: false,
        url: '/web/user/auth',
        data: form_data,
        dataType: 'JSON',
        success: function(response) {

            form.find('.modal-form-message').show();
            
            if (response.status == 0) {

                form.find('.modal-form-message').css('color', 'red');
                form.find('.modal-form-message').text(response.message);

            }
            else if (response.status == 1) {

                form.find('.modal-form-message').css('color', 'green');
                form.find('.modal-form-message').text(response.message);

                email.val('').removeClass('input-error');
                password.val('').removeClass('input-error');

                location.href = '/web/members';

            }

        }
    });    

  });

  $(document).on('submit', 'form#registerForm', function(e) {

    e.preventDefault();

    let form = $(this);
    let email = $(this).find('input[name="email"]');
    let password = $(this).find('input[name="password"]');
    let retry_password = $(this).find('input[name="retry_password"]');
    
    let csrf_param = $(document).find('meta[name="csrf-param"]').attr('content');
    let csrf_token = $(document).find('meta[name="csrf-token"]').attr('content');

    form.find('.modal-form-message').hide().text('');

    if (email.val() == '' || password.val() == '' || retry_password.val() == '') {

      if (email.val() == '') {

        email.addClass('input-error');

      }
      else {

        email.removeClass('input-error');

      }

      if (password.val() == '' || retry_password.val() == '') {

        password.addClass('input-error');
        retry_password.addClass('input-error');

      }
      else {

        password.removeClass('input-error');
        retry_password.removeClass('input-error');

      }

      return;

    }

    if (password.val() !== retry_password.val()) {

        password.addClass('input-error');
        retry_password.addClass('input-error');

        form.find('.modal-form-message').css('color', 'red');
        form.find('.modal-form-message').show().text('Password has no matching retry password!');

        return;

    }

    let form_data = new FormData();

    form_data.append('email', email.val());
    form_data.append('password', password.val());
    form_data.append('retry_password', retry_password.val());
    form_data.append(csrf_param, csrf_token);

    $.ajax({
        type: 'POST',
        processData: false,
        contentType: false,
        url: '/web/user/register',
        data: form_data,
        dataType: 'JSON',
        success: function(response) {

            form.find('.modal-form-message').show();
            
            if (response.status == 0) {

                form.find('.modal-form-message').css('color', 'red');
                form.find('.modal-form-message').text(response.message);

                email.removeClass('input-error');
                password.removeClass('input-error');
                retry_password.removeClass('input-error');

            }
            else if (response.status == 1) {

                form.find('.modal-form-message').css('color', 'green');
                form.find('.modal-form-message').text(response.message);

                email.val('').removeClass('input-error');
                password.val('').removeClass('input-error');
                retry_password.val('').removeClass('input-error');

                location.href = '/web';

            }

        }
    });    

  });

  $(document).on('click', '.profile-favorite-btn', function(e) {

    e.preventDefault();

    let btn = $(this);
    let page = btn.attr('data-page');
    let opt = btn.attr('data-opt');
    let hash = btn.attr('data-hash');
    
    let csrf_param = $(document).find('meta[name="csrf-param"]').attr('content');
    let csrf_token = $(document).find('meta[name="csrf-token"]').attr('content');

    let form_data = new FormData();

    form_data.append('page', page);
    form_data.append('opt', opt);
    form_data.append('hash', hash);
    form_data.append(csrf_param, csrf_token);

    $.ajax({
        type: 'POST',
        processData: false,
        contentType: false,
        url: '/web/favorites/'+opt,
        data: form_data,
        dataType: 'JSON',
        success: function(response) {
            
            if (response.status == 0) {

                alert(response.message);

            }
            else if (response.status == 1) {

                if (opt == 'add' && page == 'members') {

                  btn.addClass('active').attr('data-opt', 'remove').attr('data-hash', response.hash).attr('title', 'Remove member from favorite');

                }
                else if (opt == 'remove' && page == 'members') {

                  btn.removeClass('active').attr('data-opt', 'add').attr('data-hash', response.hash).attr('title', 'Add member to favorite');

                }
                else if (opt == 'remove' && page == 'favorites') {

                  btn.closest('.profile-item').remove();

                }

            }

        }
    });    

  });

  $(document).on('click', '.profile-item-inner', function(e) {
      e.preventDefault();
      let user_to = $(this).data('user');
      $.ajax({
          type: 'POST',
          dataType: 'JSON',
          url: '/web/messages/get-dialog',
          data: {user_to: user_to},
          success: function(data) {
              if (data.status == 'suc'){
                  window.location.href = '/web/messages/message?_d='+data.d+'&_u='+data.uf+'&_ut='+user_to;
              } else {
                  alert(data.msg);
              }
          }
      });
  });

});