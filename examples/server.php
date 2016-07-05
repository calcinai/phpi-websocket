<?php
/**
 * @package    calcinai/phpi
 * @author     Michael Calcinai <michael@calcin.ai>
 */

include __DIR__.'/../vendor/autoload.php';

use Calcinai\PHPi\Board;


//The actual WS construction is a bit messy, but it shows the general idea.
$loop = \React\EventLoop\Factory::create();
//$board = \Calcinai\PHPi\Factory::create($loop);

$controller = new RatchetEventBridge();

//This is like a vhost, if it donesn't match the host header you'll get a 404
$app = new Ratchet\App('raspberrypi.local', 9999, '0.0.0.0', $loop);
$app->route('/phpi', $controller, ['*']);



$loop->addPeriodicTimer(1, function() use($controller){
    $controller->broadcast('time', date('r'));
});



$controller->on('led.level', function($level) {
    echo $level;
});





$app->run();