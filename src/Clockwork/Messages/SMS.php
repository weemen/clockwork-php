<?php

namespace Clockwork\Messages;


use Clockwork\Exceptions\InvalidArgumentException;
use \Clockwork\Handlers\SMS as SMSHandler;

class SMS {

    /**
     * Phonenumber of the recipient
     * @var numeric
     */
    protected $toPhoneNumber;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var SMSHandler
     */
    protected $handler;

    public function __construct($toPhonenumber = null, $message = null)
    {
        if (null !== $toPhonenumber && is_numeric($toPhonenumber)) {
            $this->setToPhoneNumber($toPhonenumber);
        }

        if (null !== $message && is_string($message)) {
            $this->setMessage($message);
        }
    }

    /**
     * @param $message
     * @throws \Clockwork\Exceptions\InvalidArgumentException
     */
    public function setMessage($message)
    {
        if (!is_string($message)) {
            throw new InvalidArgumentException("SMS message has to be a string");
        }
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param $toPhoneNumber
     * @throws \Clockwork\Exceptions\InvalidArgumentException
     */
    public function setToPhoneNumber($toPhoneNumber)
    {
        if (!is_string($toPhoneNumber)) {
            throw new InvalidArgumentException("Phonenumber is not numberic");
        }
        $this->toPhoneNumber = $toPhoneNumber;
    }

    /**
     * @return string
     */
    public function getToPhoneNumber()
    {
        return $this->toPhoneNumber;
    }

    /**
     * @param SMSHandler $handler
     */
    public function setHandler(SMSHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @return SMSHandler
     */
    public function getHandler()
    {
        if (null === $this->handler) {
            $this->setHandler(new SMSHandler());
        }
        return $this->handler;
    }


} 