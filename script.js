var connected = false;
var username = '';
var locked = false;
var userid = -1;
var roomid = -1;
var grabbed = null;
var selectedCard = null;
var dragging = false;
var mode = 0;
var u_cards = [0,0,0,0,0,0,0,0,0,0,0,0,0,0];
var u_playedcards = [0,0,0];
var u_playedid = [0,0,0];
var u_playedusers = ['','',''];
var u_points = 0;
var u_dealer = 3;
var u_selected_user = -1;
var u_current_user = -1;
var u_current_bid_user = -1;
var u_current_bid_value = -1;
var u_suit = -1;
var u_next = -1;
var next_bid = -1;

var cursorWithin = function(e, el) {
    var x = 0;
    var y = 0;
    if(e.type == 'touchstart' || e.type == 'touchmove' || e.type == 'touchend' || e.type == 'touchcancel') {
        var touch = e.touches[0] || e.changedTouches[0];
        x = touch.clientX;
        y = touch.clientY;
    } else if (e.type == 'mousedown' || e.type == 'mouseup' || e.type == 'mousemove' || e.type == 'mouseover'|| e.type=='mouseout' || e.type=='mouseenter' || e.type=='mouseleave') {
        x = e.clientX;
        y = e.clientY;
    }
    var rect = el.getBoundingClientRect();
    if (x > rect.left && x < rect.right && y > rect.top && y < rect.bottom)
        return true;
    else
        return false;
}
var getEventLoc = function(e){
    var loc = {x:0, y:0};
    if(e.type == 'touchstart' || e.type == 'touchmove' || e.type == 'touchend' || e.type == 'touchcancel') {
        var touch = e.touches[0] || e.changedTouches[0];
        loc.x = touch.pageX;
        loc.y = touch.pageY;
    } else if (e.type == 'mousedown' || e.type == 'mouseup' || e.type == 'mousemove' || e.type == 'mouseover'|| e.type=='mouseout' || e.type=='mouseenter' || e.type=='mouseleave') {
        loc.x = e.pageX;
        loc.y = e.pageY;
    }
    return loc;
};
var grabCard = function(e, el) {
    dragging = true;
    grabbed = el;
    if (selectedCard == null) {
        selectedCard = document.getElementById("dragger");
    }
    var loc = getEventLoc(e);
    var rect = selectedCard.getBoundingClientRect();
    selectedCard.style.top = (loc.y - 78) + 'px';
    selectedCard.style.left = (loc.x - 53) + 'px';
    selectedCard.style.display = null;
    selectedCard.setAttribute('value', grabbed.getAttribute('value'));
    grabbed.style.display = 'none';
}
var dragCard = function(event) {
    if (dragging == false || selectedCard == null || grabbed == null)
        return;
    var loc = getEventLoc(event);
    var rect = selectedCard.getBoundingClientRect();
    selectedCard.style.top = (loc.y - 78) + 'px';
    selectedCard.style.left = (loc.x - 53) + 'px';
}
var dropCard = function(event) {
    dragging = false;
    if (selectedCard == null || grabbed == null)
        return;
    if (document.getElementById("bidarea").style.display != 'none') {
        selectedCard.style.display = 'none';
        grabbed.style.display = null;
        grabbed = null;
        return;
    }
    
    var value = selectedCard.getAttribute('value');
    selectedCard.style.display = 'none';
    grabbed.style.display = null;
    
    if (cursorWithin(event, document.getElementById("table"))){
        grabbed.setAttribute('value', '0');
        var msg = {
            message: ('::C'+value),
            name: username,
            room: roomid,
            id: userid
        };
        console.log(msg);
        websocket.send(JSON.stringify(msg));
    }
    grabbed = null;
}

///////////////////////////////////////////////////////////////////////////////////////////////

