<?php

if (count ($argv) !== 6) {
    exit ("Please include five parameters: database name, search string, date before file deletion, files location, and export destination. \n");
}

$db = $argv[1];
$search = $argv[2];
$date = $argv[3];
$dir = $argv[4];
$export = $argv[5];

$mysqli = new mysqli('localhost', 'root', '', 'aaa_archive');

if (!$mysqli) {
    exit ("Bad database connection. \n");
}

// First need to restore the database
$filename = "/mnt/massive06/b/mysql/server105-individual-increments/$db/$db.CONTENT_RESOURCE-schema.sql";
if (!is_file($filename)) {
  $filename = "/mnt/massive06/b/mysql/server106-individual-increments/$db/$db.CONTENT_RESOURCE-schema.sql";

  if (!is_file($filename)) exit ("Could not find database file");
}

// Modify the schema
$schema = explode("\n", file_get_contents($filename));
$sql = '';
foreach ($schema AS $line) {
  if (strpos($line, " KEY ") !== FALSE) continue;
  if (strpos($line, " SET ") !== FALSE) continue;
  if (strpos($line, "BINARY") !== FALSE) {
    $sql .= '  `BINARY_ENTITY` tinyint(1)';
  }
  elseif (strpos($line, "ENGINE") !== FALSE) {
    $sql .= ') ENGINE=MyISAM DEFAULT CHARSET=utf8;';
  }
  else {
    $sql .= $line . "\n";
  }
}

// Drop the existing table if it exists
$ret = $mysqli->query("DROP TABLE IF EXISTS CONTENT_RESOURCE");
$ret = $mysqli->query($sql) or die(mysqli_error($mysqli));

// Restore the CONTENT_RESOURCE TABLE
$filename = str_replace("-schema", "", $filename);
print "Starting rdiff restore of $filename \n";
exec ("rdiff-backup -r \"$date\" $filename /tmp/cr-$db.sql ");
print "Loading restored $filename into aaa_archive db \n";
exec ("mysql -u root aaa_archive < /tmp/cr-$db.sql");
$res = $mysqli->query("SELECT count(*) AS cnt FROM CONTENT_RESOURCE");
$obj = $res->fetch_object();
print "Restored $obj->cnt rows into CONTENT_RESOURCE table \n";

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

  if (is_file ($dest)) {
      echo ("File $dest already exists \n");
      continue;
  }
  elseif (!is_file ($file)) {
      echo ("File missing attempting to retrieve: rdiff-backup -r \"$date\" $file \"$dest\" \n");
      exec ("rdiff-backup -r \"$date\" $file \"$dest\" ");
      sleep (2);
      continue;
  }
  else {
    copy ($file, $dest);
  }

}

