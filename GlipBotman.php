<?php

namespace GlipDriver;

use Mpociot\BotMan\User;
use Mpociot\BotMan\Answer;
use Mpociot\BotMan\Message;
use Mpociot\BotMan\Question;
use Mpociot\BotMan\Drivers\Driver;
use Illuminate\Support\Collection;
use SebastianBergmann\CodeCoverage\Report\PHP;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Mpociot\BotMan\Messages\Message as IncomingMessage;
use RingCentral\SDK\SDK;
use GetSatisfactionAPILib\GetSatisfactionAPIClient;
use GetSatisfactionAPILib\APIHelper;
use Aws\Common\Aws;
use Aws\Ses\SesClient;
use Aws\S3\S3Client;


class GlipBotman extends Driver
{
    /** @var Collection */
    protected $event;

    /** @var config */
    protected $config;

    /** @var GlipClient */
    protected $sdk;
    protected $platform;

    const DRIVER_NAME = 'GlipBotman';

    /** @var Collection|ParameterBag */
    protected $payload;

    protected $endpoint = '/glip/posts';


    /**s
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->event = Collection::make($this->payload->get('event'));
    }

    /**
     * Return the driver name.
     *
     * @return string
     */
    public function getName()
    {
        return self::DRIVER_NAME;
    }

    /**
     * @param Message $matchingMessage
     * @return User
     */
    public function getUser(Message $matchingMessage)
    {
        $parameters = [
            'chat_id' => $matchingMessage->getChannel(),
            'user_id' => $matchingMessage->getUser(),
        ];

        $response = $this->$this->getPlatform()->get('/glip/persons' + $matchingMessage->getUser());
        $responseData = json_decode($response->getContent(), true);
        $userData = Collection::make($responseData['result']['user']);

        return new User($userData->get('id'), $userData->get('firstName'), $userData->get('lastName'), $userData->get('avatar'));
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return (! is_null($this->payload->get('body'))) && ! is_null($this->payload->get('event'));
    }

    /**
     * @param  Message $message
     * @return Answer
     */
    public function getConversationAnswer(Message $message)
    {
        return Answer::create($message->getMessage())->setMessage($message);
    }


    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if ($this->payload->get('body') !== null) {
            $callback = Collection::make($this->payload->get('body'));
            /*
             * To handle @mentions
             */
            $regex = "#<\s*?a\b[^>]*>(.*?)</a\b[^>]*>#s";
            preg_match($regex, $callback->get('text'), $matches);
            if($matches && $matches[1] == $this->config->get('GLIP_BOT_NAME')) {
                $glipMessage = explode("/a> ",$callback->get('text'));
                return [new Message($glipMessage[1], $callback->get('creatorId'), $callback->get('groupId'), $this->payload->get('body'))];
            }

            return [new Message('', $callback->get('creatorId'), $callback->get('groupId'), $this->payload->get('body'))];
        }

    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param Message $matchingMessage
     * @return void
     */
    public function types(Message $matchingMessage)
    {
        $parameters = [
            'chat_id' => $matchingMessage->getChannel(),
            'action' => 'typing',
        ];
        $this->http->post('/glip/posts', $parameters);
    }

    /**
     * Convert a Question object into a valid
     * quick reply response object.
     *
     * @param Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $replies = Collection::make($question->getButtons())->map(function ($button) {
            return [
                [
                    'text' => (string) $button['text'],
                    'callback_data' => (string) $button['value'],
                ],
            ];
        });

        return $replies->toArray();
    }

    /**
     * @return \RingCentral\SDK\Platform\Platform
     */
    public function getPlatform()
    {

        $rcsdk = new SDK($this->config->get('GLIP_CLIENT_ID'), $this->config->get('GLIP_CLIENT_SECRET'), $this->config->get('GLIP_SERVER'), 'Sample-Bot', '1.0.0');
        $platform = $rcsdk->platform();

        // Create the S3 Client
        $client = S3Client::factory(array(
            'key' => $this->config->get('amazonAccessKey'),
            'secret' => $this->config->get('amazonSecretKey'),
            'region' => $this->config->get('amazonRegion'),
            'command.params' => ['PathStyle' => true]
        ));

        $result = $client->getObject([
            'Bucket' => $this->config->get('amazonS3Bucket'),
            'Key' => $this->config->get('amazonBucketKeyname')
        ]);

        $token = json_decode($result['Body']);

        $platform->auth()->setData((array)$token);

        return $platform;
    }

    /**
     * Removes the inline keyboard from an interactive
     * message.
     * @param  int $chatId
     * @param  int $messageId
     * @return Response
     */
    private function removeInlineKeyboard($chatId, $messageId)
    {
        $parameters = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_keyboard' => [],
        ];

