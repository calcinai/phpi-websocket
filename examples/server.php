<?php
/**
 * @package    calcinai/phpi
 * @author     Michael Calcinai <michael@calcin.ai>
 */

include __DIR__.'/../vendor/autoload.php';

use Calcinai\PHPi\Board;

use Ratchet\ConnectionInterface;


/**
 * This is noting more than a proof of concept.  It's very untidy and not well structured for an actual app.
 *
 * On the list to improve!
 *
 * Class PHPiWSInterface
 */
class PHPiWSInterface implements \Ratchet\MessageComponentInterface {

    private $connections;
    private $board;

    private $led;

    public function __construct(Board $board) {
        $this->connections = new \SplObjectStorage();
        $this->board = $board;

        $this->board->getLoop()->addPeriodicTimer(1, [$this, 'broadcastTime']);

        $this->led = new \Calcinai\PHPi\External\LED($board->getPin(18));

        $button = new \Calcinai\PHPi\External\Button($board->getPin(17));

        $button->on('press', function(){
            $this->broadcastMessage('log', 'Button Pressed');
        });

        $button->on('release', function(){
            $this->broadcastMessage('log', 'Button Released');
        });
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $connection The socket/connection that just connected to your application
     */
    public function onOpen(ConnectionInterface $connection) {

        /** @var \Guzzle\Http\Message\Request $request */
        /** @noinspection PhpUndefinedFieldInspection */
        $request = $connection->WebSocket->request;

        if($request->hasHeader('Authorization')){
            //Here's a way you can check the user/pass
            list($username, $password) = explode(':', $request->getHeader('Authorization'));

            //Throw an exception if it's not valid..
        }

        $this->connections->attach($connection);
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $connection will not result in an error if it has already been closed.
     * @param  ConnectionInterface $connection The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $connection) {
        $connection->close();
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $connection
     * @param  \Exception $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $connection, \Exception $e) {
        $connection->close();
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $message) {
        $data = json_decode($message);

        switch($data->command){
            case 'on':
                $this->led->on();
                break;
            case 'off':
                $this->led->off();
                break;
        }
    }




    public function broadcastTime(){
        $this->broadcastMessage('time', date('r'));
    }


    public function broadcastMessage($command, $data){
        foreach($this->connections as $connection){
            $this->sendMessage($connection, $command, $data);
        }
    }

    public function sendMessage(ConnectionInterface $to, $command, $data){
        $to->send(json_encode([
            'command' => $command,
            'data' => $data
        ]));
    }


}



//The actual WS construction is a bit messy, but it shows the general idea.
$loop = \React\EventLoop\Factory::create();
$board = \Calcinai\PHPi\Factory::create($loop);

//This is like a vhost, if it donesn't match the host header you'll get a 404
$app = new Ratchet\App('raspberrypi.local', 8080, '0.0.0.0', $loop);
$app->route('/phpi', new PHPiWSInterface($board), ['*']);

$app->run();