<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // First the site tables
  $res = $t->query("SELECT * FROM sakai_site WHERE SITE_ID='$site_id'");
  if ($res && $res->num_rows === 1) {
    $tables = array('sakai_site_property');
    foreach ($tables AS $table) {
      if($t->query("INSERT INTO $target.$table SELECT * FROM $source.$table WHERE SITE_ID='$site_id'")) {
        print "Success: $target.$table from $source.$table :: $site_id ::: $t->affected_rows \n";
      }
      else {
        print "ERROR: $target.$table from $source.$table :: $site_id ::: $t->error \n";
      }
    }
  }
}

