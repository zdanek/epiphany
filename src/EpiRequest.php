<?php

class EpiRequest {
    private static $instance;

    public static function getInstance() {
        if(self::$instance) {
            return self::$instance;
        }

        self::$instance = new EpiRequest();
        return self::$instance;
    }

    public function from($array, $class) {
        $obj = new $class();

        return $obj;
    }

}

function getRequest() {
    return EpiRequest::getInstance();
}
