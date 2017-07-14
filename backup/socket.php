<?php
set_time_limit(0);
$socket_receive = function($socket, $data){};
$socket_connect = function($socket){};
$socket_disconnect = function($socket){};
function get_ip($socket){
    $msgip = '';
    socket_getpeername($socket,$msgip);
    return $msgip;
}
function handle_message($socket, $data){
    global $socket_receive;
    $socket_receive($socket, json_decode(unmask($data)));
    return;
}
function handle_connect($socket){
    global $socket_connect;
    $socket_connect($socket);
    return;
}
function handle_disconnect($socket){
    global $socket_disconnect;
    $socket_disconnect($socket);
    return;
}
function send_message($socket, $data){
    echo "$data\n";
    $response = mask($data);
    @socket_write($socket,$response,strlen($response));
    return true;
}
function unmask($text){
    $length = ord($text[1]) & 127;
    if($length == 126){
        $masks = substr($text, 4, 4);
        $data = substr($text, 8);
    }
    elseif($length == 127){
        $masks = substr($text, 10, 4);
        $data = substr($text, 14);
    }
    else {
        $masks = substr($text, 2, 4);
        $data = substr($text, 6);
    }
    $text = '';
    for ($i = 0; $i < strlen($data); ++$i){
        $text .= $data[$i] ^ $masks[$i%4];
    }
    return $text;
}
function mask($text){
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
function perform_handshaking($receved_header,$client_conn, $host, $port){
    $headers = array();
    $lines = preg_split("/\r\n/", $receved_header);
    foreach($lines as $line){
        $line = chop($line);
        if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
            $headers[$matches[1]] = $matches[2];
        }
    }

    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n".
    "Upgrade: websocket\r\n".
    "Connection: Upgrade\r\n".
    "WebSocket-Origin: $host\r\n".
    "WebSocket-Location: ws://$host:$port/demo/shout.php\r\n".
    "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
    socket_write($client_conn,$upgrade,strlen($upgrade));
}
function socket_start(){
    echo "socket started\n";
    $host = 'localhost';
    $port = '9000';
    $null = NULL;
    $connection_count = 0;
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
    socket_bind($socket, 0, $port);
    socket_listen($socket);
    $clients = array($socket);
    while (true){
        $changed = $clients;
        socket_select($changed, $null, $null, 0, 10);
        if (in_array($socket, $changed)) {
            $socket_new = socket_accept($socket);
            $clients[] = $socket_new;
            $header = socket_read($socket_new, 1024);
            perform_handshaking($header, $socket_new, $host, $port);
            $found_socket = array_search($socket, $changed);
            $connection_count++;
            handle_connect($socket_new);
            unset($changed[$found_socket]);
        }
        foreach ($changed as $changed_socket){
            while(socket_recv($changed_socket, $buf, 1024, 0) >= 1){
                try {
                    handle_message($changed_socket, $buf);
                    break 2;
                } catch (Exception $e){
                    break 1;
                }
            }
            $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
            if ($buf === false){
                $connection_count--;
                if ($connection_count < 1)
                    return;
                handle_disconnect($changed_socket);
                $found_socket = array_search($changed_socket, $clients);
                unset($clients[$found_socket]);
            }
        }
    }
}
?>