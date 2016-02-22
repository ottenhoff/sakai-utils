<?php
require "migration-helper.php";
require "start-values.php";
require "current-values.php";

$handle = fopen("sites.csv", "r");

//Site Migration for syllabus/polls/
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  /*
   * Syllabus Tables
   *
   * $new_values['sakai_syllabus_attach'] = 36587;.
   * $new_values['sakai_syllabus_data'] = 45233;.
   * $new_valuess['sakai_syllabus_item'] = 24473;.
   *
   * Polls Tables
   *
   * $new_values['poll_option'] = 6898;.
   * $new_values['poll_poll'] = 1735;.
   * $new_values['poll_vote'] = 19590;.
   *
   * Signup tables
   *
   * $new_values['signup_meetings'] = 3577;.
   * $new_values['signup_sites'] = 5386;.
   * $new_values['signup_ts'] = 10556;.
  

  if(!$t->query("INSERT INTO $target.lesson_builder_pages SELECT pageId+$lb_pages_plus, toolId, siteId, title, parent+$lb_pages_plus,topParent+$lb_pages_plus,hidden,releaseDate,gradebookPoints,owner,groupOwned,cssSheet,groupid
     FROM $source.lesson_builder_pages WHERE siteId = '$site_id' ")) {
    die ("ERROR: $target.lesson_builder_pages :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT INTO $target.lesson_builder_items SELECT id+$lb_items_plus, pageId+$lb_pages_plus, sequence,type,sakaiId,name,html,description,height,width,alt,nextPage+$lb_pages_plus,format,required,alternate,prerequisite,subrequirement,requirementText,sameWindow,groups,anonymous,showComments,forcedCommentsAnonymous,gradebookId,gradebookPoints,gradebookTitle,altGradebook,altPoints,altGradebookTitle,showPeerEval,groupOwned,ownerGroups,attributeString 
     FROM $source.lesson_builder_items WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //Now need to do some special work to update SakaiID's!
  //Some patterns
  //'/assignment/00a4dd45-4165-438e-ab86-87c90752ca86' - Nothing
  //'/group/03aa044a-f9ab-45ed-84a9-d48259692d25/Videos' -Nothing
  //'/user/5c0b5ef3-56bf-4913-95ff-825525c526dc/stuff4/' - Nothing
  //'/dummy' - Nothing
 
  //Numeric --  '/sam_pub/1001' add $lb_pages_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = sakaiId + $lb_pages_plus where id >= $lb_items_plus and id < $lb_items_cur and sakaiId REGEXP '^[0-9]+$'")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //'/forum_topic/10586' - Have to update to forum_topic_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = concat ('/forum_topic/', cast(right(sakaiId, locate('/forum_topic/',sakaiId))+$msg_topic_plus as CHAR)) where id >= $lb_items_plus and id < $lb_items_cur and sakaiId like '/forum_topic/%'")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  //'/sam_pub/1001' - Have to update to sam_pub_plus
  if(!$t->query("UPDATE $target.lesson_builder_items SET sakaiId = concat ('/sam_pub/', cast(right(sakaiId, locate('/sam_pub/',sakaiId))+$sam_pub_plus as CHAR)) where id >= $lb_items_plus and id < $lb_items_cur and sakaiId like '/sam_pub/%'")) {
    die ("ERROR: $target.lesson_builder_items :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT INTO $target.lesson_builder_comments SELECT id+$lb_comments_plus,itemId+$lb_items_plus, pageId+$lb_pages_plus,author,commenttext,UUID,html,points 
    FROM $source.lesson_builder_comments WHERE pageId IN (SELECT pageId FROM $source.lesson_builder_pages WHERE siteId='$site_id')")) {
    die ("ERROR: $target.lesson_builder_comments :: $site_id ::: $t->error \n");
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

  die();
  //echo("INSERT INTO $target.$table SELECT * FROM $source.$table WHERE SITE_ID='$site_id'");
}
