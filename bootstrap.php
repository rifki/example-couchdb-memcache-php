<?php

// lib php couchdb
require 'lib/couch.php';
require 'lib/couchClient.php';
require 'lib/couchDocument.php';

// config couchdb
define('COUCH_DSN', 'http://127.0.0.1:5984');
define('COUCH_DBNAME', 'testcase_blog');

// config memcache
define('MEM_HOST', '127.0.0.1');
define('MEM_PORT', 11211);
