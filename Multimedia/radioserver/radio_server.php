<?php


error_reporting(~E_WARNING);

require_once("../vendor/autoload.php");
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);





//Create socket
if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0)))
{
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);

    die("Couldn't create socket: [$errorcode] $errormsg \n");
}
echo "Socket created \n";

// Bind the source address
if( !socket_bind($sock, "0.0.0.0" , 9999) )
{
    $errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);

    die("Could not bind socket : [$errorcode] $errormsg \n");
}
echo "Socket bind OK \n";


//Voithitiki klasi gia apothikefsi timon
class Store{
    public static $cnt=0;
    public static $msg="[";
}






//Συναρτηση που τρεχει επαναλαμβανόμενα και λαμβάνει μηνυματα απο το socket και τα στελνει στο web socket
$loop->addPeriodicTimer(0, function() use($server, $logger,$sock){
    //μεγεθος του επόμενου πακετου που θα παραληφθεί απο το socket
    $r = socket_recvfrom($sock, $num, 5, 0, $remote_ip, $remote_port);
    $len=  intval($num);
    
    
    $r = socket_recvfrom($sock, $buf, $len, 0, $remote_ip, $remote_port);
    
    Store::$cnt+=1;
    
    $buf2=explode("","ss");
    
    if(!strcmp(substr( $buf, 0, 1 ),"!"))
    {
        $buf2= explode("##",$buf);
        $buf=$buf2[1];
    }
    
    Store::$msg.=$buf;
    
    if(Store::$cnt==172){

        if(!strcmp(substr( $buf2[0], 0, 1 ),"!"))
            Store::$msg=$buf2[0]."##".Store::$msg;
        
        
        Store::$msg=rtrim(Store::$msg, ",");
        Store::$msg.="]";

        Store::$cnt=0;

        $logger->notice("Broadcasting audio to all clients".strlen(Store::$msg));
        foreach ($server->getConnections() as $client) 
        {
            $client->sendString(Store::$msg);
        }

        Store::$msg="[";
    }
});

// Bind the server
$server->bind();


//socket_close($sock);



// Start the event loop
$loop->run();
