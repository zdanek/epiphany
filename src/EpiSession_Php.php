<?php
class EpiSession_Php implements EpiSessionInterface
{
  public function end()
  {
    $_SESSION = array();

    if (isset($_COOKIE[session_name()])) {
        //for security sake, setting time in the past, so browser will clean this cookie
        setcookie(session_name(), '', time()-42000, '/');
    }

    session_destroy();
  }

  public function get($key = null)
  {
    if(empty($key) || !isset($_SESSION[$key]))
      return false;

    return $_SESSION[$key];
  }

  public function set($key = null, $value = null)
  {
    if(empty($key))
      return false;

    $_SESSION[$key] = $value;
    return $value;
  }

  public function contains($key = null)
  {
    return isset($_SESSION[$key]);
  }

    public function __construct()
  {
    if (!session_id())
      session_start();
  }

  public function getId()
  {
      return session_id();
  }

  public function destroy()
  {
      session_destroy();
  }

  public function unset($key)
  {
      if ($this->contains($key)) {
          unset($_SESSION[$key]);
      }
  }

    private function getSessionTimeout() {

        $timeoutSetting = Epi::getSetting(self::SESSION_INACTIVE_TIMEOUT_KEY);
        return $timeoutSetting ? $timeoutSetting : EpiSecurity::SESSION_INACTIVE_TIMEOUT;
    }

}
