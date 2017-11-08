<?php


require('vendor/autoload.php');

use RingCentral\SDK\SDK;


// Parse the .env file
if(getenv('APPLICATION_ENV') !== 'production') { /* or staging */
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();
}

try {

    // Create SDK instance
    $rcsdk = new SDK($_ENV['GLIP_CLIENT_ID'], $_ENV['GLIP_CLIENT_SECRET'] , $_ENV['GLIP_SERVER'], 'Demo', '1.0.0');

    // Create Platform instance
    $platform = $rcsdk->platform();
    /*
     * Using the caching mechanism
     */
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '_cache';
    $file = $cacheDir . DIRECTORY_SEPARATOR . 'platform.json';

    $cachedAuth = array();

    if (file_exists($file)) {
        $cachedAuth = json_decode(file_get_contents($file), true);
    }

    $platform->auth()->setData($cachedAuth);

    /*
     * Setup Webhook Subscription
     */
    $apiResponse = $platform->post('/subscription',array(
        "eventFilters"=>array(
            "/restapi/v1.0/glip/groups",
            "/restapi/v1.0/glip/posts"
        ),
        "deliveryMode"=>array(
            "transportType"=> "WebHook",
            "address"=>$_ENV['GLIP_WEBHOOK_URL']
        )
    ));

    print PHP_EOL . "Wohooo, your Bot is Registered now." . PHP_EOL;

} catch (Exception $e) {

    print 'Webhook Setup Error: ' . $e->getMessage() . PHP_EOL;

}


