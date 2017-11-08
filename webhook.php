<?php

require('vendor/autoload.php');
require('GlipBotman.php');


use Mpociot\BotMan\BotManFactory;
use Mpociot\BotMan\BotMan;
use Mpociot\BotMan\DriverManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GlipDriver\GlipBotman;


// Load the values from .env
$config = [
    'GLIP_SERVER' => getenv('GLIP_SERVER'),
    'GLIP_CLIENT_ID' => getenv('GLIP_CLIENT_ID'),
    'GLIP_CLIENT_SECRET' => getenv('GLIP_CLIENT_SECRET'),
    'GLIP_BOT_NAME' => '@'. getenv('GLIP_BOT_NAME')
];


/*
 * Create the Subscription using Webhooks Method
 */
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '_subscribe';
if (!file_exists($cacheDir)) {

    if (!function_exists('getallheaders'))
    {
        function getallheaders()
        {
            $headers = array ();
            foreach ($_SERVER as $name => $value)
            {
                if (substr($name, 0, 5) == 'HTTP_')
                {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
    }

    print 'The Subscribe file is created :' . PHP_EOL;

    mkdir($cacheDir);
    $request = Request::createFromGlobals();
    // GlipWebhook verification
    if ($request->headers->has('Validation-Token'))
    {

        $headers = getallheaders();
        return Response::create('',200,array('Validation-Token' => $headers['Validation-Token']))->send();
    }
}




// Load the Driver into Botman
DriverManager::loadDriver(GlipBotman::class);


// Create a Botman Instance
$botman = BotManFactory::create($config);

$botman->hears('search topic {query}', function ($bot, $query) {
    $bot->reply($query);
});

$botman->hears('help', function ($bot) {
    $bot->reply('The List of available commands are : ' . '[' . 'Click Me for Examples' . ']' . '(' . 'https://github.com/anilkumarbp/botman-getsatisfaction#example-usage' . ')' . PHP_EOL . '*' . ' ' . 'search topic (query) - returns a list of matching topics.' . PHP_EOL . '*' . ' ' . 'search topic (query) (filter) - returns a list of matching topics based on the filters');
});

// Start listening
$botman->listen();