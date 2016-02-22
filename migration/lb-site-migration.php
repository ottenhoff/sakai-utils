<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("lb-sites.csv", "r");


$lb_pages_plus = $new_values['lesson_builder_pages'];
$lb_items_plus = $new_values['lesson_builder_items'];
$lb_items_cur = 6700000;

$lb_comments_plus = $new_values['lesson_builder_comments'];
$lb_log_plus = $new_values['lesson_builder_log'];
$lb_properties_plus = $new_values['lesson_builder_properties'];

$sam_pub_plus = $new_values['sam_publishedassessment_t'];
$msg_topic_plus = $new_values['mfr_topic_t'];

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // LB Tables
  /*
   * 'lesson_builder_comments',
   * 'lesson_builder_items',
   * 'lesson_builder_log',
   * 'lesson_builder_properties',
   * 'lesson_builder_pages',
   *
   *
   *
   * 'lesson_builder_q_responses',
   * 'lesson_builder_qr_totals',
   * 'lesson_builder_p_eval_results',
   * 'lesson_builder_student_pages',
   * 'lesson_builder_groups',
   */

  //lesson_builder_groups are not used in this instance so ignored for now
  //lesson_builder_student_pages are not used in this instance so ignored for now
  //lesson_builder_p_eval_results are not used in this instance so ignored for now

  //lesson_builder_items->gradebookId is not changed since all values were null in this instance
  //lesson_builder_q_responses,lesson_builder_qr_totals haven't been updated in 6 months and don't contain many values so ignored for now

  if(!$t->query("INSERT INTO $target.lesson_builder_pages SELECT pageId+$lb_pages_plus, toolId, siteId, title, parent+$lb_pages_plus,topParent+$lb_pages_plus,hidden,releaseDate,gradebookPoints,owner,groupOwned,cssSheet,groupid
     FROM $source.lesson_builder_pages WHERE siteId = '$site_id' ")) {
    die ("ERROR: $target.lesson_builder_pages :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT INTO $target.lesson_builder_items SELECT id+$lb_items_plus, pageId+$lb_pages_plus, sequence,type,sakaiId,name,html,description,height,width,alt,nextPage+$lb_pages_plus,format,required,alternate,prerequisite,subrequirement,requirementText,sameWindow,groups,anonymous,showComments,forcedCommentsAnonymous,gradebookId,gradebookPoints,gradebookTitle,altGradebook,altPoints,altGradebookTitle,showPeerEval,groupOwned,ownerGroups,attributeString 
     FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT INTO $target.lesson_builder_comments SELECT id+$lb_comments_plus,itemId+$lb_items_plus, pageId+$lb_pages_plus,timePosted,author,commenttext,UUID,html,points
    FROM $source.lesson_builder_comments WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_comments :: $site_id ::: $t->error \n");
  }

  print "Done with $site_id \n";
  sleep(15);
}
  print ".............................. \n";
  sleep(15);

  //Now need to do some special work to update SakaiID's!
  //Some patterns
  //'/assignment/00a4dd45-4165-438e-ab86-87c90752ca86' - Nothing
  //'/group/03aa044a-f9ab-45ed-84a9-d48259692d25/Videos' -Nothing
  //'/user/5c0b5ef3-56bf-4913-95ff-825525c526dc/stuff4/' - Nothing
  //'/dummy' - Nothing

  //Numeric --  '/sam_pub/1001' add $lb_pages_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = sakaiId+$lb_pages_plus where id >= $lb_items_plus and id < $lb_items_cur and sakaiId REGEXP '^[0-9]+$'")) {
    die ("ERROR1: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //'/forum_topic/10586' - Have to update to forum_topic_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = concat ('/forum_topic/', cast(right(sakaiId, locate('/forum_topic/',sakaiId))+$msg_topic_plus as CHAR)) where id >= $lb_items_plus and id < $lb_items_cur and sakaiId like '/forum_topic/%'")) {
    die ("ERROR2: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //'/sam_pub/1001' - Have to update to sam_pub_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = concat ('/sam_pub/', cast(right(sakaiId, locate('/sam_pub/',sakaiId))+$sam_pub_plus as CHAR)) where id >= $lb_items_plus and id < $lb_items_cur and sakaiId like '/sam_pub/%'")) {
    die ("ERROR3: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //TODO: Just move them all or have a criteria?
  if(!$t->query("INSERT INTO $target.lesson_builder_log SELECT id+$lb_log_plus,lastViewed,itemId,userId,firstViewed,complete,dummy,path,toolId,studentPageId
    FROM $source.lesson_builder_log")) {
    die ("ERROR: $target.lesson_builder_log :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT INTO $target.lesson_builder_properties SELECT id+$lb_properties_plus,attribute,value
    FROM $source.lesson_builder_properties")) {
    die ("ERROR: $target.lesson_builder_properties :: $site_id ::: $t->error \n");
  }
