<?php

class EpiErrorResponseException extends EpiException {

    public $error;

    /**
     * EpiErrorResponseException constructor.
     * @param $error
     */
    public function __construct(ErrorResponse $error) {
        parent::__construct($error->message);
        $this->error = $error;
    }

    /**
     * @return ErrorResponse
     */
    public function getError(): ErrorResponse {
        return $this->error;
    }

}
