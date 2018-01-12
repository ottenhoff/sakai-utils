<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // Sixth the content
  if(!$t->query("INSERT IGNORE INTO $target.content_collection SELECT * FROM $source.content_collection WHERE COLLECTION_ID LIKE '%$site_id%' ")) {
    die ("ERROR: $target.content_collection :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.content_resource SELECT RESOURCE_ID, RESOURCE_UUID, IN_COLLECTION, FILE_PATH, XML, BINARY_ENTITY, FILE_SIZE, CONTEXT, RESOURCE_TYPE_ID
     FROM $source.content_resource WHERE RESOURCE_ID LIKE '%$site_id%' ")) {
    die ("ERROR: $target.content_resource :: $site_id ::: $t->error \n");
  }
  if($file_res = $t->query("SELECT FILE_PATH FROM $source.content_resource WHERE RESOURCE_ID LIKE '%$site_id%' ")) {
    while ($file_row = $file_res->fetch_object()) {
      $files[] = $file_row->FILE_PATH;
    }
  }
  var_dump($site_id);
}
