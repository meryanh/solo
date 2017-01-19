<?php
include 'socket.php';
class User {
    public $cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    public $points = 50;
    public $name = '';
    public $bid_level = -1;
    public $active = false;
    public $ready = false;
    public $address = '';
    public $socket = null;
    
    // Get the user data as a comma-separated array
    function data(){
        $msg = $this->points;
        for ($i = 0; $i < 14; $i++){
            $msg = $msg.','.$this->cards[$i];
        }
        return $msg;
    }
}
class Room {
    public $locked = false;                 // Whether or not users are permitted to join the game
    public $users = null;                   // Array of users (initialized when first user added)
    public $user_count = 0;                 // The number of connected users (max 4)
    public $starting_users = 0;             // The number of connected users when the game started
    public $current_user = -1;              // User ID of next user to play
    public $selected_user = -1;             // User ID of user to win bid
    public $dealer = 3;                     // User ID of the dealer (default 3)
    public $down = array(0,0,0);            // The three cards in the "down" pile
    public $played = array(0,0,0);          // ID of each played card
    public $round_score = 0;                // Value of cards won by the selected user this round
    public $user_played_id = array(0,0,0);  // ID of player for each played card
    public $mode = -1;                      // -1=waiting, 0=bidding, 1=playing, 2=game over (restart)
    public $bid_count = 0;                  // Count of users that have placed a bid
    public $current_bid_user = -1;          // User ID of next user to bid
    public $current_bid_value = -1;         // ID of currently winning bid type
    public $selected_suit = -1;             // Suit used as trump in play (-1=none, 0=spade, 1=club, 2=diamond, 3=heart)
    public $pending_selected_suit = -1;     // Suit to be used as trump if the current highest bid succeeds
    
    // Get the room data as a comma-separated array
    public function data(){
        if ($this->users == null){
            $this->users = array();
            $this->users[0] = new User;
            $this->users[1] = new User;
            $this->users[2] = new User;
            $this->users[3] = new User;
        }
        $msg = $this->mode.','.$this->selected_user.','.$this->current_user.','.$this->current_bid_user.','.$this->current_bid_value.','.$this->selected_suit;
        for ($i = 0; $i < 3; $i++){
            $msg = $msg.','.$this->played[$i];
        }
        for ($i = 0; $i < 4; $i++){
            $msg = $msg.','.$this->users[$i]->name;
        }
        for ($i = 0; $i < 3; $i++){
            $msg = $msg.','.$this->user_played_id[$i];
        }
        return $msg;
    }
    
