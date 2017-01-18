<?php
include 'socket.php';
class User {
    public $cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    public $points = 50;
    public $name = '';
    public $bid_level = -1;
    public $active = false;
    public $address = '';
    public $socket = null;
    function data(){
        $msg = $this->points;
        for ($i = 0; $i < 14; $i++){
            $msg = $msg.','.$this->cards[$i];
        }
        return $msg;
    }
}
class Room {
    public $users = null;                       // Array of users (initialized when first user added)
    public $user_count = 0;                     // The number of connected users (max 4)
    public $current_user = -1;                  // User ID of next user to play
    public $selected_user = -1;                 // User ID of user to win bid
    public $dealer = 3;                         // User ID of the dealer (default 3)
    public $down = array(0,0,0);                // The three cards in the "down" pile
    public $played = array(0,0,0);              // ID of each played card
    public $user_played_name = array('','',''); // Name of player for each played card
    public $user_played_id = array(0,0,0);      // ID of player for each played cards
    public $mode = -1;                          // -1=waiting, 0=bidding, 1=playing, 2=game over (restart)
    public $bid_count = 0;                      // Count of users that have placed a bid
    public $current_bid_user = -1;              // User ID of next user to bid
    public $current_bid_value = -1;             // ID of currently winning bid type
    public $selected_suit = -1;                 // Suit used as trump in play (-1=none, 0=spade, 1=club, 2=diamond, 3=heart)
    public $pending_selected_suit = -1;         // Suit to be used as trump if the current highest bid succeeds
    public function data(){
        $msg = $this->mode.','.$this->selected_user.','.$this->current_user.','.$this->current_bid_user.','.$this->selected_suit;
        for ($i = 0; $i < 3; $i++){
            $msg = $msg.','.$this->played[$i];
        }
        for ($i = 0; $i < 3; $i++){
            $msg = $msg.','.$this->user_played_name[$i];
        }
        for ($i = 0; $i < 3; $i++){
            $msg = $msg.','.$this->user_played_id[$i];
        }
        return $msg;
    }
    function add_user($user_name, $socket){
        if ($this->users === null){
            $this->users = array();
            $this->users[0] = new User;
            $this->users[1] = new User;
            $this->users[2] = new User;
            $this->users[3] = new User;
        }
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->active === false){
                $this->users[$i]->socket = $socket;
                $this->users[$i]->name = $user_name;
                $this->users[$i]->active = true;
                //$this->users[$i]->address = get_ip($socket);
                $this->user_count++;
                if ($this->mode === -1 && $this->user_count >= 3){
                    $this->mode = 0;
                    //$this->deal(0);
                }
                return $i;
            }
        }
        return -1;
    }
    function remove_user($socket){
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->socket === $socket){
                $this->users[$i]->active = false;
                $this->user_count--;
            }
        }
    }
    function deal(){
        if ($this->mode !== 0 || $this->user_count < 3)
            return;
            $first_bid = ($this->dealer + 1) % $this->user_count;
        if ($this->users[$first_bid]->active === false)
            $first_bid = ($this->dealer + 1) % $this->user_count;
        $this->pending_selected_suit = -1;
        for ($i = 0; $i < 4; $i++){
            $this->users[$i]->bid_level = -1;
            $this->users[$i]->cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        }
        $this->down = array(0,0,0);
        $this->played = array(0,0,0);
        $this->user_played_name = array('','','');
        $this->user_played_id = array(0,0,0);
        $deck = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,32,32,33,34,35,36);
        $index = 0;
        for ($i = 0; $i < 4; $i++){
            if ($i === $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active !== false){
                for ($j = 0; $j < 4; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$index+$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
            $index++;
        }
        for ($i = 0; $i < 4; $i++){
            if ($i === $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active !== false){
                for ($j = 0; $j < 3; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$index+$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
            $index++;
        }
        for ($i = 0; $i < 3; $i++){
            $rnd = rand(0, count($deck)-1);
            $this->down[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        for ($i = 0; $i < 4; $i++){
            if ($i === $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active !== false){
                for ($j = 0; $j < 4; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$index+$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
            $index++;
        }
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->active !== false){
                rsort($this->users[$i]->cards, SORT_REGULAR);
            }
        }
        $this->bid_count = 0;
        $this->current_bid_user = $first_bid;
        $this->selected_suit = -1;
        $this->pending_selected_suit = -1;
        $this->mode = 0;
    }
    function rotate_dealer(){
        if ($this->user_count < 3)
            return;
        $this->dealer = ($this->dealer + 1) % $this->user_count;
        if ($this->users[$this->dealer]->active === false)
            $this->dealer = ($this->dealer + 1) % $this->user_count;
        echo "Current dealer is user ".$this->dealer."\n";
    }
    public function place_bid($user_id, $value, $suit){
        if ($this->mode !== 0 ||
            $this->user_count < 3 ||
            ($user_id === $this->dealer && $this->user_count > 3) || 
            $user_id !== $this->current_bid_user ||
            $user_id > 3 || 
            $this->users[$user_id]->bid_level !== -1)
            return false;
        $this->users[$user_id]->bid_level = $value;
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->bid_level > $value){
                echo "Current bidder is user ".$this->current_bid_user."\n";
                echo "Pending suit is ".$this->pending_selected_suit."\n";
                echo "Current bid level is ".$this->current_bid_value."\n";
                $this->bid_count++;
                return true;
            }
        }
        $this->pending_selected_suit = $suit;
        $this->current_bid_user = (($this->current_bid_user + 1) % $this->user_count);
        if ($this->users[$this->current_bid_user]->active === false)
            $this->current_bid_user = (($this->current_bid_user + 1) % $this->user_count);
        echo "Current bidder is user ".$this->current_bid_user."\n";
        echo "Pending suit is ".$this->pending_selected_suit."\n";
        echo "Current bid level is ".$this->current_bid_value."\n";
        $this->bid_count++;
        if ($this->bid_count >= 3){
            if ($this->current_bid_value === -1){
                $this->deal();
            }
        }
        return true;
    }
}
$room = array();

$socket_receive = function($socket, $data){
    global $room;
    echo "message received\n";
    try{
        if (is_object($data) === false)
            return;
        $user_room = $data->room;
        $user_id = $data->id;
        $user_name = $data->name;
        $user_message = $data->message;
        $msgip = '';
        socket_getpeername($socket,$msgip);
        // Create room if necessary
        if (array_key_exists($user_room,$room) === false){
            $room[$user_room] = new Room;
        }
        // User requests to join
        if ($user_message === '::J'){
            $user_id = $room[$user_room]->add_user($user_name, $socket);
            if ($user_id === -1){
                send_message($socket,json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>'This room is full and cannot be joined.')));
            }
            else {
                echo "User joined in slot $user_id\n";
                for ($i = 0; $i < 4; $i++){
                    if ($room[$user_room]->users[$i]->active === true){
                        if ($i !== $user_id)
                            send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>($user_name.' has joined.'))));
                        send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                    }
                }
            }
        }
        // Shut down game (for testing)
        else if ($user_message === '::Q'){
            socket_close($socket);
            exit;
        }
        // User requested data from the server
        else if ($user_message === '::R'){
            if (isset($room[$user_room]) && isset($room[$user_room]->users[$user_id])){
                $response_text = json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$user_id.'","u":"'.($room[$user_room]->users[$user_id]->data()).'","r":"'.($room[$user_room]->data()).'"}')));
                send_message($room[$user_room]->users[$user_id]->socket,$response_text);
            }
        }
        // User is attempting to place a bid
        else if (substr($user_message,0,3) === "::B"){                   
            if (strlen($user_message) > 3){
                $bid_placed = false;
                switch (substr($user_message,3)){
                    case '1':
                        $b_msg = $user_name.' selected Frog.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 0, 3);
                        break;
                    case '2':
                        $b_msg = $user_name.' selected Solo.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 2, 0);
                        break;
                    case '3':
                        $b_msg = $user_name.' selected Solo.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 2, 1);
                        break;
                    case '4':
                        $b_msg = $user_name.' selected Solo.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 2, 2);
                        break;
                    case '5':
                        $b_msg = $user_name.' selected Heart Solo.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 3, 3);
                        break;
                    case '6':
                        $b_msg = $user_name.' selected Misere.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 1, -1);
                        break;
                    case '7':
                        break; // NOT IMPLEMENTED
                        $b_msg = $user_name.' selected Spread Misere.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, 4, -1);
                        break;
                    default:
                        $b_msg = $user_name.' passed.';
                        $bid_placed = $room[$user_room]->place_bid($user_id, -1, -1);
                        break;
                }
                if ($bid_placed === true){
                    $response_text = json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>$b_msg));
                    for ($i = 0; $i < 4; $i++){
                        if ($room[$user_room]->users[$i]->active === true){
                            send_message($room[$user_room]->users[$i]->socket,$response_text);
                            send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                        }
                    }
                }
            }
        }
        // User is attempting to play a card
        else if (substr($user_message,0,3) === "::C"){
            if (strlen($user_message) > 3){
                $room[$user_room]->played($user_id,(int)substr($user_message,3));
                for ($i = 0; $i < 4; $i++){
                    if ($room[$user_room]->users[$i]->active === true)
                        send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                }
            }
        }
        // No command code sent
        else if ($user_name !== NULL && $user_room !== NULL){
            $response_text = json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>$user_name, 'message'=>$user_message));
            for ($i = 0; $i < 4; $i++){
                if ($room[$user_room]->users[$i]->active === true)
                    send_message($room[$user_room]->users[$i]->socket,$response_text);
            }
        }
    } catch (Exception $e){ }
};
$socket_connect = function($socket){
    echo "user connected\n";
};
$socket_disconnect = function($socket){
    echo "user disconnected\n";
    for ($i = 0; $i < count($room); $i++){
        remove_user($socket);
    }
};
/// TEST:
$room[0] = new Room;
echo $room[0]->data()."\n";
$room[0]->add_user("user1", null);
$room[0]->add_user("user2", null);
$room[0]->add_user("user3", null);
$room[0]->add_user("user4", null);
$room[0]->deal();
echo $room[0]->user_count."\n";
echo $room[0]->data()."\n";
echo $room[0]->users[0]->data()."\n";
echo $room[0]->users[1]->data()."\n";
echo $room[0]->users[2]->data()."\n";
echo $room[0]->users[3]->data()."\n";
$room[0]->place_bid(0, 0, 3);
$room[0]->place_bid(1, -1, 0);
$room[0]->place_bid(2, 2, 1);
$room[0]->place_bid(3, -1, 0);
echo $room[0]->data()."\n";
echo $room[0]->users[0]->data()."\n";
echo $room[0]->users[1]->data()."\n";
echo $room[0]->users[2]->data()."\n";
echo $room[0]->users[3]->data()."\n";
//socket_start();