<?php

class EpiRequest {

    public const APP_JSON = 'application/json';

    private static $instance;

    public static function getInstance() {
        if(self::$instance) {
            return self::$instance;
        }

        self::$instance = new EpiRequest();
        return self::$instance;
    }

    public function get($class) {
        if ($this->contentType() == self::APP_JSON) {
            $data = json_decode(file_get_contents('php://input'), true);
        } else {
            throw new Exception("Not supported type " . $this->contentType());
        }

        $obj = $this->initFrom($data, $class);

        return $obj;
    }

    private function initFrom($data, $class) {
        $log = getLogger(__CLASS__);
        $refl = new ReflectionClass($class);
        if (!$refl->isInstantiable()) {
            throw new Exception($class . ' is not instantable');
        }

        $args = [];
        $constr = $refl->getConstructor();
        if ($constr) {
            foreach ($constr->getParameters() as $p) {
                $args[] = isset($data[$p->getName()]) ? $data[$p->getName()] : null;
            }
        }

        $log->debug("initializing " . $class . ' with args ' . var_export($args, true));
        $obj = $refl->newInstanceArgs($args);

        return $obj;
    }

    public function contentType() {
        return isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    }

}

function getRequest() {
    return EpiRequest::getInstance();
}
