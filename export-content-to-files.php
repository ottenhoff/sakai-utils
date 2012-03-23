<?php

if (count ($argv) !== 4) {
    exit ("Please include three parameters: database name, files location, and export destination. \n");
}

$db = $argv[1];
$dir = $argv[2];
$export = $argv[3];

$mysqli = new mysqli('localhost', 'root', '', $db);

if (!$mysqli) {
    exit ("Bad database connection. \n");
}

// build a map of sites
$res = $mysqli->query("SELECT SITE_ID, TITLE FROM SAKAI_SITE WHERE TYPE IS NOT NULL");

$search = array();
$replace = array();
while ($row = $res->fetch_object()) {
    $search[] = $row->SITE_ID;
    $replace[] = str_replace ("--", "-", str_replace ("--", "-", str_replace (" ", "-", $row->TITLE)));
}

// build a map of users
$res = $mysqli->query("SELECT USER_ID, EID FROM SAKAI_USER_ID_MAP");

$user_search = array();
$user_replace = array();
while ($row = $res->fetch_object()) {
    $user_search[] = $row->USER_ID;
    $user_replace[] = $row->EID;
}


$res = $mysqli->query("SELECT * FROM CONTENT_RESOURCE");

while ($row = $res->fetch_object()) {
  $file = $dir . $row->FILE_PATH;

  $dest = $export . str_replace ($user_search, $user_replace, str_replace ($search, $replace, $row->RESOURCE_ID));
  $path = pathinfo ($dest);

  if (!is_dir ($path['dirname'])) {
      mkdir ($path['dirname'], 0775, true);
  }

  if (!is_file ($file)) {
      echo ("File missing: $file \n");
      continue;
  }


  copy ($file, $dest);
}

