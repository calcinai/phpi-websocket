# phpi-websocket
Scaffolding and demo for interacting with [phpi](https://github.com/calcinai/phpi) via websockets.

This should be enough to get started with a WS server and client. The included demo will let you monitor the pin headers, select alternate 
functions and change pin levels.

## Instalation

Installation should be done using composer as there are a few dependencies (especially for ratchet - sorry about this but they are all just tiny symphony components).

If you don't have composer already:

```$ curl -sS https://getcomposer.org/installer | php```

Then install

```$ ./composer.phar create-project calcinai/phpi-websocket```

## Usage

You can test the server by running 

```php examples/server.php```

This will run a simple HTTP & WebSocket server on port 9999.  If the hostname of the pi is not the default (raspberrypi.local), you'll need 
to set it to something that can be accessed in `server.php`

All going well, you should be able to access the example at http://raspberrypi.local:9999/ and see something like this:

![Screenshot](/../screenshots/phpi-websocket-pi3.png/?raw=true)
