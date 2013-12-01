<?php

namespace Clockwork\Handlers;


class SMS {

    /**
     * From address used on text messages
     * @var string (11 characters or 12 numbers)
     */
    protected $from;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * Allow long SMS messages (Cost up to 3 credits)
     * @var bool
     */
    protected $long;

    /**
     * Truncate message text if it is too long
     * @var bool
     */
    protected $truncate;

    /**
     * What Clockwork should do if you send an invalid character
     *
     * Possible values:
     *      'error'     - Return an error (Messasge is not sent)
     *      'remove'    - Remove the invalid character(s)
     *      'replace'   - Replace invalid characters where possible, remove others
     */
    protected $invalidCharAction;

    /**
     * @param string $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        if (null === $this->clientId) {
            $this->setClientId("");
        }

        return $this->clientId;
    }

    /**
     * @return bool
     */
    public function hasClientId()
    {
        $clientId = $this->getClientId();
        return !empty($clientId);
    }

    /**
     * @param string $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * From address used on text messages
     * @return string
     */
    public function getFrom()
    {
        if (null === $this->from) {
            $this->setForm("");
        }
        return $this->from;
    }

    /**
     * @return bool
     */
    public function hasFrom()
    {
        $from = $this->getFrom();
        return !empty($from);
    }

    /**
     * @param mixed $invalidCharAction
     */
    public function setInvalidCharAction($invalidCharAction)
    {
        $this->invalidCharAction = $invalidCharAction;
    }

    /**
     * @return mixed
     */
    public function getInvalidCharAction()
    {
        if (null === $this->invalidCharAction) {
            $this->setInvalidCharAction("");
        }

        return $this->invalidCharAction;
    }

    /**
     * @return bool
     */
    public function hasInvalidCharAction()
    {
        $invalidCharAction = $this->getInvalidCharAction();
        return !empty($invalidCharAction);
    }

    /**
     * @param boolean $long
     */
    public function setLong($long)
    {
        $this->long = $long;
    }

    /**
     * @return boolean
     */
    public function getLong()
    {
        if (null === $this->long) {
            $this->setLong(false);
        }

        return $this->long;
    }

    /**
     * @return bool
     */
    public function hasLong()
    {
        return $this->getLong();
    }

    /**
     * @param boolean $truncate
     */
    public function setTruncate($truncate)
    {
        $this->truncate = $truncate;
    }

    /**
     * @return boolean
     */
    public function getTruncate()
    {
        if (null === $this->truncate) {
            $this->setTruncate(false);
        }

        return $this->truncate;
    }

    /**
     * @return bool
     */
    public function hasTruncate()
    {
        return $this->getTruncate();
    }
} 