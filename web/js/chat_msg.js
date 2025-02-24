$(document).ready(function () {
    /*Инициализируем эмодзи*/
    new MeteorEmoji();

    /*Чатик*/
    function openWebSocket() {
        //socket = new WebSocket("wss://wss.intactshow.com");
        socket = new WebSocket("ws://localhost:4369");

        socket.onopen = function() {
            console.log("Соединение установлено.");
            var authObj = getServerAuth();
            if(authObj != false || authObj != 'undefined'){
                socket.send(JSON.stringify(authObj));
            }
        };

        socket.onclose = function(e) {
            if (e.wasClean) {
                console.log(`Соединение закрыто чисто, код=${e.code} причина=${e.reason}`);
            } else {
                console.log(`Соединение прервано, код=${e.code} причина=${e.reason}`); // например, "убит" процесс сервера
                setTimeout(function(){
                    openWebSocket();
                }, 5000);
            }
        };

        socket.onmessage = function(event) {
            var json = JSON.parse(event.data);

            console.log(json);

            switch(json.section){
                case "authorization":
                    if(json.status){
                        userAuth = true;
                        //получение списка сообщений
                        var o = getMessageList();
                        if(o != false || o != 'undefined'){
                            socket.send(JSON.stringify(o));
                        }
                        //проверка новых сообщений
                        var o = {
                            section : 'CheckNewMessage',
                            data : {
                                userID : $('#user_id').val()
                            }
                        };
                        socket.send(JSON.stringify(o));
                    } else {
                        console.log('You are not authorized to access the chat');
                    }
                    break;
                case "userOnline":
                    if ($('#user_to').val() == json.userID) {
                        $('.messager_status').removeClass('offline').addClass('online').html('online');
                    }
                    break;

                case "userOffline":
                    if ($('#user_to').val() == json.userID) {
                        $('.messager_status').removeClass('online').addClass('offline').html('offline');
                    }
                    break;

                case "getMessageList": //получение сообщений
                    var dialog_id = $('#dialog_id').val();

                    if (json.dialogID == dialog_id) {
                        /*Определяем онлайн ли юзер*/
                        if (json.online == true) {
                            $('.messager_status').removeClass('offline').addClass('online').html('online');
                        } else {
                            $('.messager_status').removeClass('online').addClass('offline').html('offline');
                        }

                        var html = '<div class="load_messages" data-ofst="10"><span>Upload... Please, wait</span><div class="loader_spiner"></div></div>';

                        /*Плашка если нет сообщений*/
                        if (json.data.length == 0) {
                            html += '<div class="msg_list-empty">The message list is empty</div>';
                        } else {
                            /*Если есть сообщения - собнса, выводим их (с учётом группировки)*/
                            $.each(json.data, function(dt_key, items){
                                html += addMessageDt(dt_key);
                                $.each(items, function(key, item){
                                    html += addMessageItem(item);
                                });
                            });
                        }

                        $('.messager-message-balloons').html(html);
                        scrollChat();

                        socket.send(JSON.stringify(clearNewMessage($('#dialog_id').val())));
                    }
                    break;

                case "addMessage": //Если пришло новое сообщение
                    var dialog_id = $('#dialog_id').val();

                    if (json.dialogID == dialog_id) { //если есть активный диалог и на него пришли сообщения

                        var html = '';

                        $.each(json.data, function(dt_key, items){
                            var dtst = $('.messager-message-balloons').find('div.messager-message-date[data-val="'+dt_key+'"]');//проверяем, есть ли уже такая дата-разделитель
                            if (dtst.length == 0) {
                                html += addMessageDt(dt_key);
                            }

                            $.each(items, function(key, item){
                                html += addMessageItem(item);
                            });
                        });

                        $('.messager-message-balloons').append(html);
                        scrollChat();

                        if (json.clearMsg == true) {
                            socket.send(JSON.stringify(clearNewMessage(dialog_id)));
                        }
                    }
                    break;

                case "getTyping":
                    var dialog_id = $('#dialog_id').val();

                    if (json.dialogID == dialog_id) {
                        if (typeof typingTimerMaj !== 'undefined') {
                            clearTimeout(typingTimerMaj);
                        }

                        $('.messager-message-typing').show();
                        $('.messager_status').hide();

                        typingTimerMaj = setTimeout(function(){
                            $('.messager-message-typing').hide();
                            $('.messager_status').show();
                        }, 2200);
                    }
                    break;

                case 'clearNewCheck':
                    $('i.fa-check').removeClass('fa-check').addClass('fa-double-check');
                    break;

                case "deleteDialog":
                    var dialog_id = $('#dialog_id').val();

                    if (json.dialog == dialog_id) { // если есть активный диалог и его удалили
                        popup_settitle('Warning');
                        add_msg_error('This chat has been deleted. You will now go to the main menu.');
                        $('.popup-fade').fadeIn();

                        delTimer = setTimeout(function(){
                            window.location.href = '/web/messages';
                        }, 2000);
                    }
                    break;

                case "ChangeUserByOperator":
                    if ($('#user_id').val() == json.userID) {
                        window.location.href = '/web/messages';
                    }
                    break;

                case "CheckNewMessage":
                    if (json.cnt > 0) {
                        $('.new_msg_cnt').html(json.cnt).removeClass('hidden');
                    } else {
                        $('.new_msg_cnt').addClass('hidden').html('');
                    }
                    break;

                case "queueSucces":
                    console.log('Ваши данные поставлены в очередь');
                    break;

                case "UnpaidChat":
                    window.location.reload();
                    break;

                case "ErrrorStatus":
                    console.log('Unexpected error. Please reload the page.');
                    break;
            }
        };

        socket.onerror = function(error) {
            console.log("Ошибка при соединении " + (error.message ? error.message : 'неизвестная ошибка'));
        };
    }

    var userAuth = false;
    openWebSocket();

    function getServerAuth(){
        if($('#user_id').length == 1 && $('#user_id').val() > 0){
            var obj = {
                section : 'authorization',
                data : {
                    userID : $('#user_id').val(),
                    identif : $('#identif').val()
                }
            };
            return obj;
        }else{
            return false;
        }
    }
    function deleteDialog(dialogID, userTo){
        var obj = {
            section : 'deleteDialog',
            data : {
                dialogID : dialogID,
                userTo : userTo
            }
        }
        return obj;
    }

    function getMessageList(offset = 0, limit = 10){
        var obj = {
            section : 'getMessageList',
            data : {
                dialogID : $('#dialog_id').val(),
                userID : $('#user_id').val(),
                offset : offset,
                limit : limit
            }
        }
        return obj;
    }

    function clearNewMessage(dialogID){
        var obj = {
            section : 'clearNewMessage',
            data : {
                dialogID: dialogID,
                userID: $('#user_id').val()
            }
        }
        return obj;
    }

    function operatorQueue(userId){
        var obj = {
            section : 'operatorQueue',
            data : {
                userID: userId
            }
        }
        return obj;
    }

    function addMessageDt(dt_key) {
        var html = '<div class="messager-message-date" data-val="'+dt_key+'"><span>'+dt_key+'</span></div>';
        return html;
    }

    function addMessageItem(item){
        var html = '';
        var userid = $('#user_id').val();

        var cls = 'user';
        var check_class = '';
        if  (userid == item.senderID) {
            cls = 'you';
            check_class = ' fa'+(item.view_message == '1' ? '-double' : '')+'-check';
        }

        var msg = '';
        if (item.status == 2) {
            msg = '<a href="'+item.message+'" class="m_img_wap"><img src="'+item.message+'"></a>';
        } else if (item.status == 1) {
            msg = '<img src="'+item.message+'">';
        } else {
            msg = item.message;
        }

        html = '<div class="messager-message-balloon-'+cls+'">'+
            msg +
            '<div class="messager-message-time"><i data-val="'+item.id+'" class="fa'+check_class+'" aria-hidden="true"></i>'+item.timeCreate+'</div>' +
            '</div>';

        return html;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////////////////////


    function scrollChat() {
        var scrollTo = $('.messager-message-balloons').find('.messager-message-time').last();

        if (scrollTo.length != 0) {
            var offsetTo = scrollTo.offset();

            var container = $('.messager-message-balloons');
            var offsetCon = container.offset();

            container.animate({
                scrollTop: offsetTo.top - offsetCon.top + container.scrollTop() + 100
            });
        }
    }

    //Отправка сообщения
    $(document).on('click', '.messager-submit', function(){
        var img = $('#img_item');
        var msg = $('#msg_txt');

        if (msg.val().trim() == '' && img.length == 0) {
            msg.val('').css('height', '30px');
            return false;
        }

        var dialog_id = $('#dialog_id').val();
        var userID = $('#user_id').val();
        var userTo = $('#user_to').val();
        var status = 0;
        var message =  msg.val().trim();

        if (img.length != 0) {
            status = 2;
            message = img.find('img').attr('src');
        }

        if (status == 2) {
            var stik = $('#add_telegram_stikers');
            var sbt = $('.messager-submit');

            img.addClass('upload_img');
            stik.hide();
            sbt.hide();

            $.ajax({
                type: 'POST',
                dataType: 'JSON',
                url: '/web/messages/upload-img',
                data: {dialog_id: dialog_id, user_id: userID, message: message},
                success: function(data) {
                    if (data.status == 'suc'){
                        message = data.msg;

                        var obj = {
                            section: 'addMessage',
                            data: {
                                dialogID: dialog_id,
                                message: message,
                                userTo: userTo,
                                userFrom: userID,
                                status: status
                            }
                        };

                        socket.send(JSON.stringify(obj));
                        socket.send(JSON.stringify(operatorQueue(userTo)));
                        $('#msg_txt').val('').css('height', '30px');
                        $('.img_close').trigger('click');

                        stik.show();
                        sbt.show();
                    } else {
                        popup_settitle('Error');
                        add_msg_error(data.msg);

                        $('.popup-fade').fadeIn();

                        img.removeClass('upload_img');
                        stik.show();
                        sbt.show();
                    }
                }
            });
        } else {
            var obj = {
                section: 'addMessage',
                data: {
                    dialogID: dialog_id,
                    message: message,
                    userTo: userTo,
                    userFrom: userID,
                    status: status
                }
            };

            socket.send(JSON.stringify(obj));
            socket.send(JSON.stringify(operatorQueue(userTo)));
            $('#msg_txt').val('').css('height', '30px');
            $('.img_close').trigger('click');
        }
    });


    //подгрузка сообщения
    function uploadScroll() {
        var dialog_id = $('#dialog_id').val();
        var user_id = $('#user_id').val();
        var ofst = $('.load_messages').data('ofst');

        $('.load_messages').show();

        $.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: '/web/messages/upload-scroll',
            data: {dialog_id: dialog_id, user_id: user_id, ofst: ofst},
            success: function(data) {
                if (data.status == 'suc'){
                    $('.load_messages').hide();

                    if (data.ofst == 0) {
                        $('.messager-message-balloons').off('scroll');
                    } else {
                        $('.load_messages').data('ofst', data.ofst);
                    }

                    html = '';
                    $.each(data.msg, function(dt_key, items){
                        var dtst = $('.messager-message-balloons').find('div.messager-message-date[data-val="'+dt_key+'"]');//проверяем, есть ли уже такая дата-разделитель
                        if (dtst.length != 0) {
                            dtst.remove();//если есть, то удаляем
                        }
                        html += addMessageDt(dt_key);
                        $.each(items, function(key, item){
                            html += addMessageItem(item);
                        });
                    });

                    $('.load_messages').after(html);
                } else {
                    popup_settitle('Error');
                    add_msg_error(data.msg);

                    $('.popup-fade').fadeIn();
                }
            }
        });
    }

    //Показ блока со стикеросами
    $('#add_telegram_stikers').on('click', function() {
        $('.telegram_stikers_block').addClass('active');
    });

    //Закрытие блока со стикерами
    $('#close_stikers_block').on('click', function() {
        $('.telegram_stikers_block').removeClass('active');
    });

    //Инициализация блока со стикерами
    $('.telegram_stikers_block_nav').slick({
        slidesToShow: 6,
        slidesToScroll: 1,
        autoplay: false,
        dots: false,
        infinite: true,
        speed: 500,
    });

    //Подгрузка стикеров при выборе категории
    $('.telegram_stikers_block_nav_item').on('click', function() {
        var dir = $(this).attr('id');
        $('.telegram_stikers_block_list').html('<div class="loader_spiner"></div>');
        $.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: '/web/messages/get-stickers',
            data: {dir: dir},
            success: function(data) {
                if (data.status == 'suc'){
                    $('.telegram_stikers_block_list').html("");
                    $.each(data.stickers, function(i, el) {
                        $('.telegram_stikers_block_list').append(el);
                    });
                } else {
                    $('.telegram_stikers_block_list').html("");
                    $('.telegram_stikers_block_list').append('<p class="select_dir_stikers">' + data.msg + '</p>');
                }
            }
        });
    });

    // отправка стикера
    $(document).on('click', '.telegram_stikers_block_list span', function() {
        var dialog_id = $('#dialog_id').val();
        var src = $(this).find('img').attr('src');
        var userTo = $('#user_to').val();
        var userID = $('#user_id').val();

        var obj = {
            section: "addMessage",
            data:{
                dialogID: dialog_id,
                message: src,
                userTo: userTo,
                userFrom: userID,
                status: 1
            }
        };

        $('.telegram_stikers_block').removeClass('active');
        socket.send(JSON.stringify(obj));
        socket.send(JSON.stringify(operatorQueue(userTo)));
    });

    $('.messager-message-balloons').on('scroll', function() {
        var scrollPosition = $(this).scrollTop();
        if (scrollPosition == 0) {
            uploadScroll();
        }
    });

    $(document).on('click', '.back_flip', function() {
        window.location.href = '/web/messages';
    });

    $(document).on('click', '.dropdown', function() {
        $(this).children('.dropdown-content').show();
    });

    //Удаление чата
    $(document).on('click', '.delete_dialog', function(e) {
        popup_settitle('Do you really want to delete the dialog?');
        popup_setcontent('<button id="confirm_delete_dialog">Confirm</button>');

        $('.popup-fade').fadeIn();
        return false;
    });

    $(document).on('click', '#confirm_delete_dialog', function() {
        popup_info_clear();
        var dialog_id = $('#dialog_id').val();
        var user_to = $('#user_to').val();

        popup_setcontent('<span>Please, wait </span><span class="loader_spiner"></span>');

        $.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: '/web/messages/delete-dialog',
            data: {dialog_id: dialog_id},
            success: function(data) {
                if (data.status == 'suc'){
                    var o = deleteDialog(dialog_id, user_to);
                    socket.send(JSON.stringify(o));

                    window.location.href = '/web/messages';
                } else {
                    popup_setcontent('<button id="confirm_delete_dialog">Confirm</button>');
                    add_msg_error(data.msg);
                }
            }
        });

        return false;
    });

    $('html').click(function() {
        $('.dropdown-content').hide();
    });

    $(document).keydown(function(e) {
        if (e.keyCode === 27) {
            if ($('.popup-fade').is(':hidden')) {
                e.stopPropagation();
                $('#msg_txt').val("");
            }
        }

        if (e.keyCode == 13 && !e.shiftKey) {
            e.preventDefault();

            if ($('#msg_txt').val().trim() == '') {
                return false;
            }

            $('.messager-submit').trigger('click');

            return false;
        }
    });

    $(document).on("input", "#msg_txt", function () {
        $(this).outerHeight(this.scrollHeight);
    });

    function getUnixTime(){
        return Math.round(new Date().getTime()/1000);
    }

    var lastSendTime = getUnixTime();

    $(document).on('keyup', '#msg_txt', function() {
        if((getUnixTime() - lastSendTime) >= 2){
            var dialog_id = $('#dialog_id').val();
            var user_to = $('#user_to').val();

            var obj = {
                section: "getTyping",
                data:{
                    dialogID: dialog_id,
                    userTo: user_to
                }
            };

            socket.send(JSON.stringify(obj));

            lastSendTime = getUnixTime();
        }
    });

    $('.popup-gallery-images').magnificPopup({
        delegate: '.m_img_wap',
        type: 'image',
        tLoading: 'Loading image #%curr%...',
        mainClass: 'mfp-img-mobile',
        gallery: {
            enabled: true,
            navigateByImgClick: true,
            preload: [0,1] // Will preload 0 - before current, and 1 after the current image
        },
        image: {
            tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
        }
    });

    $(document).on('paste', '#msg_txt', function(e) {
        var item = e.originalEvent.clipboardData.items[0];

        if (item.type.indexOf("image") === 0) {
            $('#img_item').hide('fast', function() {$('#img_item').remove()});
            $('#msg_txt').attr('disabled', true).addClass('disabled');
            $('.messager-textarea').find('a').hide('fast');

            // Получаем объект изображения
            var blob = item.getAsFile();

            // Создаём программу чтения файлов
            var reader = new FileReader();

            // Устанавливаем обработчик события загрузки
            reader.onload = function(event) {
                // Получаем URL-адрес данных изображения
                let dataURL = event.target.result;

                $('.messager-img').append('<div id="img_item" class="messager-img_item"><div class="btn_close img_close"></div><img src="' + dataURL + '"></div>');
            };

            // Считаем большой двоичный объект как URL-адрес данных
            reader.readAsDataURL(blob);
        }
    });

    $(document).on('click', '.img_close', function() {
        $('#img_item').hide('fast', function() {$('#img_item').remove()});
        $('#msg_txt').attr('disabled', false).removeClass('disabled');
        $('.messager-textarea').find('a').show('fast');
    });
});