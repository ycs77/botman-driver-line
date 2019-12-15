<?php

namespace Tests\Drivers;

use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Line\Extensions\User;

class LineUserTest extends PHPUnit_Framework_TestCase
{
    public function createTestUser()
    {
        $userInfo = [
            'id' => '1234',
            'displayName' => 'Lucas',
            'userId' => '1234',
            'pictureUrl' => 'http://profile.com/pic',
            'statusMessage' => 'Hello world!',
        ];

        $user = new User(
            '1234',
            'Lucas',
            null,
            'Lucas',
            $userInfo
        );

        return $user;
    }

    public function testFirstName()
    {
        $user = $this->createTestUser();

        $this->assertSame('Lucas', $user->getFirstName());
    }

    public function testLastName()
    {
        $user = $this->createTestUser();

        $this->assertNull($user->getLastName());
    }

    public function testUsername()
    {
        $user = $this->createTestUser();

        $this->assertSame('Lucas', $user->getUsername());
    }

    public function testPicture()
    {
        $user = $this->createTestUser();

        $this->assertSame('http://profile.com/pic', $user->getPicture());
    }

    public function testStatusMessage()
    {
        $user = $this->createTestUser();

        $this->assertSame('Hello world!', $user->getStatusMessage());
    }
}
