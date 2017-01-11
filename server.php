<?php
set_time_limit(0);
$host = 'localhost';
$port = '9000';
$null = NULL;
$user_count = 0;

class User {
    public $cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    public $points = 50;
    public $bid = 0;
    public $name = '';
    public $active = false;
    public $address = '';
    public $socket = null;
    function data(){
        $msg = $this->points;
        for ($i = 0; $i < 14; $i++) {
            $msg = $msg.','.$this->cards[$i];
        }
        return $msg;
    }
}
class Room {
    public $next_id = 0;
    public $next_bid = 0;
    public $bid_count = 0;
    public $lead_value = 0;
    public $lead_id = -1;
    public $selected_id = 0;
    public $pending_suit_id = -1;
    public $suit_id = -1; // -1=none,spade,club,diamond,heart
    public $mode = 0;     // -1=none,frog,mazar,solo,hsolo,smazar
    public $ready = 0;
    public $user = array();
    public $down = array(0,0,0);
    public $played = array(0,0,0);
    public $u_played = array('','','');
    public $round_score = 0;
    public $dealer = 3;
    public function data(){
        $msg = $this->dealer.','.$this->next_id.','.$this->round_score;
        for ($i = 0; $i < 3; $i++) {
            $msg = $msg.','.$this->played[$i];
        }
        for ($i = 0; $i < 3; $i++) {
            $msg = $msg.','.$this->u_played[$i];
        }
        return $msg.','.$this->next_bid.','.$this->mode.','.$this->suit_id;
    }
    public function add_user($name, $address, $socket){
        if (count($this->user) >=4)
            return -1;
        $id = 0;
        for ($i = 0; $i < 4; $i++){
            if (isset($this->user[$i]) === false || $this->user[$i]->active === false){
                $id = $i;
                break;
            }
        }
        str_replace (',', '', $name);
        $this->user[$id] = new User;
        $this->user[$id]->name = $name;
        $this->user[$id]->address = $address;
        $this->user[$i]->active = true;
        $this->user[$i]->socket = $socket;
        if (count($this->user) === 3){
            $this->deal();
        }
        return $id;
    }
    public function deal(){
        $user_count = count($this->user);
        if ($user_count < 3)
            return;
        $this->ready = 0;
        $this->mode = -1;
        $this->bid_count = 0;
        $this->suit_id = -1;
        $deck = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,32,32,33,34,35,36);
        if ($this->lead_id !== -1){
            $this->next_id = $this->lead_id;
        }
        else if ($user_count > 3){
            $this->next_id = ($this->dealer + 1)%4;
        }
        else
            $this->next_id = ($this->next_id + 1)%3;
        $this->next_bid = $this->next_id;
        for ($i = 0; $i < $user_count; $i++)
            $this->user[$i]->cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        $this->round_score = 0;
        $next = ($this->dealer + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand()%count($deck);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        $next = ($next + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand()%count($deck);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        $next = ($next + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand()%count($deck);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        for ($i = 0; $i < 3; $i++) {
            $rnd = rand()%count($deck);
            $this->down[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        $this->played = array(0,0,0);
        $this->u_played = array('','','');
    }
    public function rotate(){
        if (count($this->user) <= 3)
            return;
        $this->dealer = ($this->dealer + 1)%4;
    }
    public function request($user_id){
        return $this->user[$user_id]->data();
    }
    public function is_playable($user_id, $card_id){
        if ($this->played[0] <= 0)
            return true;
        $value = floor(($card_id-1)/9);
        
        if ($value === floor(($this->played[0]-1)/9)){
            return true;
        }
        else{
            for ($i = 0; $i < 13; $i++){
                $card = $this->user[$user_id]->cards[$i];
                if ($card === $card_id)
                    $i++;
                else if (floor(($card-1)/9) === floor(($this->played[0]-1)/9) || ($value !== $this->suit_id && floor(($card-1)/9) === $this->suit_id))
                    return false;
                
            }
        }
        if ($value !== $this->suit_id){
            return true;
        }
        return false;
    }
    public function played($user_id, $card_id){
        if ($this->mode > -1 && $card_id === 0 || $user_id !== $this->next_id)
            return;
        if(($key = array_search($card_id, $this->user[$user_id]->cards)) === false) {
            return;
        }
        if ($user_id === $this->selected_id && $this->user[$user_id]->cards[11] !== 0 || $this->user[$user_id]->cards[12] !== 0 || $this->user[$user_id]->cards[13] !== 0){
            $this->round_score += $this->card_to_score($card_id);
            $this->user[$user_id]->cards[$key] = 0;
            rsort($this->user[$user_id]->cards, SORT_REGULAR);
            return;            
        }
        if ($this->is_playable($user_id, $card_id) === false)
            return;
        $card_suit = floor(($this->played[0]-1)/9);
        $card_value = ($this->played[0]-1)%9;
        if ($this->played[0] === 0 && $user_id === $this->lead_id){
            $this->played[0] = $card_id;
            $this->u_played[0] = $this->user[$user_id]->name;
            echo "slot 0\n";
        }
        else if ($this->played[1] === 0){
            $this->played[1] = $card_id;
            $this->u_played[1] = $this->user[$user_id]->name;
            echo "slot 1\n";
            echo (int)($card_suit === floor(($this->played[0]-1)/9))."\n";
            echo (int)($card_value > ($this->played[0]-1)%9)."\n";
            if ((($card_suit === floor(($this->played[0]-1)/9)) && ($card_value > ($this->played[0]-1)%9)) || (($card_suit === $this->suit_id && $card_value > (($this->played[0]-1)%9)))){
                echo $user_id."is leading\n";
                $this->lead_id = $user_id;
            }
        }
        else if ($this->played[2] === 0){
            $this->played[2] = $card_id;
            $this->u_played[2] = $this->user[$user_id]->name;
            echo "slot 2\n";
            if ((($card_suit === floor(($this->played[0]-1)/9)) && ($card_value > ($this->played[0]-1)%9)) || (($card_suit === $this->suit_id && $card_value > (($this->played[0]-1)%9))) &&
                (($card_suit === floor(($this->played[1]-1)/9)) && ($card_value > ($this->played[1]-1)%9)) || (($card_suit === $this->suit_id && $card_value > (($this->played[1]-1)%9)))){
                echo $user_id."is leading\n";
                $this->lead_id = $user_id;
                }
        }
        $this->user[$user_id]->cards[$key] = 0;
        rsort($this->user[$user_id]->cards, SORT_REGULAR);
        $this->next_id = ($this->next_id + 1)%count($this->user);
        if ($this->next_id === $this->dealer)
            $this->next_id = ($this->next_id + 1)%count($this->user);
        $this->get_score();
    }
    public function give_down($user_id){
        if ($user_id !== $this->selected_id)
            return;
        for ($i = 0; $i < 3; $i++)
            $this->user[$user_id]->cards[$i+11] = $this->down[$i];
        rsort($this->user[$user_id]->cards, SORT_REGULAR);
        $this->down = array(0,0,0);
    }
    public function card_to_score($card_id){
        $value = ($card_id-1)%9;
        switch ($value){
            case 8:
                return 11; // A
            case 7:
                return 10; // 10
            case 6:
                return 4;  // K
            case 5:
                return 3;  // Q
            case 5:
                return 2;  // J
            default:
                return 0;
        }
    }
    public function add_score($score){
        if ($score > 0){
            for ($i = 0; $i < count($this->user); $i++){
                if ($i !== $this->dealer){
                    $this->user[$this->selected_id]->points += $score;
                    $this->user[$i]->points -= $score;
                }
            }
        }
        else if ($score < 0){
            for ($i = 0; $i < count($this->user); $i++){
                $this->user[$this->selected_id]->points += $score;
                $this->user[$i]->points -= $score;
            }
        }
    }
    public function get_score(){
        if ($this->played[0] === 0 || $this->played[1] === 0 || $this->played[2] === 0)
            return;
        $won = ($this->lead_id === $this->selected_id);
        if ($won === true && $this->mode === 1){
            $this->add_score(-30);
            $this->played = array(0,0,0);
            $this->rotate();
            $this->deal();
            return;
        }
        if ($won === true)
            $this->round_score += ($this->card_to_score($this->played[0]) + $this->card_to_score($this->played[1]) + $this->card_to_score($this->played[2]));
        $round_over = true;
        for ($i = 0; $i < count($this->user); $i++){
            if (empty(array_filter($this->user[$i]->cards)) === false){
                $round_over = false;
            }
        }
        if ($round_over === true){
            if ($this->mode === 2){
                $this->round_score *= 2;
            }
            else if ($this->mode === 3){
                $this->round_score *= 3;
            }
            else if ($this->mode === 1){
                $this->round_score = 30;
            }
            $this->round_score -= 60;
            $this->add_score($this->round_score);
            $this->rotate();
            $this->deal();
        }
        $this->played = array(0,0,0);
    }
    public function place_bid($user_id, $value, $suit){
        $user_count = count($this->user);
        if ($user_count < 3)
            return;
        if ($this->next_bid === $user_id && $this->mode < $value){
            $this->mode = $value;
            $this->selected_id = $user_id;
            $this->next_id = $user_id;
            $this->selected_id = $user_id;
            $this->pending_suit_id = $suit;
            $this->bid_count++;
            $this->bid_count++;
            if ($this->bid_count >= 3){
                if ($this->mode === -1){
                    $this->deal();
                    return;
                }
                else{
                    $this->ready = 1;
                    $this->next_bid = -1;
                    $this->lead_id = $user_id;
                    $this->suit_id = $this->pending_suit_id;
                    return;
                }
            }
        }
        if ($user_count > 3){
            $this->next_bid = ($this->next_bid + 1)%4;
            if ($this->next_bid === $this->dealer)
                $this->next_bid = ($this->next_bid + 1)%4;
        }
        else
            $this->next_bid = ($this->next_bid + 1)%3;
    }
}
$room = array();
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, 0, $port);
socket_listen($socket);
$clients = array($socket);
while (true) {
	$changed = $clients;
	socket_select($changed, $null, $null, 0, 10);
	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket);
		$clients[] = $socket_new;
        
		$header = socket_read($socket_new, 1024);
		perform_handshaking($header, $socket_new, $host, $port);
		$found_socket = array_search($socket, $changed);
        $user_count++;
		unset($changed[$found_socket]);
	}
	foreach ($changed as $changed_socket) {
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
            try {
                $received_text = unmask($buf);
                $tst_msg = json_decode($received_text);
                if (is_object($tst_msg) === false)
                    continue;
                $user_room = $tst_msg->room;
                $user_id = $tst_msg->id;
                $user_name = $tst_msg->name;
                $user_message = $tst_msg->message;
                $msgip = '';
                socket_getpeername($changed_socket,$msgip);
                if (array_key_exists($user_room,$room) === false) {
                    $room[$user_room] = new Room;
                }
                
                if ($user_message === '::JOIN') {
                    if (count($room[$user_room]->user) >= 4){
                        $response_text = mask(json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>'This room is full and cannot be joined.')));
                        @socket_write($changed_socket,$response_text,strlen($response_text));
                    }
                    else{
                        $found_socket = array_search($changed_socket, $clients);
                        $user_id = $room[$user_room]->add_user($user_name, $msgip, $clients[$found_socket]);
                        $response_text = mask(json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$user_id.'","u":"'.($room[$user_room]->user[$user_id]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                        @socket_write($room[$user_room]->user[$user_id]->socket,$response_text,strlen($response_text));
                        $response_text = mask(json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>($user_name.' has joined.'))));
                        for ($i = 0; $i < count($room[$user_room]->user); $i++){
                            if ($i !== $user_id)
                                @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                        }
                        for ($i = 0; $i < count($room[$user_room]->user); $i++){
                            $response_text = mask(json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->user[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                            @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                        }                        }
                }
                else if ($user_message === '::Q') {
                    socket_close($socket);
                    exit;
                }
                else if ($user_message === '::R') {
                    if (isset($room[$user_room]) && isset($room[$user_room]->user[$user_id])){
                        $response_text = mask(json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$user_id.'","u":"'.($room[$user_room]->user[$user_id]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                        @socket_write($room[$user_room]->user[$user_id]->socket,$response_text,strlen($response_text));
                    }
                }
                else if (substr($user_message,0,3) === "::b") {
                    if (strlen($user_message) > 3){
                        switch (substr($user_message,3)){
                            case '1':
                                $b_msg = $user_name.' selected Frog.';
                                $room[$user_room]->place_bid($user_id, 0, 3);
                                break;
                            case '2':
                                $b_msg = $user_name.' selected Solo.';
                                $room[$user_room]->place_bid($user_id, 2, 0);
                                break;
                            case '3':
                                $b_msg = $user_name.' selected Solo.';
                                $room[$user_room]->place_bid($user_id, 2, 1);
                                break;
                            case '4':
                                $b_msg = $user_name.' selected Solo.';
                                $room[$user_room]->place_bid($user_id, 2, 2);
                                break;
                            case '5':
                                $b_msg = $user_name.' selected Heart Solo.';
                                $room[$user_room]->place_bid($user_id, 3, 3);
                                break;
                            case '6':
                                $b_msg = $user_name.' selected Mazar.';
                                $room[$user_room]->place_bid($user_id, 1, -1);
                                break;
                            case '7':
                                break; // NOT IMPLEMENTED
                                $b_msg = $user_name.' selected Spread Mazar.';
                                $room[$user_room]->place_bid($user_id, 4, -1);
                                break;
                            default:
                                $b_msg = $user_name.' passed.';
                                $room[$user_room]->place_bid($user_id, -1, -1);
                                break;
                        }
                        $response_text = mask(json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>$b_msg)));
                        for ($i = 0; $i < count($room[$user_room]->user); $i++){
                            @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                            $response_text = mask(json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->user[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                            @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                        }
                    }
                }
                else if (substr($user_message,0,3) === "::c"){
                    if (strlen($user_message) > 3){
                        $room[$user_room]->played($user_id,(int)substr($user_message,3));
                        for ($i = 0; $i < count($room[$user_room]->user); $i++){
                            $response_text = mask(json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->user[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                            @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                        }
                    }
                }
                else if ($user_name !== NULL && $user_room !== NULL) {
                    $response_text = mask(json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>$user_name, 'message'=>$user_message)));
                    for ($i = 0; $i < count($room[$user_room]->user); $i++){
                        @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                    }
                }
                break 2;
            } catch (Exception $e) {
                break;
            }
		}
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) {
            $user_count--;
            if ($user_count < 1)
                exit;
            $u_room = -1;
            $u_name = 'A user';
            $u_id = -1;
            $msgip = '';
            socket_getpeername($changed_socket,$msgip);
            for ($i = 0; $i <= 5; $i++){
                if (isset($room[$i])){
                    for ($j = 0; $j <= 10; $j++){
                        if (isset($room[$i]->user[$j])){
                            if ($msgip === $room[$i]->user[$j]->address){
                                $u_room = $i;
                                $u_id = $j;
                                $u_name = $room[$i]->user[$j]->name;
                                $room[$u_room]->user[$u_id]->active = false;
                                break 2;
                            }
                        }
                    }
                }
            }
            if ($u_id !== -1 && $u_room !== -1){
                $response_text = mask(json_encode(array('type'=>'usermsg', 'room'=>$u_room, 'name'=>'Server', 'message'=>$u_name.' has disconnected.')));
                for ($i = 0; $i < count($room[$user_room]->user); $i++){
                    @socket_write($room[$user_room]->user[$i]->socket,$response_text,strlen($response_text));
                }            
            }
			$found_socket = array_search($changed_socket, $clients);
			unset($clients[$found_socket]);
		}
	}
}
socket_close($socket, $msg);
function send_message($msg){
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}
function unmask($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$masks = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$masks = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$masks = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $masks[$i%4];
	}
	return $text;
}
function mask($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}

function perform_handshaking($receved_header,$client_conn, $host, $port)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}