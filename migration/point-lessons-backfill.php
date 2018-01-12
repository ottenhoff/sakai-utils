<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites-orig.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // Lessons
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_pages SELECT * FROM $source.lesson_builder_pages WHERE siteId='$site_id'")) {
    die ("ERROR: $target.lesson_builder_pages :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_groups SELECT * FROM $source.lesson_builder_groups WHERE siteId='$site_id'")) {
    die ("ERROR: $target.lesson_builder_groups :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_items SELECT * FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_items SELECT * FROM $source.lesson_builder_items WHERE type=2 AND pageId=0 AND sakaiId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_student_pages SELECT * FROM $source.lesson_builder_student_pages WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_student_pages :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_comments SELECT * FROM $source.lesson_builder_comments WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_comments :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_p_eval_results SELECT * FROM $source.lesson_builder_p_eval_results WHERE PAGE_ID IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_p_eval_results :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_q_responses SELECT * FROM $source.lesson_builder_q_responses WHERE questionId IN
    (SELECT id FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id'))")) {
    die ("ERROR: $target.lesson_builder_q_responses :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_qr_totals SELECT * FROM $source.lesson_builder_qr_totals WHERE questionId IN
    (SELECT id FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id'))")) {
    die ("ERROR: $target.lesson_builder_qr_totals :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.lesson_builder_ch_status SELECT * FROM $source.lesson_builder_ch_status WHERE checklistId IN
    (SELECT id FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id'))")) {
    die ("ERROR: $target.lesson_builder_ch_status :: $site_id ::: $t->error \n");
  }

  var_dump($site_id);
}

