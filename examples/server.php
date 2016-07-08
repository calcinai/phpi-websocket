<?php
/**
 * @package    calcinai/phpi
 * @author     Michael Calcinai <michael@calcin.ai>
 */

include __DIR__.'/../vendor/autoload.php';

use Calcinai\PHPi\Board;
use Calcinai\PHPi\Pin\PinFunction;
use Ratchet\ConnectionInterface;

//The actual WS construction is a bit messy, but it shows the general idea.
$loop = \React\EventLoop\Factory::create();
$board = \Calcinai\PHPi\Factory::create($loop);

$http_server = new ForExampleOnlyHTTPServer(__DIR__.'/client.html');
$controller = new RatchetEventBridge();

//This is like a vhost, if it donesn't match the host header you'll get a 404
$app = new Ratchet\App('raspberrypi.local', 9999, '0.0.0.0', $loop);
//Route the root to serve the html file
$app->route('/', $http_server, ['*']);
//Route to ws server
$app->route('/phpi', $controller, ['*']);



//Some sort of visible heartbeat.  No functional use - display only.
$loop->addPeriodicTimer(1, function() use($controller){
    $controller->broadcast('time', date('r'));
});




//The following are the events triggered by the client:
/**
 * Send the pins and their functions on connect
 * Also send board meta
 */
$controller->on('client.connect', function(ConnectionInterface $connection) use($controller, $board) {

    //Prepare the pins in a useful format for the client
    $headers = $board->getPhysicalPins();
    foreach($headers as &$header){
        foreach($header as $pin_number => &$physical_pin){
            if($physical_pin->gpio_number !== null){
                $pin = $board->getPin($physical_pin->gpio_number);
                $physical_pin->function = $pin->getFunction();
                $physical_pin->alternate_functions = $pin->getAltFunctions();
                $physical_pin->level = $pin->getLevel();
            }
        }
    }

    $controller->send($connection, 'board.headers', $headers);
    $controller->send($connection, 'board.meta', $board->getMeta());
});


/**
 * Handle pin function changes from the client.
 */
$controller->on('pin.function', function($data) use($board) {
    $board->getPin($data['pin'])->setFunction($data['func']);
});


/**
 * Handle pin level changes from the client.
 * The pin is automatically set to output if it isn't already.
 */
$controller->on('pin.level', function($data) use($board) {
    $pin = $board->getPin($data['pin']);

    //Just to help out!
    if($pin->getFunction() !== PinFunction::OUTPUT){
        $pin->setFunction(PinFunction::OUTPUT);
    }

    $data['level'] ? $pin->high() : $pin->low();
});






//Set up the update events server -> client
//This is a bit of a duplicate of above but it's for clarity as an example.
$headers = $board->getPhysicalPins();
foreach($headers as $header) {
    foreach($header as $pin_number => $physical_pin) {
        if($physical_pin->gpio_number !== null) {
            $pin = $board->getPin($physical_pin->gpio_number);

            //Need to add a callback on these pins to the client too for level and function changes.
            $pin->on(\Calcinai\PHPi\Pin::EVENT_FUNCTION_CHANGE, function($new_function) use($pin, $controller){
                $controller->broadcast('pin.function', ['pin' => $pin->getPinNumber(), 'func' => $new_function]);
            });

            $pin->on(\Calcinai\PHPi\Pin::EVENT_LEVEL_CHANGE, function() use($pin, $controller){
                $controller->broadcast('pin.level', ['pin' => $pin->getPinNumber(), 'level' => $pin->getLevel()]);
            });
        }
    }
}

$app->run();