        $this->getPlatform()->post('/glip/posts', $parameters);
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param Message $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function reply($message, $matchingMessage, $additionalParameters = [])
    {


        $endpoint = 'sendMessage';
        $parameters = array_merge([
            'groupId' => $matchingMessage->getChannel(),
        ], $additionalParameters);
        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            $parameters['text'] = $message->getText();
            $parameters['reply_markup'] = json_encode([
                'inline_keyboard' => $this->convertQuestion($message),
            ], true);
        } elseif ($message instanceof IncomingMessage) {
            if (! is_null($message->getImage())) {
                if (strtolower(pathinfo($message->getImage(), PATHINFO_EXTENSION)) === 'gif') {
                    $endpoint = 'sendDocument';
                    $parameters['document'] = $message->getImage();
                } else {
                    $endpoint = 'sendPhoto';
                    $parameters['photo'] = $message->getImage();
                }
                $parameters['caption'] = $message->getMessage();
            } elseif (! is_null($message->getVideo())) {
                $endpoint = 'sendVideo';
                $parameters['video'] = $message->getVideo();
                $parameters['caption'] = $message->getMessage();
            } else {
                $parameters['text'] = $message->getMessage();
            }
        } else {
            if($message && strlen($message) < 100) {
                $query = explode(' ',$message);
                $glipMessage = $this->getTopicsFromGetSat($query);
                $parameters['text'] = $glipMessage;
            }
            else
            {
                $parameters['text'] = $message;
            }

        }


        $this->getPlatform()->post('/glip/posts', $parameters);
    }


    /*
     * Getting the topics from GetSat API's
     */
    public function getTopicsFromGetSat($queryParams)
    {
        $client = new GetSatisfactionAPIClient();
        $controller = $client->getTopic();
        $glipMessage = $this->formatGetSatResponse($controller->getCompaniesTopicsJsonByCompanyId('ringcentraldev',true,$queryParams));
        return $glipMessage;
    }

    public function formatGetSatResponse($apiResponse)
    {
        $parameters = "";
        foreach ($apiResponse->data as $item)
        {
            $lineItem = '*' . ' ' . '[' . $item->subject . ']' . '(' . $item->atSfn . ')' . PHP_EOL;
            $parameters .= $lineItem;
        }

        return $parameters;
    }


    /**
     * @param string|Question|IncomingMessage $message
     * @param Message $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $recipient = $matchingMessage->getRecipient() === '' ? $matchingMessage->getSender() : $matchingMessage->getRecipient();
        $parameters = array_merge_recursive([
            'groupId' => $recipient,
        ], $additionalParameters);

        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            $parameters['text'] = $message->getText();
            $parameters['reply_markup'] = json_encode([
                'inline_keyboard' => $this->convertQuestion($message),
            ], true);
        } elseif ($message instanceof IncomingMessage) {
            if (! is_null($message->getAttachment())) {
                $attachment = $message->getAttachment();
                $parameters['caption'] = $message->getText();
                if ($attachment instanceof Image) {
                    if (strtolower(pathinfo($attachment->getUrl(), PATHINFO_EXTENSION)) === 'gif') {
                        $this->endpoint = 'sendDocument';
                        $parameters['document'] = $attachment->getUrl();
                    } else {
                        $this->endpoint = 'sendPhoto';
                        $parameters['photo'] = $attachment->getUrl();
                    }
                } elseif ($attachment instanceof Video) {
                    $this->endpoint = 'sendVideo';
                    $parameters['video'] = $attachment->getUrl();
                } elseif ($attachment instanceof Audio) {
                    $this->endpoint = 'sendAudio';
                    $parameters['audio'] = $attachment->getUrl();
                } elseif ($attachment instanceof File) {
                    $this->endpoint = 'sendDocument';
                    $parameters['document'] = $attachment->getUrl();
                } elseif ($attachment instanceof Location) {
                    $this->endpoint = 'sendLocation';
                    $parameters['latitude'] = $attachment->getLatitude();
                    $parameters['longitude'] = $attachment->getLongitude();
                }
            } else {
                $parameters['text'] = $message->getText();
            }
        } else {
            $parameters['text'] = $message;
        }

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        return $this->getPlatform()->post($this->endpoint, $payload);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! is_null($this->getPlatform()->loggedIn());
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param Message $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, Message $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'chat_id' => $matchingMessage->getRecipient(),
        ], $parameters);

        return $this->getPlatform()->post($endpoint, $parameters);
    }
}
