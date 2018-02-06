<?php

class EpiSecurity {

    const PRINCIPAL_KEY = 'securityPrincipal';
    //TODO change to config and to proper val
    const SESSION_FIXATION_TIMEOUT = 12000;
    const SESSION_INACTIVE_TIMEOUT = 3072;
    const ENABLED = true;

    private $loginUrl;
    private $endpoints = array();
    private $endpointRoles = array();
    private $endpointRoleMethods = array();

    /**
     * EpiSecurity constructor.
     * @param $loginUrl
     */
    public function __construct() {
        //TODO check if exists loginPath settigns
        $this->loginUrl = Epi::getSetting('loginPath');
    }

    public function check() {
        if (!self::ENABLED) {
            return;
        }

        $this->sessionAntiFixation();
        $this->sessionKillTimeouted();

        $route = getRoute()->matchingRoute();
        $method = getRoute()->requestMethod();
//        getLogger()->info($route);

        if (array_key_exists($route, $this->endpoints)) {
            if (!$this->endpoints[$route]) {
                getLogger()->info('Security: route ' . $route . ' has anonymous access');
                return;
            }
        }

        if (getSession()->get(Constants::LOGGED_IN) == false) {
            getLogger()->info("User not logged in. Route: " . var_export($route, true));
            if ($route == $this->loginUrl) {
                getLogger()->info('During login, not redirecting');
            } else {

                if (getApi()->isExternalApi($route)) {
                    getLogger()->info("external api " . $route);

                    getRoute()->respondWihCode(401, "Please login");
                } else {
                    getRoute()->redirect($this->loginUrl);
                }

            }
        }

        if (array_key_exists($route, $this->endpointRoles)) {
            $role = $this->endpointRoles[$route];
            $securedHttpMethod = isset($this->endpointRoleMethods[$route]) ? $this->endpointRoleMethods[$route] : null;

            if ($method == $securedHttpMethod && !in_array($role, $this->getPrincipal()->getRoles())) {
                getRoute()->respondWihCode(401, "Insufficient ROLE to access this document");
            }
        }

        $this->sessionRefresh();
    }

    public function role($requiredRole, $route, $httpMethod = null) {
        $this->endpointRoles[$route] = $requiredRole;
        //TODO fix this!
        if ($httpMethod != null) {
            $httpMethod = strtoupper($httpMethod);
            if (!in_array($httpMethod, array('GET', 'POST', 'PUT', 'DELETE'))) {
                EpiException::raise(new EpiException("Wrong HTTP Request method {$httpMethod}"));
            }
            $this->endpointRoleMethods[$route] = $httpMethod;
        }
    }

    public function authenticate(EpiSecurityPrincipal $principal) {
        getLogger()->info("Authenticating user ");
        getSession()->set(EpiSecurity::PRINCIPAL_KEY, $principal);
        getSession()->set(Constants::LOGGED_IN, true);
    }

    public function logout() {
        getSession()->destroy();
    }

    public function getPrincipal() {
        return (getSession()->contains(EpiSecurity::PRINCIPAL_KEY)) ? getSession()->get(EpiSecurity::PRINCIPAL_KEY) : null;
    }

    public function configEndpoint($route, $secured = true) {
        $this->endpoints[$route] = $secured;
    }

    private function sessionAntiFixation() {
        if (!getSession()->contains(Constants::SESSION_CREATION_TS)) {
            getSession()->set(Constants::SESSION_CREATION_TS, time());
            getLogger()->info("Session CREATION TS stored");
        } else if (time() - getSession()->get(Constants::SESSION_CREATION_TS) > self::SESSION_FIXATION_TIMEOUT) {
            session_regenerate_id(false);
            getLogger()->info("Session antifixation. New session ID " . session_id());
            getSession()->set(Constants::SESSION_CREATION_TS, time());
        }
    }

    /**
     * If session is timeouted (inactive), will kill it logging out
     */
    private function sessionKillTimeouted() {
        if (!getSession()->contains(Constants::SESSION_LAST_ACTIVE)) {
            return;
        }
        if (time() > getSession()->get(Constants::SESSION_LAST_ACTIVE) + EpiSecurity::SESSION_INACTIVE_TIMEOUT) {
            $this->logout();
        }
    }

    /**
     * Refreshes session.
     *
     * Put last active time to current time preventing logging out on inactivity.
     */
    private function sessionRefresh() {
        getSession()->set(Constants::SESSION_LAST_ACTIVE, time());
    }

    public function requireRole($string) {
    }
}

interface EpiSecurityPrincipal {

    /**
     * User id.
     *
     * @return mixed
     */
    public function getId();

    /**
     * Unix timestamp when password expires.
     *
     * 0 expired
     * -1 never expires
     *
     * @return mixed
     */
    public function getPasswordExpiryTimestamp();

    /**
     * Returns array of roles.
     *
     * Each role is a String.
     *
     * @return array Roles of type String
     */
    public function getRoles();
}

function getSecurity()
{
    static $security;
    if (!$security) {
        $security = new EpiSecurity();
    }

//    $security->check();

    return $security;
}

