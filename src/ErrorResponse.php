<?php

class ErrorResponse {
    public $message;
    public $code;

    /**
     * ResponseError constructor.
     * @param $message
     * @param $code
     */
    public function __construct($message, $code)
    {
        $this->message = $message;
        $this->code = $code;
    }
}
