<?php

class EpiSecurity {

    const PRINCIPAL_KEY = 'securityPrincipal';
    //TODO change to config and to proper val
    const SESSION_FIXATION_TIMEOUT = 12000;
    const SESSION_INACTIVE_TIMEOUT = 3072;
    const ENABLED = true;

    private $loginUrl;
    private $endpointsSecured = array();
    private $endpointsSecuredRegex = array();
    private $endpointRoles = array();
    private $endpointRoleMethods = array();

    /**
     * EpiSecurity constructor.
     * @param $loginUrl
     */
    public function __construct() {
        $this->loginUrl = Epi::getSetting('loginPath');
        if (!$this->loginUrl) {
            EpiException::raise(new EpiException("loginUrl not set in settings"));
        }
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

        if ($this->hasAnonymousAccess($route)) {
            return;
        }

        getLogger()->debug('This route is secured. Checking if user is logged in');

        if (getSession()->get(Constants::LOGGED_IN) == false) {
            getLogger()->info("User not logged in. Route: " . var_export($route, true));
            getLogger()->debug('Checking if current url is loginUrl');
            if ($route == $this->loginUrl) {
                getLogger()->info('During login, not redirecting');
            } else {

                if (getApi()->isExternalApi($route)) {
                    getLogger()->info("external api " . $route);

                    getRoute()->respondWithCode(401, "Please login");
                } else {
                    getLogger()->info('Current URL is not logging url. Redirecting to loggin url');
                    $this->setRedirectionAfterLogin();
                    getRoute()->redirect($this->loginUrl);
                }

            }
        }
        if (array_key_exists($route, $this->endpointRoles)) {

            $role = $this->endpointRoles[$route];
            $securedHttpMethod = isset($this->endpointRoleMethods[$route]) ? $this->endpointRoleMethods[$route] : 'ALL';
getLogger()->info('sec meth ' . $securedHttpMethod . ', role ' . $role . ' // user roles: ' . var_export($this->getPrincipal()->getRoles(), true));
            if ((($securedHttpMethod != null && $method == $securedHttpMethod) || ($securedHttpMethod == 'ALL'))  && !in_array($role, $this->getPrincipal()->getRoles())) {
                getRoute()->respondWithCode(401, "Insufficient ROLE to access this document");
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
        getLogger()->info("Authenticating user " . $principal->getId());

        getSession()->set(EpiSecurity::PRINCIPAL_KEY, $principal);
        getSession()->set(Constants::LOGGED_IN, true);
    }

    public function logout() {
        getSession()->destroy();
    }

    public function getPrincipal() : ?EpiSecurityPrincipal {
        return (getSession()->contains(EpiSecurity::PRINCIPAL_KEY)) ? getSession()->get(EpiSecurity::PRINCIPAL_KEY) : null;
    }

    public function isLoggedIn() {
        return getSession()->contains(Constants::LOGGED_IN) && getSession()->get(Constants::LOGGED_IN);
    }

    public function configEndpoint($route, $secured = true) {
        $this->endpointsSecured[$route] = $secured;
    }

    public function configEndpointRegex($routeRegex, $secured = true) {
        $route = "#^{$routeRegex}\$#";
        $this->endpointsSecuredRegex[$route] = $secured;
    }

    public function getRedirectionUrlAfterLogin() {
        return getSession()->get(Constants::REDIRECT_AFTER_LOGIN);
    }

    private function hasAnonymousAccess($route) {

        if (Epi::getSetting('404Path') && $route == Epi::getSetting('404Path')) {
            return true;
        }

        if (array_key_exists($route, $this->endpointsSecured)) {
            if (!$this->endpointsSecured[$route]) {
                getLogger()->info('Security: route ' . $route . ' has anonymous access');
                return true;
            }
        }

        foreach ($this->endpointsSecuredRegex as $endpointRegex => $isSecured) {
            if (preg_match($endpointRegex, $route)) {
                return !$isSecured;
            }
        }
        return false;
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

    private function setRedirectionAfterLogin() {
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        if ($httpMethod == 'GET') {
            getSession()->set(Constants::REDIRECT_AFTER_LOGIN, $_SERVER['REQUEST_URI']);
        } else {

        }
        getSession()->set(Constants::REDIRECT_AFTER_LOGIN, $_SERVER['REQUEST_URI']);
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

function getSecurity() : EpiSecurity
{
    static $security;
    if (!$security) {
        $security = new EpiSecurity();
    }

//    $security->check();

    return $security;
}

