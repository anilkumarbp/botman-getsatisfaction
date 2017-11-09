<?php


require('vendor/autoload.php');

use RingCentral\SDK\SDK;
use Symfony\Component\HttpFoundation\Response;


try {

    if (!isset($_GET['code'])) {
        print 'Heroku App Deployed' . PHP_EOL;
        return;
    }

    // Create SDK instance
    $rcsdk = new SDK(getenv('GLIP_CLIENT_ID'), getenv('GLIP_CLIENT_SECRET') , getenv('GLIP_SERVER'), 'Demo', '1.0.0');

    // Create Platform instance
    $platform = $rcsdk->platform();
    $qs = $platform->parseAuthRedirectUrl($_SERVER['QUERY_STRING']);
    $qs["redirectUri"] = getenv('GLIP_REDIRECT_URL');
    $auth = $platform->login($qs);

    /*
     * Using the caching mechanism
     */
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '_cache';
    $file = $cacheDir . DIRECTORY_SEPARATOR . 'platform.json';
    if (!file_exists($cacheDir)) {
        print ' The cacheDir is created ' . PHP_EOL;
        mkdir($cacheDir);
    }
    file_put_contents($file, json_encode($platform->auth()->data(), JSON_PRETTY_PRINT));
    print PHP_EOL . "Wohooo, your Bot is on-boarded to Glip." . PHP_EOL;

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
            "address"=>getenv('GLIP_WEBHOOK_URL')
        )
    ));

    print PHP_EOL . "Wohooo, your Bot is Registered now." . PHP_EOL;


} catch (Exception $e) {

    print 'Webhook Provision Error: ' . $e->getMessage() . PHP_EOL;

}