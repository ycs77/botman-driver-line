<?php

namespace Tests\Drivers;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Line\Events\MessagingAccountLinking;
use BotMan\Drivers\Line\Events\MessagingCheckoutUpdates;
use BotMan\Drivers\Line\Events\MessagingDeliveries;
use BotMan\Drivers\Line\Events\MessagingOptins;
use BotMan\Drivers\Line\Events\MessagingReads;
use BotMan\Drivers\Line\Events\MessagingReferrals;
use BotMan\Drivers\Line\Exceptions\LineException;
use BotMan\Drivers\Line\Extensions\QuickReplyButton;
use BotMan\Drivers\Line\LineDriver;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Constant\Meta;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LineDriverTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    private function getRequest($responseData)
    {
        /** @var \Symfony\Component\HttpFoundation\Request|\Mockery\MockInterface|\Mockery\LegacyMockInterface $request */
        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class . '[getContent]');
        $request->shouldReceive('getContent')->andReturn($responseData);

        return $request;
    }

    private function getDriver($responseData, array $config = null, $signature = '', $htmlInterface = null)
    {
        if (is_null($config)) {
            $config = [
                'line' => [
                    'channel_access_token' => 'Foo',
                    'channel_secret' => 'Bar',
                ],
            ];
        }

        $request = $this->getRequest($responseData);
        $request->headers->set(HTTPHeader::LINE_SIGNATURE, $signature);

        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new LineDriver($request, $config, $htmlInterface);
    }

    private function getHttpHeaders(string $channelAccessToken = 'Foo')
    {
        return [
            'Authorization: Bearer ' . $channelAccessToken,
            'User-Agent: LINE-BotSDK-PHP/' . Meta::VERSION,
        ];
    }

    private function getHttpPostHeaders(string $channelAccessToken = 'Foo')
    {
        return array_merge($this->getHttpHeaders($channelAccessToken), [
            'Content-Type: application/json; charset=utf-8',
        ]);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver('');
        $this->assertSame('Line', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $request = '{}';
        $driver = $this->getDriver($request);
        $this->assertFalse($driver->matchesRequest());

        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"text":"Hello,world"}}]}';
        $driver = $this->getDriver($request);
        $this->assertFalse($driver->matchesRequest());

        $signature = 'Foo';

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
                'channel_secret' => 'Bar',
            ],
        ];
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello,world"}}]}';
        $driver = $this->getDriver($request, $config, $signature);
        $this->assertFalse($driver->matchesRequest());

        $signature = 'VQPjJerJekWwQ8VSC1wyThne43l4+QGL6EVZGbvQNnY=';

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
                'channel_secret' => 'Bar',
            ],
        ];
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello,world"}}]}';
        $driver = $this->getDriver($request, $config, $signature);
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';
        $driver = $this->getDriver($request);

        $this->assertSame('Hello!!!', $driver->getMessages()[0]->getText());

        $request = '{"events":[{"message":{}}]}';
        $driver = $this->getDriver($request);

        $this->assertSame('', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_user_object()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';

        $lineResponse = '{"displayName":"Lucas","userId":"UID12345678","pictureUrl":"https://line.com/profile/pic","statusMessage":"Hello world!"}';

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('get')
            ->once()
            ->with('https://api.line.me/v2/bot/profile/UID12345678', [], $this->getHttpHeaders('Foo'))
            ->andReturn(new Response($lineResponse));

        $driver = $this->getDriver($request, $config, '', $htmlInterface);
        $message = $driver->getMessages()[0];
        $user = $driver->getUser($message);

        $this->assertSame('UID12345678', $user->getId());
        $this->assertSame('Lucas', $user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertSame('Lucas', $user->getUsername());
        $this->assertSame('https://line.com/profile/pic', $user->getPicture());
        $this->assertSame('Hello world!', $user->getStatusMessage());
    }

    /** @test */
    public function it_throws_exception_in_get_user()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ];

        $errorResponse = '{"message":"The request body has 2 errors","details":[{"message":"May not be empty","property":"messages[0].text"},{"message":"Must be one of the following values: [text, image, video, audio, location, sticker, template, imagemap]","property":"messages[1].type"}]}';

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('get')
            ->once()
            ->with('https://api.line.me/v2/bot/profile/UID12345678', [], $this->getHttpHeaders('Foo'))
            ->andReturn(new Response($errorResponse, 400));

        $driver = $this->getDriver($request, $config, '', $htmlInterface);

        $this->expectException(LineException::class);
        $this->expectExceptionMessage('Error sending payload: The request body has 2 errors: May not be empty. Must be one of the following values: [text, image, video, audio, location, sticker, template, imagemap].');

        $driver->getUser($driver->getMessages()[0]);
    }

    /** @test */
    public function it_returns_an_empty_message_if_nothing_matches()
    {
        $request = '';
        $driver = $this->getDriver($request);

        $this->assertSame('', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver('');
        $this->assertFalse($driver->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';
        $driver = $this->getDriver($request);

        $this->assertSame('UID12345678', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_recipient_id()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';
        $driver = $this->getDriver($request);

        $this->assertNull($driver->getMessages()[0]->getRecipient());

        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678","groupId":"GID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';
        $driver = $this->getDriver($request);

        $this->assertSame('GID12345678', $driver->getMessages()[0]->getRecipient());

        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678","roomId":"RID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';
        $driver = $this->getDriver($request);

        $this->assertSame('RID12345678', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')
            ->once()
            ->with('https://api.line.me/v2/bot/message/reply', [], [
                'replyToken' => '0f3779fba3b349968c5d07db31eab56f',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, user',
                    ],
                ],
            ], $this->getHttpPostHeaders('Foo'))
            ->andReturn(new Response());

        $driver = $this->getDriver($request, null, '', $htmlInterface);

        $message = new IncomingMessage('', 'UID12345678', '');
        $response = $driver->sendPayload($driver->buildServicePayload('Hello, user', $message));

        $this->assertTrue($response->isOk());
    }

    /** @test */
    public function it_throws_exception_while_sending_message()
    {
        $request = '{"destination":"xxxxxxxxxx","events":[{"replyToken":"0f3779fba3b349968c5d07db31eab56f","type":"message","timestamp":1462629479859,"source":{"type":"user","userId":"UID12345678"},"message":{"id":"325708","type":"text","text":"Hello!!!"}}]}';

        $errorResponse = '{"message":"The request body has 2 errors","details":[{"message":"May not be empty","property":"messages[0].text"},{"message":"Must be one of the following values: [text, image, video, audio, location, sticker, template, imagemap]","property":"messages[1].type"}]}';

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')
            ->once()
            ->with('https://api.line.me/v2/bot/message/reply', [], [
                'replyToken' => '0f3779fba3b349968c5d07db31eab56f',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, user',
                    ],
                ],
            ], $this->getHttpPostHeaders('Foo'))
            ->andReturn(new Response($errorResponse, 400));

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ];

        $driver = $this->getDriver($request, $config, '', $htmlInterface);

        $message = new IncomingMessage('', 'UID12345678', '');

        $this->expectException(LineException::class);
        $this->expectExceptionMessage('Error sending payload: The request body has 2 errors: May not be empty. Must be one of the following values: [text, image, video, audio, location, sticker, template, imagemap].');

        $driver->sendPayload($driver->buildServicePayload('Hello, user', $message));
    }

    /** @test */
    public function it_returns_answer_from_interactive_messages()
    {
        $request = '{}';

        $driver = $this->getDriver($request);

        $message = new IncomingMessage('Red', 'UID12345678', 'GID12345678', [
            'sender' => [
                'id' => 'UID12345678',
            ],
            'recipient' => [
                'id' => 'GID12345678',
            ],
            'message' => [
                'text' => 'Red',
                'quick_reply' => [
                    'payload' => 'DEVELOPER_DEFINED_PAYLOAD',
                ],
            ],
        ]);

        $this->assertSame('Red', $driver->getConversationAnswer($message)->getText());
        $this->assertSame($message, $driver->getConversationAnswer($message)->getMessage());
        $this->assertSame('DEVELOPER_DEFINED_PAYLOAD', $driver->getConversationAnswer($message)->getValue());
    }

    /** @test */
    public function it_returns_answer_from_regular_messages()
    {
        $request = '{}';

        $driver = $this->getDriver($request);

        $message = new IncomingMessage('Red', 'UID12345678', 'GID12345678', [
            'sender' => [
                'id' => 'UID12345678',
            ],
            'recipient' => [
                'id' => 'GID12345678',
            ],
            'message' => [
                'text' => 'Red',
            ],
        ]);

        $this->assertSame('Red', $driver->getConversationAnswer($message)->getText());
        $this->assertSame(null, $driver->getConversationAnswer($message)->getValue());
    }

    /** @test */
    public function it_can_reply_questions()
    {
        $question = Question::create('How are you doing?')
            ->addButton(
                Button::create('Great')->value('great')
            )
            ->addButton(
                Button::create('Good')->value('good')
            );

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')
            ->once()
            ->with('https://graph.line.com/v3.0/me/messages', [], [
                // 'messaging_type' => 'RESPONSE',
                // 'recipient' => [
                //     'id' => '1234567890',
                // ],
                // 'message' => [
                //     'text' => 'How are you doing?',
                //     'quick_replies' => [
                //         [
                //             'content_type' => 'text',
                //             'title' => 'Great',
                //             'payload' => 'great',
                //             'image_url' => null,
                //         ],
                //         [
                //             'content_type' => 'text',
                //             'title' => 'Good',
                //             'payload' => 'good',
                //             'image_url' => null,
                //         ],
                //     ],
                // ],
                'replyToken' => '0f3779fba3b349968c5d07db31eab56f',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, user',
                    ],
                ],
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('[]');

        $driver = $this->getDriver($request, null, '', $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_questions_with_additional_button_parameters()
    {
        $question = Question::create('How are you doing?')->addButton(Button::create('Great')->value('great')->additionalParameters(['foo' => 'bar']))->addButton(Button::create('Good')->value('good'));

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'id' => '1234567890',
                ],
                'message' => [
                    'text' => 'How are you doing?',
                    'quick_replies' => [
                        [
                            'content_type' => 'text',
                            'title' => 'Great',
                            'payload' => 'great',
                            'image_url' => null,
                            'foo' => 'bar',
                        ],
                        [
                            'content_type' => 'text',
                            'title' => 'Good',
                            'payload' => 'good',
                            'image_url' => null,
                        ],
                    ],
                ],
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('[]');

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_quick_replies_with_special_types()
    {
        $question = Question::create('How are you doing?')
            ->addAction(QuickReplyButton::create()->type('user_email'))
            ->addAction(QuickReplyButton::create()->type('location'))
            ->addAction(QuickReplyButton::create()->type('user_phone_number'));

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
            'messaging_type' => 'RESPONSE',
            'recipient' => [
                'id' => '1234567890',
            ],
            'message' => [
                'text' => 'How are you doing?',
                'quick_replies' => [
                    [
                        'content_type' => 'user_email',
                    ],
                    [
                        'content_type' => 'location',
                    ],
                    [
                        'content_type' => 'user_phone_number',
                    ],
                ],
            ],
            'access_token' => 'Foo',
        ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('[]');

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_is_configured()
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('');
        $htmlInterface = m::mock(Curl::class);

        $config = [
            'line' => [
                'channel_access_token' => 'Foo',
                'channel_secret' => 'Bar',
            ],
        ];
        $driver = new LineDriver($request, $config, $htmlInterface);

        $this->assertTrue($driver->isConfigured());

        $config = [
            'line' => [
                'channel_access_token' => null,
                'channel_secret' => 'Bar',
            ],
        ];
        $driver = new LineDriver($request, $config, $htmlInterface);

        $this->assertFalse($driver->isConfigured());

        $driver = new LineDriver($request, [], $htmlInterface);

        $this->assertFalse($driver->isConfigured());
    }

    /** @test */
    public function it_can_reply_message_objects()
    {
        $responseData = [
            'object' => 'page',
            'event' => [
                [
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => '1234567890',
                            ],
                            'recipient' => [
                                'id' => '0987654321',
                            ],
                            'message' => [
                                'text' => 'test',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'id' => '1234567890',
                ],
                'message' => [
                    'text' => 'Test',
                ],
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test'),
            $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_image()
    {
        $responseData = [
            'object' => 'page',
            'event' => [
                [
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => '1234567890',
                            ],
                            'recipient' => [
                                'id' => '0987654321',
                            ],
                            'message' => [
                                'text' => 'test',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'id' => '1234567890',
                ],
                'message' => [
                    'attachment' => [
                        'type' => 'image',
                        'payload' => [
                            'is_reusable' => false,
                            'url' => 'http://image.url//foo.png',
                        ],
                    ],
                ],
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test',
            Image::url('http://image.url//foo.png')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_audio()
    {
        $responseData = [
            'object' => 'page',
            'event' => [
                [
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => '1234567890',
                            ],
                            'recipient' => [
                                'id' => '0987654321',
                            ],
                            'message' => [
                                'text' => 'test',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'id' => '1234567890',
                ],
                'message' => [
                    'attachment' => [
                        'type' => 'audio',
                        'payload' => [
                            'is_reusable' => false,
                            'url' => 'http://image.url//foo.mp3',
                        ],
                    ],
                ],
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test',
            Audio::url('http://image.url//foo.mp3')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_file()
    {
        $responseData = [
            'object' => 'page',
            'event' => [
                [
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => '1234567890',
                            ],
                            'recipient' => [
                                'id' => '0987654321',
                            ],
                            'message' => [
                                'text' => 'test',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'messaging_type' => 'RESPONSE',
                'recipient' => [
                    'id' => '1234567890',
                ],
                'message' => [
                    'attachment' => [
                        'type' => 'file',
                        'payload' => [
                            'is_reusable' => false,
                            'url' => 'http://image.url//foo.pdf',
                        ],
                    ],
                ],
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test',
            File::url('http://image.url//foo.pdf')), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_reusable_file()
    {
        $responseData = [
            'object' => 'page',
            'event' => [
                [
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => '1234567890',
                            ],
                            'recipient' => [
                                'id' => '0987654321',
                            ],
                            'message' => [
                                'text' => 'test',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
            'messaging_type' => 'RESPONSE',
            'recipient' => [
                'id' => '1234567890',
            ],
            'message' => [
                'attachment' => [
                    'type' => 'file',
                    'payload' => [
                        'is_reusable' => true,
                        'url' => 'http://image.url//foo.pdf',
                    ],
                ],
            ],
            'access_token' => 'Foo',
        ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $file = File::url('http://image.url//foo.pdf');
        $file->addExtras('is_reusable', true);

        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', $file), $message));
    }

    /** @test */
    public function it_calls_referral_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"1433960459967306"},"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"referral":{"ref":"MY_REF","source": "MY_SOURCE","type": "MY_TYPE"}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingReferrals::class, $event);
        $this->assertSame('messaging_referrals', $event->getName());
    }

    /** @test */
    public function it_has_message_for_referral_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"1433960459967306"},"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"referral":{"ref":"MY_REF","source": "MY_SOURCE","type": "MY_TYPE"}}]}]}';
        $driver = $this->getDriver($request);

        $message = $driver->getMessages()[0];
        $this->assertSame('1433960459967306', $message->getSender());
        $this->assertSame('111899832631525', $message->getRecipient());
    }

    /** @test */
    public function it_calls_optin_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"optin": {"ref":"optin","user_ref":"1234"}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingOptins::class, $event);
        $this->assertSame('messaging_optins', $event->getName());
    }

    /** @test */
    public function it_has_message_for_optin_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"optin": {"ref":"optin","user_ref":"1234"}}]}]}';
        $driver = $this->getDriver($request);

        $message = $driver->getMessages()[0];
        $this->assertSame('1234', $message->getSender());
        $this->assertSame('111899832631525', $message->getRecipient());
    }

    /** @test */
    public function it_calls_delivery_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"USER_ID"},"recipient":{"id":"PAGE_ID"},"delivery":{"mids":["mid.1458668856218:ed81099e15d3f4f233"],"watermark":1458668856253,"seq":37}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingDeliveries::class, $event);
        $this->assertSame('messaging_deliveries', $event->getName());
    }

    /** @test */
    public function it_calls_read_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"USER_ID"},"recipient":{"id":"PAGE_ID"},"timestamp":1458668856463,"read":{"watermark":1458668856253,"seq":38}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingReads::class, $event);
        $this->assertSame('messaging_reads', $event->getName());
    }

    /** @test */
    public function it_calls_account_linking_event()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"USER_ID"},"recipient":{"id":"PAGE_ID"},"timestamp":1458668856463,"account_linking":{"status":"linked","authorization_code":"authorization code"}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingAccountLinking::class, $event);
        $this->assertSame('messaging_account_linking', $event->getName());
    }

    /** @test */
    public function it_calls_checkout_update_event()
    {
        $request = '{"object": "page","entry": [{"id": "PAGE_ID","time": 1473204787206,"messaging": [{"recipient": {"id": "PAGE_ID"},"timestamp": 1473204787206,"sender": {"id": "USER_ID"},"checkout_update": {"payload": "DEVELOPER_DEFINED_PAYLOAD","shipping_address": {"id": 10105655000959552,"country": "US","city": "MENLO PARK","street1": "1 Hacker Way","street2": "","state": "CA","postal_code": "94025"}}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(MessagingCheckoutUpdates::class, $event);
        $this->assertSame('messaging_checkout_updates', $event->getName());
    }

    /** @test */
    public function it_calls_generic_event_for_unkown_line_events()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"USER_ID"},"recipient":{"id":"PAGE_ID"},"timestamp":1458668856463,"foo":{"watermark":1458668856253,"seq":38}}]}]}';
        $driver = $this->getDriver($request);

        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame('foo', $event->getName());
    }

    /** @test */
    public function it_can_reply_mark_seen_sender_action()
    {
        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('post')->once()->with('https://graph.line.com/v3.0/me/messages', [], [
                'recipient' => [
                    'id' => '1234567890',
                ],
                'sender_action' => 'mark_seen',
                'access_token' => 'Foo',
            ])->andReturn(new Response());

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('[]');

        $driver = new LineDriver($request, [
            'line' => [
                'channel_access_token' => 'Foo',
            ],
        ], $htmlInterface);

        $message = new IncomingMessage('', '1234567890', '');
        $driver->markSeen($message);
    }

    /** @test */
    public function it_returns_the_quick_reply_postback()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"1433960459967306"},"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"message":{"quick_reply":{"payload":"MY_PAYLOAD"},"mid":"mid.1480279487147:4388d3b344","seq":36,"text":"Red"}}]}]}';

        $driver = $this->getDriver($request);
        $this->assertSame('MY_PAYLOAD', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_quick_reply_button_text_and_value_for_conversation_answer()
    {
        $request = '{"object":"page","entry":[{"id":"111899832631525","time":1480279487271,"messaging":[{"sender":{"id":"1433960459967306"},"recipient":{"id":"111899832631525"},"timestamp":1480279487147,"message":{"quick_reply":{"payload":"MY_PAYLOAD"},"mid":"mid.1480279487147:4388d3b344","seq":36,"text":"Red"}}]}]}';

        $driver = $this->getDriver($request);
        $this->assertSame('Red', $driver->getConversationAnswer($driver->getMessages()[0])->getText());
        $this->assertSame('MY_PAYLOAD', $driver->getConversationAnswer($driver->getMessages()[0])->getValue());
    }
}
