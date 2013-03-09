<?php

class MemberArea {

  protected $auth = null;

  public function beforeAction($matches, $callback) {
    if ($callback['name'] == 'login')
      return;
    if ($this->auth === null) {
      session_start();
      FW::set('auth', $this->auth = isset($_SESSION['member']));
    }
    if (!$this->auth) {
      header('Location: ' . FW::baseUrl() . '/index.php/member/signin');
      FW::$stop = true;
      return;
    }
  }

  /**
   * @route * /member
   * @route * /member/home
   */
  public function getIndex() {
    echo FW::render('layouts/member', 'member/index');
  }

  /**
   * @route * /member/signin(/error)?
   */
  public function login($matches, $callback) {
    $login = FW::param('login');
    $password = FW::param('password');

    if (isset($_POST['login']) && isset($_POST['password'])) {
      if ($login == 'sample' && $password == 'sample') {
        session_start();
        $_SESSION['member'] = true;
        header('Location: ' . FW::baseUrl() . '/index.php/member');
      } else
        header('Location: ' . FW::baseUrl() . '/index.php/member/signin/error');
      FW::$stop = true;
      return;
    }

    $error = isset($matches[1]);

    echo FW::render('layouts/member', 'member/signin', array(
      'login' => $login,
      'password' => $password,
      'error' => $error,
    ));
  }

  /**
   * @route * /member/signout
   */
  public function signout() {
    session_start();
    unset($_SESSION['member']);
    header('Location: ' . FW::baseUrl() . '/index.php/member');
    FW::$stop = true;
    return;
  }

}
