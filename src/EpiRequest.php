<?php

class EpiRequest {

    public const APP_JSON = 'application/json';
    public const FORM_URL_ENCODED = 'application/x-www-form-urlencoded';

    private static $instance;

    public static function getInstance() {
        if(self::$instance) {
            return self::$instance;
        }

        self::$instance = new EpiRequest();
        return self::$instance;
    }

    public function get($class, $containingParam = null) {
        $data = $this->getData();

        if ($containingParam && $data && is_array($data) && array_key_exists($containingParam, $data) && is_array($data[$containingParam])) {
            $data = $data[$containingParam];
        }

        $obj = $this->initFrom($data, $class);

        return $obj;
    }

    public function getArrayOf($class) {

        $data = $this->getData();

        if (!is_array($data)) {
            throw new Exception("Data is not an array.");
        }

        $resp = array();
        foreach ($data as $param) {
            $obj = $this->initFrom($param, $class);
            if ($obj) {
                $resp[] = $obj;
            }
        }

        return $resp;
    }

    private function getData() {
        if ($this->contentType() == self::APP_JSON) {
            return $this->getJsonData();
        } else if ($this->contentType() == self::FORM_URL_ENCODED) {
            return $this->getFormData();
        }

        else {
            throw new Exception("Not supported type " . $this->contentType());
        }
    }

    private function getFormData() {
        return $_POST;
    }

    private function getJsonData() {
        if ($this->contentType() == self::APP_JSON) {
            return json_decode(file_get_contents('php://input'), true);
        } else {
            throw new Exception("Not supported type " . $this->contentType());
        }
    }

    public function getParam($reqParam) {
        if (isset($_REQUEST[$reqParam])) {
            return $_REQUEST[$reqParam];
        }

        if ($this->contentType() == self::APP_JSON) {
            $data = json_decode(file_get_contents('php://input'), true);

            if (array_key_exists($reqParam, $data)) {
                return $data[$reqParam];
            }
        }

        return null;
    }

    private function initFrom($data, $class) {
        $log = getLogger(__CLASS__);
        if ($data == null) {
            return null;
        }
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
//TODO tunded off because login and password were logged
//        $log->debug("initializing " . $class . ' with args ' . var_export($args, true));
        $obj = $refl->newInstanceArgs($args);

        $fixItf = 'FieldFix';
        if ($obj instanceof $fixItf) {
            $obj->fix();
        }

        return $obj;
    }

    public function contentType() {
        //content type can contain charset, currently not supporting this info, only trimming it

            if (isset($_SERVER["CONTENT_TYPE"])) {
            $ct = explode(';', $_SERVER["CONTENT_TYPE"]);
            return trim($ct[0]);
        }

        return null;
    }

    public function getPath() {
        return $_SERVER['REQUEST_URI'];
    }
}

function getRequest() {
    return EpiRequest::getInstance();
}
