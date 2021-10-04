<?php

/**
 * Class to add additional information to exceptions.
 */
class MobbexException extends \Exception
{
    public $data = '';

    /**
     * Constructor.
     * 
     * @param string $message 
     * @param string $code
     * @param mixed $data
     */
    public function __construct($message = '', $code = 0, $data = '')
    {
        $this->data = $data;
        parent::__construct($message, $code);
    }
}