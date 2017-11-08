<?php

require('vendor/autoload.php');
require('GlipBotman.php');


use Mpociot\BotMan\BotManFactory;
use Mpociot\BotMan\BotMan;
use Mpociot\BotMan\DriverManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GlipDriver\GlipBotman;

// Parse the .env file
$dotenv = new Dotenv\Dotenv(getcwd());
$dotenv->load();


// Load the values from .env
$config = [
    'GLIP_SERVER' => $_ENV['GLIP_SERVER'],
    'GLIP_APPKEY' => $_ENV['GLIP_APPKEY'],
    'GLIP_APPSECRET' => $_ENV['GLIP_APPSECRET'],
    'GLIP_BOT_NAME' => '@'.$_ENV['GLIP_BOT_NAME']
];


/*
 * Create the Subscription using Webhooks Method
 */
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '_subscribe';
if (!file_exists($cacheDir)) {

    mkdir($cacheDir);
    $request = Request::createFromGlobals();
    // GlipWebhook verification
    if ($request->headers->has('Validation-Token'))
    {

        return Response::create('',200,array('Validation-Token' => getallheaders()['Validation-Token']))->send();
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