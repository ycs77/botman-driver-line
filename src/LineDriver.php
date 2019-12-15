<?php

namespace BotMan\Drivers\Line;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Interfaces\VerifiesService;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Line\Events\MessagingDeliveries;
use BotMan\Drivers\Line\Events\MessagingOptins;
use BotMan\Drivers\Line\Events\MessagingReads;
use BotMan\Drivers\Line\Events\MessagingReferrals;
use BotMan\Drivers\Line\Exceptions\LineException;
use BotMan\Drivers\Line\Extensions\ButtonTemplate;
use BotMan\Drivers\Line\Extensions\GenericTemplate;
use BotMan\Drivers\Line\Extensions\ListTemplate;
use BotMan\Drivers\Line\Extensions\MediaTemplate;
use BotMan\Drivers\Line\Extensions\OpenGraphTemplate;
use BotMan\Drivers\Line\Extensions\ReceiptTemplate;
use BotMan\Drivers\Line\Extensions\User;
use Illuminate\Support\Collection;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Constant\MessageType;
use LINE\LINEBot\Constant\Meta;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LineDriver extends HttpDriver implements VerifiesService
{
    const DRIVER_NAME = 'Line';

    /** @var string */
    protected $signature;

    /** @var array */
    protected $messages = [];

    /** @var array */
    protected $templates = [
        ButtonTemplate::class,
        GenericTemplate::class,
        ListTemplate::class,
        ReceiptTemplate::class,
        MediaTemplate::class,
        OpenGraphTemplate::class,
    ];

    private $supportedAttachments = [
        Video::class,
        Audio::class,
        Image::class,
        File::class,
    ];

    /** @var DriverEventInterface */
    protected $driverEvent;

    // protected $lineProfileEndpoint = 'https://graph.line.com/v3.0/';

    /** @var string */
    protected $endpointBase = 'https://api.line.me';

    /** @var string */
    protected $dataEndpointBase = 'https://api-data.line.me';

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->event = Collection::make((array) $this->payload->get('events')[0]);
        $this->signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE, '');
        $this->content = $request->getContent();
        $this->config = Collection::make($this->config->get('line', []));
        $this->endpointBase = $this->config->get('endpoint_base', $this->endpointBase);
        $this->dataEndpointBase = $this->config->get('data_endpoint_base', $this->endpointBase);
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->validateSignature()
            && $this->event->has('type', 'timestamp', 'source');
    }

    /**
     * @param Request $request
     * @return null|Response
     */
    public function verifyRequest(Request $request)
    {
        if ($request->get('hub_mode') === 'subscribe' && $request->get('hub_verify_token') === $this->config->get('verification')) {
            return Response::create($request->get('hub_challenge'))->send();
        }
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        $event = Collection::make($this->event->get('messaging'))->filter(function ($msg) {
            return Collection::make($msg)->except([
                    'sender',
                    'recipient',
                    'timestamp',
                    'message',
                    'postback',
                ])->isEmpty() === false;
        })->transform(function ($msg) {
            return Collection::make($msg)->toArray();
        })->first();

        if (! is_null($event)) {
            $this->driverEvent = $this->getEventFromEventData($event);

            return $this->driverEvent;
        }

        return false;
    }

    /**
     * @param array $eventData
     * @return DriverEventInterface
     */
    protected function getEventFromEventData(array $eventData)
    {
        $name = Collection::make($eventData)->except([
            'sender',
            'recipient',
            'timestamp',
            'message',
            'postback',
        ])->keys()->first();
        switch ($name) {
            case 'referral':
                return new MessagingReferrals($eventData);
                break;
            case 'optin':
                return new MessagingOptins($eventData);
                break;
            case 'delivery':
                return new MessagingDeliveries($eventData);
                break;
            case 'read':
                return new MessagingReads($eventData);
                break;
            case 'account_linking':
                return new Events\MessagingAccountLinking($eventData);
                break;
            case 'checkout_update':
                return new Events\MessagingCheckoutUpdates($eventData);
                break;
            default:
                $event = new GenericEvent($eventData);
                $event->setName($name);

                return $event;
                break;
        }
    }

    /**
     * @return bool
     */
    protected function validateSignature()
    {
        $hash = hash_hmac('sha256', $this->content, $this->config->get('channel_secret'), true);
        $encodedHash = base64_encode($hash);

        return hash_equals($encodedHash, $this->signature);
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function markSeen(IncomingMessage $matchingMessage)
    {
        $parameters = [
            'recipient' => [
                'id' => $matchingMessage->getSender(),
            ],
            'access_token' => $this->config->get('channel_access_token'),
            'sender_action' => 'mark_seen',
        ];

        return $this->http->post($this->lineProfileEndpoint.'me/messages', [], $parameters);
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function types(IncomingMessage $matchingMessage)
    {
        $parameters = [
            'recipient' => [
                'id' => $matchingMessage->getSender(),
            ],
            'access_token' => $this->config->get('channel_access_token'),
            'sender_action' => 'typing_on',
        ];

        return $this->http->post($this->lineProfileEndpoint.'me/messages', [], $parameters);
    }

    /**
     * @param  IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        $payload = $message->getPayload();

        if (isset($payload['message']['quick_reply'])) {
            return Answer::create($payload['message']['text'])
                ->setMessage($message)
                ->setInteractiveReply(true)
                ->setValue($payload['message']['quick_reply']['payload']);
        } elseif (isset($payload['postback']['payload'])) {
            return Answer::create($payload['postback']['title'])
                ->setMessage($message)
                ->setInteractiveReply(true)
                ->setValue($payload['postback']['payload']);
        }

        return Answer::create($message->getText())
            ->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load Line messages.
     */
    protected function loadMessages()
    {
        $message = new IncomingMessage(
            '',
            $this->getMessageSender($this->event),
            $this->getMessageRecipient($this->event),
            $this->payload
        );

        if (isset($this->event->get('message')['text'])) {
            $message->setText($this->event->get('message')['text']);
        } elseif (isset($this->event->get('postback')['data'])) {
            $message->setText($this->event->get('postback')['data']);
        }

        $this->messages = [$message];
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        // Line Bot replies don't get returned
        return false;
    }

    /**
     * Convert a Question object into a valid Line
     * quick reply response object.
     *
     * @param Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $replies = Collection::make($question->getButtons())
            ->map(function ($button) {
                if (isset($button['content_type']) && $button['content_type'] !== 'text') {
                    return ['content_type' => $button['content_type']];
                }

                return array_merge([
                    'content_type' => 'text',
                    'title' => $button['text'] ?? $button['title'],
                    'payload' => $button['value'] ?? $button['payload'],
                    'image_url' => $button['image_url'] ?? $button['image_url'],
                ], $button['additional'] ?? []);
            });

        return [
            'text' => $question->getText(),
            'quick_replies' => $replies->toArray(),
        ];
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = [
            'replyToken' => $this->event->get('replyToken'),
            'messages' => [],
        ];

        // if ($this->driverEvent) {
        //     $payload = $this->driverEvent->getPayload();
        //     if (isset($payload['optin']) && isset($payload['optin']['user_ref'])) {
        //         $recipient = ['user_ref' => $payload['optin']['user_ref']];
        //     } else {
        //         $recipient = ['id' => $payload['sender']['id']];
        //     }
        // } else {
        //     $recipient = ['id' => $matchingMessage->getSender()];
        // }

        if ($message instanceof Question) {
            $parameters['message'] = $this->convertQuestion($message);
        } elseif (is_object($message) && in_array(get_class($message), $this->templates)) {
            // $parameters['message'] = $message->toArray();
        } elseif ($message instanceof OutgoingMessage) {
            // $attachment = $message->getAttachment();
            // if (! is_null($attachment) && in_array(get_class($attachment), $this->supportedAttachments)) {
            //     $attachmentType = strtolower(basename(str_replace('\\', '/', get_class($attachment))));
            //     unset($parameters['message']['text']);
            //     $parameters['message']['attachment'] = [
            //         'type' => $attachmentType,
            //         'payload' => [
            //             'is_reusable' => $attachment->getExtras('is_reusable') ?? false,
            //             'url' => $attachment->getUrl(),
            //         ],
            //     ];
            // } else {
            //     $parameters['message']['text'] = $message->getText();
            // }
        }

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     * @throws LineException
     */
    public function sendPayload($payload)
    {
        $url = $this->endpointBase . '/v2/bot/message/reply';
        $response = $this->http->post($url, [], $payload, $this->getHttpPostHeaders());

        $this->throwExceptionIfResponseNotOk($response);

        return $response;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('channel_access_token'))
            && ! empty($this->config->get('channel_secret'));
    }

    /**
     * Retrieve User information.
     *
     * @param IncomingMessage $matchingMessage
     * @return User
     * @throws LineException
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $url = $this->endpointBase . '/v2/bot/profile/' . urlencode($matchingMessage->getSender());
        $response = $this->http->get($url, [], $this->getHttpHeaders());

        $this->throwExceptionIfResponseNotOk($response);

        $userInfo = json_decode($response->getContent(), true);
        $name = $userInfo['displayName'] ?? null;

        return new User($matchingMessage->getSender(), $name, null, $name, $userInfo);
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'access_token' => $this->config->get('channel_access_token'),
        ], $parameters);

        return $this->http->post($this->lineProfileEndpoint.$endpoint, [], $parameters);
    }

    /**
     * @param Response $lineResponse
     * @return mixed
     * @throws LineException
     */
    protected function throwExceptionIfResponseNotOk(Response $lineResponse)
    {
        if ($lineResponse->getStatusCode() !== 200) {
            $responseData = json_decode($lineResponse->getContent(), true);

            $message = $responseData['message'];

            if (isset($responseData['details'])) {
                $message .= ':' . Collection::make($responseData['details'])
                    ->reduce(function ($carry, $value) {
                        return $carry . ' ' . $value['message'] . '.';
                    }, '');
            }

            throw new LineException('Error sending payload: ' . $message);
        }
    }

    /**
     * @param $event
     * @return string|null
     */
    protected function getMessageSender($event)
    {
        if (isset($event['source'])) {
            return $event['source']['userId'] ?? null;
        }
    }

    /**
     * @param $event
     * @return string|null
     */
    protected function getMessageRecipient($event)
    {
        if (isset($event['source'])) {
            return $event['source']['groupId'] ?? $event['source']['roomId'] ?? null;
        }
    }

    /**
     * Get the http headers.
     *
     * @param  array  $headers
     * @return array
     */
    public function getHttpHeaders(array $headers = [])
    {
        return array_merge([
            'Authorization: Bearer ' . $this->config->get('channel_access_token'),
            'User-Agent: LINE-BotSDK-PHP/' . Meta::VERSION,
        ], $headers);
    }

    /**
     * Get the http headers for POST method.
     *
     * @param  array  $headers
     * @return array
     */
    public function getHttpPostHeaders(array $headers = [])
    {
        return array_merge($this->getHttpHeaders(), [
            'Content-Type: application/json; charset=utf-8',
        ], $headers);
    }
}
