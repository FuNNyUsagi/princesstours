$(document).ready(function () {
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
                        //получение списка диалогов
                        var o = getDialogList();
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

                case "setDialogList":
                    $('div.body-header-main-content').html('');
                    $.each(json.data, function(key, item){
                        $('div.body-header-main-content').append(createItemDialog(item));
                    });
                    break;

                case "userOnline":
                    if ($('div.messages-item[data-user="'+json.userID+'"]').length > 0) {
                        $('div.messages-item[data-user="'+json.userID+'"] div.messages-online').addClass('active');
                    }
                    break;

                case "userOffline":
                    if ($('div.messages-item[data-user="'+json.userID+'"]').length > 0) {
                        $('div.messages-item[data-user="'+json.userID+'"] div.messages-online').removeClass('active');
                    }
                    break;

                case "addMessageMain": //Если приходит новое сообщение
                    var msg_block = $('div.messages-item[data-val="'+json.dialogID+'"]');
                    if (msg_block.length > 0) {
                        if (json.countNewMessage > 0) {
                            msg_block.find('div.messages-unread-count').show().html(json.countNewMessage);
                        }

                        msg_block.find('div.messages-last-time').html(json.lastMessageTime);
                        msg_block.find('div.messages-message').html(json.lastMessageText);
                    }

                    break;

                case "getTyping":
                    if (typeof typingTimer !== 'undefined') {
                        clearTimeout(typingTimer);
                    }

                    var tp = '<i class="typing">Typing...</i>';
                    var typing = $('div.body-header-main-content').find('.messages-item[data-val="'+json.dialogID+'"]').find('div.messages-message');
                    var msg = typing.html();

                    typing.html(tp);

                    typingTimer = setTimeout(function(){
                        typing.html(msg);
                    }, 2200);
                    break;

                case "clearNewMessage":
                    var item = $('div.body-header-main-content div.messages-item[data-val="'+json.dialogID+'"]');
                    if(item.length > 0 && json.result == true){
                        item.find('div.messages-unread-count').hide();
                    }
                    break;

                case "deleteDialog":
                    $('div.messages-item[data-val="'+json.dialog+'"]').remove();
                    break;

                case "newMsgForOperator":
                    $("#select_user").addClass('new_msg_operator');
                    $.each(json.data, function(key, item){
                        $("#select_user option[value='"+item.user_id+"']").css('color', 'red').css('font-weight', 'bold');
                        var el = $("#select_user option[value='"+item.user_id+"']").children('span');
                        if ( el.length > 0) {
                            el.html(' | new messages: '+ item.cnt);
                        } else {
                            $("#select_user option[value='"+item.user_id+"']").append('<span> | new messages: '+item.cnt+'</span>');
                        }

                    });
                    break;

                case "UpdateSelectUser":
                    var cnt = json.cnt;
                    var user_id = json.userID;

                    if (cnt == 0) {
                        //Редактируем option
                        $("#select_user option[value='"+user_id+"']").css('color', '#000').css('font-weight', 'normal');
                        var el = $("#select_user option[value='"+user_id+"']").children('span');
                        if ( el.length > 0) {
                            el.remove();
                        }

                        //Редактируем select
                        if ($('#select_user').children('option').find('span').length == 0) {
                            $("#select_user").removeClass('new_msg_operator');
                        }
                    } else {
                        //Редактироем option
                        $("#select_user option[value='"+user_id+"']").css('color', 'red').css('font-weight', 'bold');
                        var el = $("#select_user option[value='"+user_id+"']").children('span');
                        if ( el.length > 0) {
                            el.html(' | new messages: '+ cnt);
                        } else {
                            $("#select_user option[value='"+user_id+"']").append('<span> | new messages: '+cnt+'</span>');
                        }

                        //Редактируем select
                        $("#select_user").addClass('new_msg_operator');
                    }
                    break;

                case "CheckNewMessage":
                    if (json.cnt > 0) {
                        $('.new_msg_cnt').html(json.cnt).removeClass('hidden');
                    } else {
                        $('.new_msg_cnt').addClass('hidden').html('');
                    }
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
    function getDialogList(){
        var obj = {
            section : 'getDialogList',
            data : {
                userID : $('#user_id').val()
            }
        }
        if(userAuth) return obj;
        return false;
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

    function createItemDialog(element){
        var html = '<div class="messages-item col-lg-12" data-val="'+element.idDialog+'" data-user="'+element.userToID+'" data-srch="'+element.dialogName+'">' +
            '                <div class="messages-subitem dialog">' +
            '                    <div class="message-left-container">' +
            '                        <div class="messages-image">' +
            '                            <img src="/uploads/accounts/images/'+(element.avatar == null ? '' : element.avatar)+'" class="avatar_image">' +
            '                            <div class="messages-online'+(element.online ? ' active' : '')+'"></div>' +
            '                        </div>\n' +
            '                        <div class="messages-description">' +
            '                            <div class="messages-name">' +
            '                                '+element.dialogName+'' +
            '                            </div>' +
            '                            <div class="messages-message">'+(element.lastMessage == null ? '' : element.lastMessage.length > 30 ? element.lastMessage.substr(0,30) + "..." : element.lastMessage)+'</div>' +
            '                        </div>' +
            '                    </div>' +
            '                    <div class="message-right-container">' +
            '                        <div class="messages-info">' +
            '                            <div class="messages-last-time">'+(element.lastMessageDate == null ? '' : element.lastMessageDate)+'</div>';

        if(element.countNewMessage > 0){
            html += '                            <div class="messages-unread-count">'+element.countNewMessage+'</div>';
        }else{
            html += '                            <div class="messages-unread-count" style="display:none;">'+element.countNewMessage+'</div>';
        }

        html += '                        </div>' +
            '                    </div>' +
            '                </div>' +
            '                <div class="message-right-container messages-settings dropdown">' +
            '                   <div class="dropdown-content">' +
            '                       <span class="delete_dialog" data-val="'+element.idDialog+'" data-user="'+element.userToID+'">Delete</span>' +
            '                   </div>' +
            '                </div>' +
            '</div>';

        return html;
    }

    //клик по чату
    $(document).on('click', '.dialog', function(e){
        var dialog_id = $(this).parent('div.messages-item').data('val');
        var user_id = $('#user_id').val();
        var user_to = $(this).parent('div.messages-item').data('user');
        window.location.href = '/web/messages/message?_d='+dialog_id+'&_u='+user_id+'&_ut='+user_to;
    });

    //Удаление чата
    $(document).on('click', '.delete_dialog', function(e) {
        popup_settitle('Do you really want to delete the dialog?');
        popup_setcontent('<button data-val="'+$(this).data('val')+'" data-user="'+$(this).data('user')+'" id="confirm_delete_dialog">Confirm</button>');

        $('.popup-fade').fadeIn();
        return false;
    });

    $(document).on('click', '#confirm_delete_dialog', function() {
        popup_info_clear();
        var dialog_id = $(this).data('val');
        var user_to = $(this).data('user');

        popup_setcontent('<span>Please, wait </span><span class="loader_spiner"></span>');

        $.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: '/web/messages/delete-dialog',
            data: {dialog_id: dialog_id},
            success: function(data) {
                if (data.status == 'suc'){
                    $('div.messages-item[data-val="'+dialog_id+'"]').remove();

                    popup_setcontent('');
                    add_msg_success(data.msg);
                    $('.popup-close').trigger('click');

                    var o = deleteDialog(dialog_id, user_to);
                    socket.send(JSON.stringify(o));
                } else {
                    popup_setcontent('<button data-val="'+dialog_id+'" data-user="'+user_to+'" id="confirm_delete_dialog">Confirm</button>');
                    add_msg_error(data.msg);
                }
            }
        });

        return false;
    });

    $(document).on('change', '#select_user', function() {
        var sl = $(this);

        $.ajax({
            type: 'POST',
            dataType: 'JSON',
            url: '/web/messages/get-identif',
            data: {uid: sl.val()},
            success: function(data) {
                if (data.status == 'suc') {
                    /*Разлогиниваемся одним юзером оператора*/
                    var obj = {
                        section: "ChangeUserByOperator",
                        data:{
                            userID : $('#user_id').val()
                        }
                    };

                    socket.send(JSON.stringify(obj));
                    
                    $('#identif').val(data.msg);
                    $('#user_id').val(sl.val());

                    userAuth = false;
                    socket.close();
                    openWebSocket();
                } else {
                    popup_settitle('Error');
                    add_msg_error(data.msg);

                    $('.popup-fade').fadeIn();
                }
            }
        });
    });

    $(document).on('click', '.dropdown', function() {
        $(this).children('.dropdown-content').show();
    });

    $('html').click(function() {
        $('.dropdown-content').hide();
    });

    $(document).on('keyup', '#search_dialog', function() {
        var value = $(this).val().toLowerCase();

        if (value == '') {
            $('.close_search').hide();
        } else {
            $('.close_search').show();
        }

        $(".body-header-main-content div.messages-item").filter(function() {
            $(this).toggle($(this).data('srch').toLowerCase().indexOf(value) > -1)
        });
    });

    $(document).on('click', '.close_search', function() {
        $('#search_dialog').val('');
        $('#search_dialog').trigger('keyup');
        $(this).hide();
    });

    $(document).keydown(function(e) {
        if (e.keyCode === 27) {
            if ($('.popup-fade').is(':hidden')) {
                e.stopPropagation();
                $('#search_dialog').val('');
                $('#search_dialog').trigger('keyup');
                $('.close_search').hide();
            }
        }
    });
});