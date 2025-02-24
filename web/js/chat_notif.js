$(document).ready(function() {
    function openWebSocket() {
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

                case "CheckNewMessage":
                    if (json.cnt > 0) {
                        $('.new_msg_cnt').html(json.cnt).removeClass('hidden');
                    } else {
                        $('.new_msg_cnt').addClass('hidden').html('');
                    }
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
});