<?php
require "migration-helper.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  if($file_res = $t->query("SELECT FILE_PATH FROM $source.content_resource WHERE RESOURCE_ID LIKE '%$site_id%' ")) {
    while ($file_row = $file_res->fetch_object()) {
      $files[] = $file_row->FILE_PATH;
    }
  }
}

file_put_contents('/tmp/files-rsync.txt', implode("\n", $files));
