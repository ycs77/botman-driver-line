<?php

namespace BotMan\Drivers\Line\Events;

class MessagingOptins extends LineEvent
{
    /**
     * Return the event name to match.
     *
     * @return string
     */
    public function getName()
    {
        return 'messaging_optins';
    }
}
