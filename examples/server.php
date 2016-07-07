<?php
/**
 * @package    calcinai/phpi
 * @author     Michael Calcinai <michael@calcin.ai>
 */

include __DIR__.'/../vendor/autoload.php';

use Calcinai\PHPi\Board;
use Ratchet\ConnectionInterface;


//The actual WS construction is a bit messy, but it shows the general idea.
$loop = \React\EventLoop\Factory::create();
$board = \Calcinai\PHPi\Factory::create($loop);

$controller = new RatchetEventBridge();

//This is like a vhost, if it donesn't match the host header you'll get a 404
$app = new Ratchet\App('raspberrypi.local', 9999, '0.0.0.0', $loop);
$app->route('/phpi', $controller, ['*']);


//Some sort of visible heartbeat
$loop->addPeriodicTimer(1, function() use($controller){
    $controller->broadcast('time', date('r'));
});


//Send the pins and their functions on connect
$controller->on('client.connect', function(ConnectionInterface $connection) use($controller, $board) {

    //Prepare the pins in a useful format for the client
    $headers = $board->getPhysicalPins();
    foreach($headers as &$header){
        foreach($header as $pin_number => &$physical_pin){
            if($physical_pin->gpio_number !== null){
                $physical_pin->function = $board->getPin($physical_pin->gpio_number)->getFunction();
                $physical_pin->alternate_functions = $board->getPin($physical_pin->gpio_number)->getAltFunctions();
                $physical_pin->level = $board->getPin($physical_pin->gpio_number)->getLevel();
            }
        }
    }

    $controller->send($connection, 'headers.initialise', $headers);
});


$controller->on('pin.function', function($data) use($board) {
    $board->getPin($data['pin'])->setFunction($data['function']);
});

$controller->on('pin.level', function($data) use($board) {
    $pin = $board->getPin($data['pin']);
    $data['level'] ? $pin->high() : $pin->low();
});



$app->run();