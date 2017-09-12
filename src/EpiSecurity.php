<?php

class EpiSecurity {

    const PRINCIPAL_KEY = 'securityPrincipal';
    //TODO change to config and to proper val
    const SESSION_FIXATION_TIMEOUT = 12000;
    const SESSION_INACTIVE_TIMEOUT = 3072;
    const ENABLED = true;

    private $loginUrl;
    private $endpoints = array();

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

        if (array_key_exists(getRoute()->matchingRoute(), $this->endpoints)) {
            if (!$this->endpoints[getRoute()->matchingRoute()]) {
                getLogger()->info('Security: route ' . getRoute()->matchingRoute() . ' has anonymous access');
                return;
            }
        }

        if (getSession()->get(Constants::LOGGED_IN) == false) {
            getLogger()->info("User not logged in. Route: " . var_export(getRoute()->matchingRoute(), true));
            if (getRoute()->matchingRoute() == $this->loginUrl) {
                getLogger()->info('During login, not redirecting');
            } else {
                getRoute()->redirect($this->loginUrl);
            }
        }

        $this->sessionRefresh();
    }

    public function role($requiredRole, $route) {
        //TODO
    }

    public function authenticate($principal) {
        getSession()->set(EpiSecurity::PRINCIPAL_KEY, $principal);
        getSession()->set(Constants::LOGGED_IN, true);
    }

    public function logout() {
        //TODO consider unsetting
        getSession()->set(EpiSecurity::PRINCIPAL_KEY, null);
        getSession()->set(Constants::LOGGED_IN, false);
        getSession()->set(Constants::SESSION_CREATION_TS, 0);
        getSession()->set(Constants::SESSION_LAST_ACTIVE, 0);
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
            getLogger()->info("Session CRATION TS stored");
        } else if (time() - getSession()->get(Constants::SESSION_CREATION_TS) > self::SESSION_FIXATION_TIMEOUT) {
            session_regenerate_id(false);
            getLogger()->info("Session antifixation. New session ID " + session_id());
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
}

interface EpiSecurityPrincipal {
    /**
     * Unix timestamp when password expires.
     *
     * 0 expired
     * -1 never expires
     *
     * @return mixed
     */
    public function getPasswordExpiryTimestamp();
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

