<?php

class PublicArea {

  /**
   * @route * /
   * @route * /home
   */
  public function getIndex() {
    echo FW::render('layouts/public', 'public/index');
  }

  /**
   * @route GET /contact
   */
  public function getContact() {
    echo FW::render('layouts/public', 'public/contact-form');
  }

  /**
   * @route POST /contact
   */
  public function postContact() {
    $name = FW::param('name');
    $message = FW::param('message');

    echo FW::render('layouts/public', 'public/contact', array(
      'name' => $name,
      'message' => $message,
    ));
  }

  /**
   * @route HTTP_STATUS 404
   */
  public function error404() {
    echo FW::render('layouts/error', 'error/404');
  }
  
  /**
   * @route * /static/([a-z0-9_-]+)
   */
  public function staticPage($matches, $callback) {
    $page = FW::getIndex($matches, 1);
    $path = BASE_PATH . '/views/static/' . $page . '.php';
    if (empty($page) || !file_exists($path)) {
      FW::callHttpStatus(404);
      return false;
    }
    echo FW::render('layouts/public', 'static/' . $page);
  }

}
