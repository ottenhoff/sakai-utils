<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // Third do assignments
  if ($res3 = $t->query("SELECT * FROM $source.assignment_assignment WHERE CONTEXT='$site_id' ")) {
    $assignments = $contents = array();
    while ($row3 = $res3->fetch_object()) {
      $xml = simplexml_load_string(utf8_encode($row3->XML));
      $ac = $xml->attributes()->assignmentcontent;
      $parts = explode("/", $ac);
      $contents[] = array_pop($parts);
      $assignments[] = $row3->ASSIGNMENT_ID;
    }
    $c_sql = "'" . implode("','", $contents) . "'";
    $a_sql = "'" . implode("','", $assignments) . "'";

    var_dump("INSERT INTO $target.assignment_content SELECT * FROM $source.assignment_content WHERE CONTEXT IN ($c_sql) ");
    if(!$t->query("INSERT INTO $target.assignment_content SELECT * FROM $source.assignment_content WHERE CONTENT_ID IN ($c_sql) ")) {
      die ("ERROR: $target.assignment_content :: $site_id ::: $t->error \n");
    }
  }
}
