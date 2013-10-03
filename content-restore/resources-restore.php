<?php

if (count ($argv) !== 6) {
    exit ("Please include five parameters: database name, search string, date before file deletion, files location, and export destination. \n");
}

$db = $argv[1];
$search = $argv[2];
$date = $argv[3];
$dir = $argv[4];
$export = $argv[5];

$mysqli = new mysqli('localhost', 'root', '', $db);

if (!$mysqli) {
    exit ("Bad database connection. \n");
}

$search = $mysqli->real_escape_string ($search);
$res = $mysqli->query("SELECT * FROM CONTENT_RESOURCE WHERE RESOURCE_ID LIKE '%$search%' ");

while ($row = $res->fetch_object()) {
  if (empty($row->FILE_PATH)) {
    print "Empty file path : ";
    print_r($row);
    continue;
  }

  $file = $dir . $row->FILE_PATH;

  $slashes = substr_count ($row->RESOURCE_ID, "/");
  $arr = explode ("/", $row->RESOURCE_ID);

  // get rid of first couple parts of the dir
  array_shift ($arr);
  array_shift ($arr);

  $dest = $export . '/' . implode ("/", $arr);
  $path = pathinfo ($dest);

  if (!is_dir ($path['dirname'])) {
      $ret = mkdir ($path['dirname'], 0775, true);
      if (!$ret) exit ("Incorrect permissions");
  }

  if (!is_file ($file)) {
      echo ("File missing attempting to retrieve: rdiff-backup -r \"$date\" $file \"$dest\" \n");
      exec ("rdiff-backup -r \"$date\" $file \"$dest\" ");
      sleep (2);
      continue;
  }
  else {
    copy ($file, $dest);
  }

}

