<?php

/**
 * @package    phpi-websocket
 * @author     Michael Calcinai <michael@calcin.ai>
 */
class RatchetEventBridge implements \Ratchet\MessageComponentInterface {

    use \Evenement\EventEmitterTrait;

    /**
     * @var SplObjectStorage
     */
    private $connections;


    public function __construct() {
        $this->connections = new SplObjectStorage();
    }


    /**
     * When a new connection is opened it will be passed to this method
     * @param  \Ratchet\ConnectionInterface $connection The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(\Ratchet\ConnectionInterface $connection) {

        //Do some checking here to check authorization


        $this->connections->attach($connection);
        $this->emit('client.connect', [$connection]);
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $connection will not result in an error if it has already been closed.
     * @param  \Ratchet\ConnectionInterface $connection The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(\Ratchet\ConnectionInterface $connection) {
        $this->emit('client.close', [$connection]);
        $this->connections->detach($connection);
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  \Ratchet\ConnectionInterface $connection
     * @param  \Exception $e
     * @throws \Exception
     */
    function onError(\Ratchet\ConnectionInterface $connection, \Exception $e) {
        $this->emit('client.error', [$connection]);
        $this->connections->detach($connection);
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $message The message received
     * @throws \Exception
     */
    function onMessage(\Ratchet\ConnectionInterface $from, $message) {
        $components = @json_decode($message, true);

        if($message === false || !isset($components['event'])){
            throw new \Exception('Bad message payload received');
        }

        $this->emit($components['event'], [$components['data'], $from]);

    }

    public function send(\Ratchet\ConnectionInterface $to, $event, $data = null){
        $to->send(json_encode([
            'event' => $event,
            'data' => $data
        ]));
    }

    public function broadcast($event, $data){
        foreach($this->connections as $connection){
            $this->send($connection, $event, $data);
        }
    }
}