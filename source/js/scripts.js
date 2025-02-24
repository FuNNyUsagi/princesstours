$(document).ready(function() {

  $('.plans-item-checkin label').on('click', function() {

    $('.plans-item-inner').removeClass('active');
    
    $(this).closest('.plans-item-inner').addClass('active');

  });
  
  $('.select-lang').on('click', function() {

    $(this).find('.select-lang-dropdown-items').toggle('slow');

  });

});