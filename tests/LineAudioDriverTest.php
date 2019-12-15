<?php

namespace BotMan\BotMan\tests\Drivers;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use BotMan\Drivers\Line\LineAudioDriver;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class LineAudioDriverTest extends PHPUnit_Framework_TestCase
{
    /**
     * Get correct Line request data for audio.
     *
     * @return array
     */
    private function getCorrectRequestData()
    {
        return [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'PAGE_ID',
                    'time' => 1472672934319,
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => 'USER_ID',
                            ],
                            'recipient' => [
                                'id' => 'PAGE_ID',
                            ],
                            'timestamp' => 1472672934259,
                            'message' => [
                                'mid' => 'mid.1472672934017:db566db5104b5b5c08',
                                'seq' => 297,
                                'attachments' => [
                                    [
                                        'type' => 'audio',
                                        'payload' => [
                                            'url' => 'http://lineimage.com/audio.mp3',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getRequest($responseData)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        return $request;
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = $this->getRequest($responseData);
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new LineAudioDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('LineAudio', $driver->getName());
    }

    /**
     * @test
     **/
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'PAGE_ID',
                    'time' => 1472672934319,
                    'messaging' => [
                        [
                            'sender' => [
                                'id' => 'USER_ID',
                            ],
                            'recipient' => [
                                'id' => 'PAGE_ID',
                            ],
                            'timestamp' => 1472672934259,
                            'message' => [
                                'mid' => 'mid.1472672934017:db566db5104b5b5c08',
                                'seq' => 297,
                                'attachments' => [
                                    [
                                        'type' => 'file',
                                        'payload' => [
                                            'url' => 'http://lineattachmenturl.com',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver($this->getCorrectRequestData());
        $this->assertTrue($driver->matchesRequest());
    }

    /**
     * @test
     **/
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver($this->getCorrectRequestData());
        $messages = $driver->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertEquals(1, count($messages));
        $this->assertInstanceOf(IncomingMessage::class, $messages[0]);
    }

    /** @test */
    public function it_returns_the_message_as_reference()
    {
        $driver = $this->getDriver($this->getCorrectRequestData());

        $hash = spl_object_hash($driver->getMessages()[0]);

        $this->assertSame($hash, spl_object_hash($driver->getMessages()[0]));
    }

    /**
     * @test
     **/
    public function it_returns_audio_from_request()
    {
        $driver = $this->getDriver($this->getCorrectRequestData());
        $messages = $driver->getMessages()[0];
        $audiUrls = $messages->getAudio();

        $this->assertTrue(is_array($audiUrls));
        $this->assertEquals('http://lineimage.com/audio.mp3', $audiUrls[0]->getUrl());
        $this->assertEquals([
                    'url' => 'http://lineimage.com/audio.mp3',
            ], $audiUrls[0]->getPayload());
    }
}
