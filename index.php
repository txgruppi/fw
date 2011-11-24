<?php

define('BASE_PATH', dirname(__FILE__));
define('LIB_PATH', BASE_PATH . '/lib');

require_once LIB_PATH . '/fw.php';
require_once LIB_PATH . '/PublicArea.php';
require_once LIB_PATH . '/MemberArea.php';

FW::add('PublicArea');
FW::add('MemberArea');
FW::run();
