<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  if ($site_id === 'NH-CH501-502D-1-SP15') continue;

  if ($res3 = $t->query("SELECT * FROM $source.assignment_assignment WHERE CONTEXT='$site_id' ")) {
    $assignments = $contents = array();
    while ($row3 = $res3->fetch_object()) {
      $assignments[] = $row3->ASSIGNMENT_ID;
    }
    $a_sql = "'" . implode("','", $assignments) . "'";

    if (!$t->query("DELETE FROM $target.assignment_submission WHERE `CONTEXT` IN ($a_sql) AND ((GRADED LIKE '%xml%' AND `XML` NOT LIKE '%xml%') OR (`SUBMITTED`='false' AND `GRADED`='false')) ")) {
      die ("ERROR: DELETE FROM $target.assignment_submission WHERE CONTEXT IN ($a_sql) AND ((GRADED LIKE '%xml%' AND `XML` NOT LIKE '%xml%') OR (SUBMITTED='false' AND GRADED='false')) :: $t->error \n");
    }

    if(!$t->query("INSERT IGNORE INTO $target.assignment_submission SELECT SUBMISSION_ID,CONTEXT,XML,SUBMITTER_ID,SUBMIT_TIME,SUBMITTED,GRADED FROM $source.assignment_submission WHERE CONTEXT IN ($a_sql) ")) {
      die ("ERROR: $target.assignment_submission :: $site_id ::: $t->error \n");
    }

  }
}
