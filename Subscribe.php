<?php


require('vendor/autoload.php');

use RingCentral\SDK\SDK;
use Aws\Common\Aws;
use Aws\Ses\SesClient;
use Aws\S3\S3Client;


try {

    // Create SDK instance
    $rcsdk = new SDK(getenv('GLIP_CLIENT_ID'), getenv('GLIP_CLIENT_SECRET') , getenv('GLIP_SERVER'), 'Demo', '1.0.0');

    // Create Platform instance
    $platform = $rcsdk->platform();
    /*
     * Using the AmazonS3 Bucket to get the tokens
     */

    // Create the S3 Client
    $client = S3Client::factory(array(
        'key' => getenv('amazonAccessKey'),
        'secret' => getenv('amazonSecretKey'),
        'region' => getenv('amazonRegion'),
        'command.params' => ['PathStyle' => true]
    ));

    $result = $client->getObject([
        'Bucket' => getenv('amazonS3Bucket'),
        'Key' => getenv('amazonBucketKeyname')
    ]);

    $token = json_decode($result['Body']);
    $platform->auth()->setData((array)$token);


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

    print 'Webhook Setup Error: ' . $e->getMessage() . PHP_EOL;

}


