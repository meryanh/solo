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
var within = function(x, y, e) {
    var rect = e.getBoundingClientRect();
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
var grabCard = function(e) {
    dragging = true;
    grabbed = e;
    if (selectedCard == null) {
        selectedCard = document.getElementById("s0");
    }
    selectedCard.innerHTML = grabbed.innerHTML;
    selectedCard.style.backgroundColor = '#fff';
    var c_suit = Math.floor((parseInt(grabbed.getAttribute('value'))-1)/9);
    if (c_suit == parseInt(u_suit)){
        selectedCard.style.backgroundColor = '#111';
        if (c_suit < 2)
            selectedCard.style.color= '#fff';
        else
            selectedCard.style.color= '#d11';
    }
    else if (c_suit >= 2) {
        selectedCard.style.backgroundColor = null;
        selectedCard.style.color= '#d11';
    }
    else {
        selectedCard.style.backgroundColor = null;
        selectedCard.style.color = null;
    }
}
var dragCard = function(event) {
    if (dragging == false || selectedCard == null || grabbed == null)
        return;
    if (selectedCard.style.display = 'none') {
        grabbed.style.display = 'none';
        selectedCard.style.display = 'block';
    }
    var loc = getEventLoc(event);
    var rect = selectedCard.getBoundingClientRect();
    selectedCard.style.top = (loc.y - rect.height/2) + 'px';
    selectedCard.style.left = (loc.x - rect.width/2) + 'px';
}
var getCardText = function(cardId){
    if (cardId == 0)
        return '';
    var value = (cardId-1)%9;
    var suit = Math.floor((cardId-1)/9);
    var text = '';
    switch (value){
        case 0:
            text += '6';
            break;
        case 1:
            text += '7';
            break;
        case 2:
            text += '8';
            break;
        case 3:
            text += '9';
            break;
        case 4:
            text += 'J';
            break;
        case 5:
            text += 'Q';
            break;
        case 6:
            text += 'K';
            break;
        case 7:
            text += '10';
            break;
        case 8:
            text += 'A';
            break;
    }
    switch (suit){
        case 0:
            text += '♠';
            break;
        case 1:
            text += '♣';
            break;
        case 2:
            text += '♦';
            break;
        case 3:
            text += '♥';
            break;
    }
    return text;
}
var dropCard = function(event) {
    if (dragging == false || grabbed == null || selectedCard == null)
        return;
    dragging = false;
    var loc = getEventLoc(event);
    if (within(loc.x, loc.y, document.getElementById("playarea"))){
        var msg = {
            message: ('::C'+grabbed.getAttribute('value')),
            name: username,
            room: roomid,
            id: userid
        };
        websocket.send(JSON.stringify(msg));
    }
    grabbed.style.display = 'inline-block';
    selectedCard.style.display = 'none';
    decorateCards();
}
var decorateCards = function() {
    var cards = document.getElementsByClassName('card');
    var length = cards.length;
    
    var element;
    for (var i = 0; i < 14; i++){
        element = document.getElementById('c' + i);
        element.setAttribute('value', u_cards[i]);
        element.innerHTML = getCardText(u_cards[i]);
        if (u_cards[i] == 0)
            element.style.display = 'none';
        else
            element.style.display = null;
    }
    for (var i = 0; i < 3; i++){
        element = document.getElementById('d' + i);
        element.setAttribute('value', u_playedcards[i]);
        element.innerHTML = getCardText(u_playedcards[i]);
        if (u_playedcards[i] == 0)
            element.style.backgroundColor = '#333';
    }
    for (var i = 0; i < length; i++) {
        if (cards[i].getAttribute('value') != '0'){
            if (cards[i].innerHTML.indexOf('♥') > -1) {
                cards[i].style.color= '#d11';
                if (cards[i].getElementsByClassName('suit-symbol').length == 0){
                    var symbol = document.createElement('div');
                    symbol.innerHTML = '♥';
                    symbol.className = 'suit-symbol';
                    cards[i].appendChild(symbol);
                }
            }
            else if (cards[i].innerHTML.indexOf('♦') > -1) {
                cards[i].style.color= '#d11';
                if (cards[i].getElementsByClassName('suit-symbol').length == 0){
                    var symbol = document.createElement('div');
                    symbol.innerHTML = '♦';
                    symbol.className = 'suit-symbol';
                    cards[i].appendChild(symbol);
                }
            }
            else if (cards[i].innerHTML.indexOf('♣') > -1) {
                cards[i].style.color= null;
                if (cards[i].getElementsByClassName('suit-symbol').length == 0){
                    var symbol = document.createElement('div');
                    symbol.innerHTML = '♣';
                    symbol.className = 'suit-symbol';
                    cards[i].appendChild(symbol);
                }
            }
            else if (cards[i].innerHTML.indexOf('♠') > -1) {
                cards[i].style.color= null;
                if (cards[i].getElementsByClassName('suit-symbol').length == 0){
                    var symbol = document.createElement('div');
                    symbol.innerHTML = '♠';
                    symbol.className = 'suit-symbol';
                    cards[i].appendChild(symbol);
                }
            }
            else {
                cards[i].style.color = null;
                cards[i].innerHTML = "";
            }
                
            if (cards[i].innerHTML == '') {
                cards[i].style.backgroundColor = '#555';
            }
            var c_suit = Math.floor((parseInt(cards[i].getAttribute('value'))-1)/9);
            if (c_suit == parseInt(u_suit)){
                cards[i].style.backgroundColor = '#111';
                if (c_suit < 2)
                    cards[i].style.color= '#fff';
            }
            else
                cards[i].style.backgroundColor = null;
        }
    }
}
var setMode = function(){
    if (u_cards.every((val, i, arr) => val == arr[0]))
        return;
    if (mode == 0){
        if (u_current_bid_user == userid){
            document.getElementById("bidarea").style.display = null;
            document.getElementById("playarea").style.display = 'none';
            if (u_current_bid_value == -1){
                for (var i = 0; i < 7; i++){
                    document.getElementById("b"+i).style.display = null;
                }
            }
            else if (u_current_bid_value == 0){
                document.getElementById("b0").style.display = null;
                document.getElementById("b1").style.display = 'none';
                document.getElementById("b2").style.display = null;
                document.getElementById("b3").style.display = null;
                document.getElementById("b4").style.display = null;
                document.getElementById("b5").style.display = null;
                document.getElementById("b6").style.display = null;
                document.getElementById("b7").style.display = null;
            }
            else if (u_current_bid_value == 1){
                document.getElementById("b0").style.display = null;
                document.getElementById("b1").style.display = 'none';
                document.getElementById("b2").style.display = null;
                document.getElementById("b3").style.display = null;
                document.getElementById("b4").style.display = null;
                document.getElementById("b5").style.display = null;
                document.getElementById("b6").style.display = 'none';
                document.getElementById("b7").style.display = null;
            }
            else if (u_current_bid_value == 2){
                document.getElementById("b0").style.display = null;
                document.getElementById("b1").style.display = 'none';
                document.getElementById("b2").style.display = 'none';
                document.getElementById("b3").style.display = 'none';
                document.getElementById("b4").style.display = 'none';
                document.getElementById("b5").style.display = null;
                document.getElementById("b6").style.display = 'none';
                document.getElementById("b7").style.display = null;
            }
            else if (u_current_bid_value == 3){
                document.getElementById("b0").style.display = null;
                for (var i = 1; i < 7; i++){
                    document.getElementById("b"+i).style.display = 'none';
                }
                //document.getElementById("b7").style.display = null;
            }
            else if (u_current_bid_value == 4){
                // NOT IMPLEMENTED
            }
        }
        else {
        document.getElementById("bidarea").style.display = 'none';
        document.getElementById("playarea").style.display = 'none';
        }
    }
    else if (mode == 1){
        document.getElementById("bidarea").style.display = 'none';
        document.getElementById("playarea").style.display = null;
    }
    else {
        document.getElementById("bidarea").style.display = 'none';
        document.getElementById("playarea").style.display = 'none';
    }
}
var initSocket = function() {
    var wsUri = "ws://" + window.location.host + ":9000/server.php";
    websocket = new WebSocket(wsUri); 
    
    websocket.onopen = function(ev) {
        connected = true;
        console.log('Connected to server');
    }

    document.getElementById('send-btn').onclick =(function(){ //use clicks message send button	
        var mymessage = document.getElementById('message').value; //get message text
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

        if (uroom != roomid)
            return;
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
                        for (var i = 1; i < 15; i++){
                            u_cards[i-1] = parseInt(udata[i]);
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
                        setMode();
                    }
                    decorateCards();
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
}
var login = function() {
    if (connected == false)
        return;
    username = document.getElementById('input_name').value;
    roomid = document.getElementById('input_room').value;
    if (username == '')
        return;
    document.getElementById('login').style.display = 'none';
    document.getElementById('game_area').style.display = 'block';
    
    var msg = {
        message: '::J',
        name: username,
        room: roomid,
        id: userid
    };
    websocket.send(JSON.stringify(msg));
    document.getElementById('ready_button_container').style.display = null;
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