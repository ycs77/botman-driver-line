<?php

namespace BotMan\Drivers\Line\Extensions;

use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Users\User as BotManUser;

class User extends BotManUser implements UserInterface
{
    /**
     * @var array
     */
    protected $user_info;

    public function __construct(
        $id = null,
        $first_name = null,
        $last_name = null,
        $username = null,
        array $user_info = []
    ) {
        $this->id = $id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->username = $username;
        $this->user_info = (array) $user_info;
    }

    /**
     * @return string
     */
    public function getPicture()
    {
        return $this->user_info['pictureUrl'] ?? '';
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->user_info['statusMessage'] ?? '';
    }
}