var initSocket = function() {
    var wsUri = "ws://" + window.location.host.split(':')[0] + ":9000/server.php";
    websocket = new WebSocket(wsUri); 
    
    websocket.onopen = function(ev) {
        connected = true;
        console.log('Connected to server');
    }

    document.getElementById('message').onkeydown =(function(event){ 
        if (event.keyCode != 13)
            return;
        var mymessage = document.getElementById('message').value;
        document.getElementById('message').value = '';
        var myname = username;
        
        if(mymessage == "")
            return;
            
        var objDiv = document.getElementById("message_box");
        objDiv.scrollTop = objDiv.scrollHeight;
        var msg = {
            message: mymessage,
            name: myname,
            room: roomid,
            id: userid
        };
        websocket.send(JSON.stringify(msg));
    });
    
    websocket.onmessage = function(ev) {
        var msg = JSON.parse(ev.data);
        var type = msg.type;
        var umsg = msg.message;
        var uroom = msg.room;
        var uname = msg.name;
        
        if(type == 'usermsg') {
            var msg = document.createElement('div');
            msg.className = '';
            msg.innerHTML = '<span class="user_name">'+uname+'</span> : <span class="user_message">'+umsg+'</span>';
            document.getElementById('message_box').appendChild(msg);
        }
        else if(type == 'system') {
            var msg = document.createElement('div');
            msg.className = 'system_msg';
            msg.innerHTML = umsg;
            document.getElementById('message_box').appendChild(msg);
        }
        else if(type == 'data') {
            if (umsg[0] == '{'){
                var data = JSON.parse(umsg);
                if (userid == -1 && data.id != undefined) {
                    userid = parseInt(data.id);
                    var msg = document.createElement('div');
                    msg.className = 'system_msg';
                    msg.style.fontSize = '0.7em';
                    msg.innerHTML = 'Room ' + roomid;
                    document.getElementById('message_box').appendChild(msg);
                }
                if (data.id != undefined && userid == parseInt(data.id)) {
                    if (data.u != undefined) {
                        var udata = data.u.split(',');
                        points = udata[0];
                        for (var i = 0; i < 14; i++){
                            u_cards[i] = parseInt(udata[i+1]);
                            document.getElementById("c"+i).setAttribute("value", udata[i+1]);
                        }
                        document.getElementById("points").innerHTML = points;
                    }
                    if (data.r != undefined) {
                        var rdata = data.r.split(',');
                        console.log(rdata);
                        locked = rdata[0];
                        mode = rdata[1];
                        u_selected_user = rdata[2];
                        u_current_user = rdata[3];
                        u_current_bid_user = rdata[4];
                        u_current_bid_value = rdata[5];
                        u_suit = rdata[6];
                        for (var i = 7; i < 10; i++){
                            u_playedcards[i-7] = parseInt(rdata[i]);
                        }
                        for (var i = 10; i < 14; i++){
                            u_playedusers[i-10] = rdata[i];
                        }
                        for (var i = 14; i < 17; i++){
                            u_playedusers[i-14] = rdata[i];
                        }
                        // setMode();
                    }
                    // decorateCards();
                }
            }
        }
        
        var objDiv = document.getElementById("message_box");
        objDiv.scrollTop = objDiv.scrollHeight;
    };
    websocket.onclose = function(ev){
        var msg = document.createElement('div');
        msg.className = 'system_msg';
        msg.innerHTML = 'Connection Closed';
        document.getElementById('message_box').appendChild(msg);
    }; 
    websocket.onerror = function(ev){
        websocket.onclose = null;
        if(XMLHttpRequest) var x = new XMLHttpRequest();
        else var x = new ActiveXObject("Microsoft.XMLHTTP");
        x.open("GET", "server.php", true);
        x.send();
        var msg = document.createElement('div');
        initSocket();
    }; 
}
var readyUp = function() {    
    var msg = {
        message: '::R',
        name: username,
        room: roomid,
        id: userid
    };
    websocket.send(JSON.stringify(msg));
    document.getElementById('ready_button_container').style.display = 'none';
    document.getElementById('game_area').style.display = 'block';
}
var login = function() {
    if (connected == false)
        return;
    username = document.getElementById('input_name').value.replace(/,/g,'');
    roomid = document.getElementById('input_room').value;
    if (username == '')
        return;
    document.getElementById('login').style.display = 'none';
    
    var msg = {
        message: '::J',
        name: username,
        room: roomid,
        id: userid
    };
    websocket.send(JSON.stringify(msg));
    document.getElementById('ready_button_container').style.display = null;
    document.getElementById('chat_box').style.display = 'block';
}
var placeBid = function(v){
    var msg = {
        message: '::B' + v,
        name: username,
        room: roomid,
        id: userid
    };
    websocket.send(JSON.stringify(msg));
    document.getElementById('bidarea').style.display = 'none';
    document.getElementById('playarea').style.display = null;
}
var fixRoomNumber = function(e){
    if (e.value > 5)
        e.value = '5';
    else if (e.value < 1)
        e.value = '1';
}