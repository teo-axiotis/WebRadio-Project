#!/php -q
<?php

// Set timezone of script to UTC inorder to avoid DateTime warnings in
// vendor/zendframework/zend-log/Zend/Log/Logger.php
date_default_timezone_set('UTC');

require_once("../vendor/autoload.php");

// Run from command prompt > php chat.php
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Server\IWebSocketServerObserver;
use Devristo\Phpws\Server\UriHandler\WebSocketUriHandler;
use Devristo\Phpws\Server\WebSocketServer;



/**
 * This ChatHandler handler below will respond to all messages sent to /chat (e.g. ws://localhost:12345/chat)
 */
class ChatHandler extends WebSocketUriHandler {
    
    var $sock;
    var $remote_ip;
    var $remote_port;
    var $userID=0;

    public function __construct($logger) {
        parent::__construct($logger);
        if(!($this->sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);

            die("Couldn't create socket: [$errorcode] $errormsg \n");
        }
        echo "Socket created \n";

        // Bind the source address
        if( !socket_bind($this->sock, "localhost" , 9998) )
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);

            die("Could not bind socket : [$errorcode] $errormsg \n");
        }
        echo "Socket bind OK \n";
    }
    /**
     * Notify everyone when a user has joined the chat
     *
     * @param WebSocketTransportInterface $user
     */
    public function onConnect(WebSocketTransportInterface $user){
        $user->setId($this->userID);
        $this->userID++;
        foreach($this->getConnections() as $client){
            $client->sendString("User{$user->getId()} joined the chat: ");
        }
    }

    /**
     * Broadcast messages sent by a user to everyone in the room
     *
     * @param WebSocketTransportInterface $user
     * @param WebSocketMessageInterface $msg
     */
    public function onMessage(WebSocketTransportInterface $user, WebSocketMessageInterface $msg) {
        $this->logger->notice("Broadcasting " . strlen($msg->getData()) . " bytes");
        
        $msg2="User{$user->getId()} said: ".$msg->getData();
        
        echo strval(strlen($msg2));
        $r = socket_sendto($this->sock, strval(strlen($msg2)), 2, 0, "192.168.1.2", "8785");
        
        $r = socket_sendto($this->sock, $msg2, strlen($msg2), 0, "192.168.1.2", "8785");
        
        foreach($this->getConnections() as $client){
            $client->sendString($msg2);
        }
    }
}
class ChatHandlerForUnroutedUrls extends WebSocketUriHandler {
    /**
     * This class deals with users who are not routed
     */
    public function onConnect(WebSocketTransportInterface $user){
		//do nothing
		$this->logger->notice("User {$user->getId()} did not join any room");
    }
    public function onMessage(WebSocketTransportInterface $user, WebSocketMessageInterface $msg) {
    	//do nothing
        $this->logger->notice("User {$user->getId()} is not in a room but tried to say: {$msg->getData()}");
    }
}


$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server
$server = new WebSocketServer("tcp://0.0.0.0:12346", $loop, $logger);

// Create a router which transfers all /chat connections to the ChatHandler class
$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
// route /chat url
$chatH=new ChatHandler($logger);
$router->addRoute('#^/chat$#i', $chatH);
// route unmatched urls durring this demo to avoid errors
$router->addRoute('#^(.*)$#i', new ChatHandlerForUnroutedUrls($logger));

// Bind the server
$server->bind();

// Start the event loop
$loop->run();

?>
