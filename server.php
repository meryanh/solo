<?php
include 'sockets.php';
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
    public $mode = 0;     // -1=none,frog,Misere,solo,hsolo,sMisere
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
    public function deal($rotate = true){
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
        else if ($rotate === true)
            $this->next_id = ($this->next_id + 1)%3;
        $this->next_bid = $this->next_id;
        for ($i = 0; $i < $user_count; $i++)
            $this->user[$i]->cards = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        $this->round_score = 0;
        $next = ($this->dealer + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand(0, count($deck)-1);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        $next = ($next + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand(0, count($deck)-1);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        $next = ($next + 1)%$user_count;
        for ($i = 0; $i < 11; $i++) {
            $rnd = rand(0, count($deck)-1);
            $this->user[$next]->cards[$i] = $deck[$rnd];
            array_splice($deck, $rnd, 1);
        }
        rsort($this->user[$next]->cards, SORT_REGULAR);
        for ($i = 0; $i < 3; $i++) {
            $rnd = rand(0, count($deck)-1);
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
        if ($this->next_bid !== $user_id)
            return;
        $this->bid_count++;
        echo ($this->selected_id)."\n";
        if ($user_count > 3){
            $this->next_bid = ($this->next_bid + 1)%4;
            if ($this->next_bid === $this->dealer)
                $this->next_bid = ($this->next_bid + 1)%4;
        }
        else
            $this->next_bid = ($this->next_bid + 1)%3;
        if ($this->bid_count >= 3 && $this->mode === -1){
            $this->bid_count = 0;
            $this->deal(false);
        }
        if ($this->mode < $value){
            $this->mode = $value;
            $this->selected_id = $user_id;
            $this->next_id = $user_id;
            $this->selected_id = $user_id;
            $this->pending_suit_id = $suit;
            if ($this->bid_count >= 3){
                $this->bid_count = 0;
                $this->ready = 1;
                $this->next_bid = -1;
                $this->lead_id = $user_id;
                $this->suit_id = $this->pending_suit_id;
                return;
            }
        }
    }
}
$room = array();