<?php

class EpiNotAuthorizedException extends EpiException {

    public $error;

    /**
     * EpiNotAuthorizedException constructor.
     * @param $error
     */
    public function __construct($error) {
        $this->error = $error;
    }


}
