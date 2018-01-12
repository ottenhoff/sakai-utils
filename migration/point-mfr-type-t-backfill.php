<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites-orig.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);
  
  echo("INSERT IGNORE INTO $target.cmn_type_t SELECT * FROM $source.cmn_type_t WHERE UUID IN (SELECT TYPE_UUID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')");
  print "\n";
  if(!$t->query("INSERT IGNORE INTO $target.cmn_type_t SELECT * FROM $source.cmn_type_t WHERE UUID IN (SELECT TYPE_UUID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')")) {
      die ("ERROR: $target.mfr_area_t :: $site_id ::: $t->error \n");
  }
}