    // Try adding a user to this room
    function add_user($user_name, $socket){
        if ($this->user_count >= 4 || ($this->locked == true && ($this->starting_users == $this->user_count)))
            return -1;
        if ($this->users == null){
            $this->users = array();
            $this->users[0] = new User;
            $this->users[1] = new User;
            $this->users[2] = new User;
            $this->users[3] = new User;
        }
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->active == false){
                $this->users[$i]->socket = $socket;
                $this->users[$i]->name = $user_name;
                $this->users[$i]->active = true;
                //$this->users[$i]->address = get_ip($socket);
                $this->user_count++;
                return $i;
            }
        }
        return -1;
    }
    
    // Mark the user as "ready"
    function user_ready($user_id){
        $this->users[$user_id]->ready = true;
        if ($this->user_count >= 3){
            $ready = true;
            for ($i = 0; $i < 4; $i++){
                if ($this->users[$i]->ready == false && $this->users[$i]->active == true){
                    $ready = false;
                }
            }
            if ($ready == true){
                $this->mode = 0;
                $this->starting_users = $this->user_count;
                $this->locked = true;
                $this->deal();
            }
        }
    }
    
    // Remove a user that has disconnected
    function remove_user($socket){
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->socket == $socket){
                $this->users[$i]->active = false;
                $this->user_count--;
                return;
            }
        }
    }
    
    // Distribute the cards to the players based on traditional rules
    function deal(){
        if ($this->mode != 0 || $this->user_count < 3)
            return;
            $first_bid = ($this->dealer + 1) % $this->user_count;
        if (($first_bid == $this->dealer && $this->user_count > 3) || $this->users[$first_bid]->active == false)
            $first_bid = ($this->dealer + 1) % $this->user_count;
        $this->pending_selected_suit = -1;
        for ($i = 0; $i < 4; $i++){
            $this->users[$i]->bid_level = -1;
            $this->users[$i]->cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        }
        $this->down = array(0,0,0);
        $this->played = array(0,0,0);
        $this->user_played_id = array(0,0,0);
        $deck = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,32,32,33,34,35,36);
        for ($i = 0; $i < 4; $i++){
            if ($i == $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active != false){
                for ($j = 0; $j < 4; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
        }
        for ($i = 0; $i < 4; $i++){
            if ($i == $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active != false){
                for ($j = 4; $j < 7; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
        }
        for ($i = 0; $i < 3; $i++){
            $rnd = rand(0, count($deck)-1);
            $this->down[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        for ($i = 0; $i < 4; $i++){
            if ($i == $this->dealer && $this->user_count > 3)
                continue;
            if ($this->users[$i]->active != false){
                for ($j = 7; $j < 11; $j++){
                    $rnd = rand(0, count($deck)-1);
                    $this->users[$i]->cards[$j] = $deck[$rnd];
                    array_splice($deck, $rnd, 1);
                }
            }
        }
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->active != false){
                rsort($this->users[$i]->cards, SORT_REGULAR);
            }
        }
        $this->bid_count = 0;
        $this->current_bid_user = $first_bid;
        $this->selected_suit = -1;
        $this->pending_selected_suit = -1;
        $this->mode = 0;
    }
    
    // Dermine if a user is capable of playing a card
    public function is_playable($user_id, $card_id){ 
        if ($user_id != $this->current_user ||
            $this->mode != 1 ||
            in_array($card_id, $this->users[$user_id]->cards) == false ||
            $card_id == 0)
            return false;
        if ($this->played[0] == 0)
            return true;
        $suit = floor(($card_id-1)/9);
        $played_suit = floor((($this->played[0])-1)/9);
        if ($suit == $played_suit)
            return true;
        $has_trump = false;
        $has_match = false;
        $check_suit = -1;
        for ($i = 0; $i < 14; $i++){
            if ($this->users[$user_id]->cards[$i] == $card_id)
                continue;
            $in_hand_suit = floor(($this->users[$user_id]->cards[$i]-1)/9);
            if ($in_hand_suit == $played_suit)
                $has_match = true;
            if ($in_hand_suit == $this->selected_suit)
                $has_trump = true;
        }
        if ($suit == $this->selected_suit && $has_match == false)
            return true;
        if ($suit != $this->selected_suit && $has_trump == false && $has_match == false)
            return true;
        return false;
    }
    
    // Handle the "down" cards based on the winning bid level
    public function give_down(){ 
        if ($this->down[0] == 0 ||
            $this->down[1] == 0 ||
            $this->down[2] == 0 ||
            $this->mode != 0) 
            return; 
        if ($this->current_bid_value == 0){ // Frog
            for ($i = 0; $i < 3; $i++) 
                $this->users[$this->selected_user]->cards[$i+11] = $this->down[$i]; 
            rsort($this->users[$this->selected_user]->cards, SORT_REGULAR);
            $this->down = array(0,0,0);
        }
        else if ($this->current_bid_value == 1){ // Misere
            $this->down = array(0,0,0);
        }
        else if ($this->current_bid_value == 4){ // Spread Misere
            $this->down = array(0,0,0);
        }
        else if ($this->current_bid_value > 1 && $this->current_bid_value < 4){ // Solo
            for ($i = 0; $i < 3; $i++) 
                $this->round_score += $this->card_to_score($this->down[$i]);
            $this->down = array(0,0,0);
        }
    }
    
    // Convert a card to its score value
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
    
    function remove_card($user_id, $card_id){
        for ($i = 0; $i < 14; $i++){
            if ($this->users[$user_id]->cards[$i] == $card_id){
                $this->users[$user_id]->cards[$i] = 0;
                break;
            }
        }
    }
    
    // Handle user playing a card
    function played_card($user_id, $card_id){
        if ($user_id != $this->current_user || in_array($card_id, $this->users[$user_id]->cards) == false)
            return;
        if ($user_id == $this->selected_user && count($this->users[$user_id]->cards) > 11){
            $this->round_score += $this->card_to_score($card_id);
            $this->remove_card($user_id, $card_id);
            return;
        }
        if ($this->is_playable($user_id, $card_id) != true)
            return;
        if ($this->played[0] == 0){
            $this->played[0] = $card_id;
            $this->remove_card($user_id, $card_id);
            // Move to next player
            return;
        }
        if ($this->played[1] == 0){
            $this->played[1] = $card_id;
            $this->remove_card($user_id, $card_id);
            // Move to next player
            return;
        }
        if ($this->played[2] == 0){
            $this->played[2] = $card_id;
            $this->remove_card($user_id, $card_id);
            // Add score to winning side
            // Set winner as next player
            // Clear played cards
            // If everyone is out of cards...
            //     Rotate dealer
            //     Re-deal
            //     Set mode to 0
            return;
        }
    }
    
    // Rotate the dealer role to the next user
    function rotate_dealer(){
        if ($this->user_count < 3)
            return;
        $this->dealer = ($this->dealer + 1) % $this->user_count;
        if ($this->users[$this->dealer]->active == false)
            $this->dealer = ($this->dealer + 1) % $this->user_count;
    }
    
    // Attempt to set the bid level and suit for a user
    public function place_bid($user_id, $value, $suit){
        if ($this->mode != 0 ||
            $this->user_count < 3 ||
            ($user_id == $this->dealer && $this->user_count > 3) || 
            $user_id != $this->current_bid_user ||
            $user_id > 3 || 
            $this->users[$user_id]->bid_level != -1)
            return false;
        $this->users[$user_id]->bid_level = $value;
        $this->current_bid_user = (($this->current_bid_user + 1) % $this->user_count);
        if (($this->current_bid_user == $this->dealer && $this->user_count > 3) || $this->users[$this->current_bid_user]->active == false)
            $this->current_bid_user = (($this->current_bid_user + 1) % $this->user_count);
        $is_greater = true;
        for ($i = 0; $i < 4; $i++){
            if ($this->users[$i]->bid_level > $value){
                $is_greater = false;
            }
        }
        if ($is_greater == true){
            $this->pending_selected_suit = $suit;
            $this->current_bid_value = $value;
            $this->current_user = $user_id;
            $this->selected_user = $user_id;
        }
        $this->bid_count++;
        if ($this->bid_count >= 3){
            if ($this->current_bid_value == -1){
                $this->deal();
            }
            else{
                $this->selected_suit = $this->pending_selected_suit;
                $this->give_down();
                $this->mode = 1;
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
        if (is_object($data) == false)
            return;
        $user_room = $data->room;
        $user_id = $data->id;
        $user_name = $data->name;
        $user_message = $data->message;
        $msgip = '';
        socket_getpeername($socket,$msgip);
        // Create room if necessary
        if (array_key_exists($user_room,$room) == false){
            $room[$user_room] = new Room;
        }
        // User requests to join
        if ($user_message == '::J'){
            $user_id = $room[$user_room]->add_user($user_name, $socket);
            if ($user_id == -1){
                send_message($socket,json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>'This room is full and cannot be joined.')));
            }
            else {
                echo "User joined in slot $user_id\n";
                for ($i = 0; $i < 4; $i++){
                    if ($room[$user_room]->users[$i]->active == true){
                        if ($i != $user_id)
                            send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>($user_name.' has joined.'))));
                        send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                    }
                }
            }
        }
        // Shut down game (for testing)
        else if ($user_message == '::Q'){
            socket_close($socket);
            exit;
        }
        // User requested data from the server
        else if ($user_message == '::I'){
            if (isset($room[$user_room]) && isset($room[$user_room]->users[$user_id])){
                $response_text = json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$user_id.'","u":"'.($room[$user_room]->users[$user_id]->data()).'","r":"'.($room[$user_room]->data()).'"}')));
                send_message($room[$user_room]->users[$user_id]->socket,$response_text);
            }
        }
        // User is attempting to place a bid
        else if (substr($user_message,0,3) == "::B"){                   
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
                if ($bid_placed == true){
                    $response_text = json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>'Server', 'message'=>$b_msg));
                    for ($i = 0; $i < 4; $i++){
                        if ($room[$user_room]->users[$i]->active == true){
                            send_message($room[$user_room]->users[$i]->socket,$response_text);
                            send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                        }
                    }
                }
            }
        }
        // User is ready to play
        else if ($user_message == "::R"){
            $room[$user_room]->user_ready($user_id);
        }
        // User is attempting to play a card
        else if (substr($user_message,0,3) == "::C"){
            if (strlen($user_message) > 3){
                $room[$user_room]->played_card($user_id,(int)substr($user_message,3));
                for ($i = 0; $i < 4; $i++){
                    if ($room[$user_room]->users[$i]->active == true)
                        send_message($room[$user_room]->users[$i]->socket,json_encode(array('type'=>'data', 'room'=>$user_room, 'name'=>'Server', 'message'=>('{"id":"'.$i.'","u":"'.($room[$user_room]->users[$i]->data()).'","r":"'.($room[$user_room]->data()).'"}'))));
                }
            }
        }
        // No command code sent
        else if ($user_name != NULL && $user_room != NULL){
            $response_text = json_encode(array('type'=>'usermsg', 'room'=>$user_room, 'name'=>$user_name, 'message'=>$user_message));
            for ($i = 0; $i < 4; $i++){
                if ($room[$user_room]->users[$i]->active == true)
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
//socket_start();

/// TEST: (delete when done using)
function card_symbol($card_id){
    if ($card_id == 0)
        return '0X';
    $suit = floor(($card_id-1)/9);
    $value = ($card_id-1)%9;
    $str = '';
    switch ($value){
        case 0:
            $str = '6';
        break;
        case 1:
            $str = '7';
        break;
        case 2:
            $str = '8';
        break;
        case 3:
            $str = '9';
        break;
        case 4:
            $str = 'J';
        break;
        case 5:
            $str = 'Q';
        break;
        case 6:
            $str = 'K';
        break;
        case 7:
            $str = '10';
        break;
        case 8:
            $str = 'A';
        break;
    }
    switch ($suit){
        case 0:
            $str = $str.'S';
        break;
        case 1:
            $str = $str.'C';
        break;
        case 2:
            $str = $str.'D';
        break;
        case 3:
            $str = $str.'H';
        break;
    }
    return $str;
}
$room[0] = new Room;
echo $room[0]->data()."\n";
$room[0]->add_user("user1", null);
$room[0]->add_user("user2", null);
$room[0]->add_user("user3", null);
//$room[0]->add_user("user4", null);
$room[0]->user_ready(0);
$room[0]->user_ready(3);
$room[0]->user_ready(1);
$room[0]->user_ready(2);
//$room[0]->rotate_dealer();
//$room[0]->deal();
//$room[0]->users[3]->active = false;
echo $room[0]->data()."\n";
echo $room[0]->users[0]->data()."\n";
echo $room[0]->users[1]->data()."\n";
echo $room[0]->users[2]->data()."\n";
echo $room[0]->users[3]->data()."\n";
$room[0]->place_bid(0, 0, 3);
$room[0]->place_bid(0, 1, 3);
$room[0]->place_bid(1, -1, 0);
$room[0]->place_bid(0, 1, 3);
$room[0]->place_bid(1, 3, 0);
$room[0]->place_bid(2, 2, 1);
$room[0]->place_bid(1, 5, 0);
$room[0]->place_bid(2, 2, 1);
$room[0]->place_bid(3, 3, 3);
$room[0]->place_bid(0, 0, 3);
$room[0]->place_bid(1, -1, 0);
$room[0]->place_bid(2, 2, 1);
echo $room[0]->data()."\n";
echo $room[0]->users[0]->data()."\n";
echo $room[0]->users[1]->data()."\n";
echo $room[0]->users[2]->data()."\n";
echo $room[0]->users[3]->data()."\n";
echo $room[0]->current_user."\n";