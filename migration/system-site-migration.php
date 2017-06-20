<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  // First the site tables
  $res = $t->query("SELECT * FROM sakai_site WHERE SITE_ID='$site_id'");
  if ($res && $res->num_rows === 0) {
    $tables = array('sakai_site', 'sakai_site_property', 'sakai_site_page', 'sakai_site_page_property', 'sakai_site_tool', 'sakai_site_tool_property', 'sakai_site_group', 'sakai_site_group_property', 'sakai_site_user');
    foreach ($tables AS $table) {
      if($t->query("INSERT INTO $target.$table SELECT * FROM $source.$table WHERE SITE_ID='$site_id'")) {
        print "Success: $target.$table from $source.$table :: $site_id ::: $t->affected_rows \n";
      }
      else {
        print "ERROR: $target.$table from $source.$table :: $site_id ::: $t->error \n";
        die();
      }
    }
  }

  // Second do the realms
  $res = $s->query("SELECT REALM_KEY FROM sakai_realm WHERE REALM_ID LIKE '%$site_id%'");
  while ($row = $res->fetch_object()) {
    $new_realm_key = $old_realm_key = $row->REALM_KEY;
    //$plus = $new_values['sakai_realm'];
    //$new_realm_key = $old_realm_key + $plus;
    print "INFO: transferring $old_realm_key to new realm $new_realm_key \n";

    // Transferring the users over
    if(!$t->query("INSERT IGNORE INTO $target.sakai_user_id_map SELECT * FROM $source.sakai_user_id_map WHERE USER_ID IN (SELECT USER_ID FROM $source.sakai_realm_rl_gr WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_user_id_map :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_user SELECT * FROM $source.sakai_user WHERE USER_ID IN (SELECT USER_ID FROM $source.sakai_realm_rl_gr WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_user :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_user_property SELECT * FROM $source.sakai_user_property WHERE USER_ID IN (SELECT USER_ID FROM $source.sakai_realm_rl_gr WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_user_property :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_preferences SELECT * FROM $source.sakai_preferences WHERE PREFERENCES_ID IN (SELECT USER_ID FROM $source.sakai_realm_rl_gr WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_preferences :: $site_id ::: $t->error \n");
    }

    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_role SELECT ROLE_KEY, ROLE_NAME FROM $source.sakai_realm_role WHERE ROLE_KEY IN
        (SELECT ROLE_KEY FROM $source.sakai_realm_rl_fn WHERE REALM_KEY=$old_realm_key) OR ROLE_KEY IN (SELECT MAINTAIN_ROLE FROM $source.sakai_realm WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_realm_role :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm SELECT $new_realm_key, REALM_ID, PROVIDER_ID, MAINTAIN_ROLE, CREATEDBY, MODIFIEDBY, CREATEDON, MODIFIEDON FROM $source.sakai_realm WHERE REALM_KEY=$old_realm_key")) {
      die ("ERROR: $target.sakai_realm :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_role_desc SELECT REALM_KEY, ROLE_KEY, DESCRIPTION, PROVIDER_ONLY FROM $source.sakai_realm_role_desc WHERE REALM_KEY=$old_realm_key")) {
      die ("ERROR: $target.sakai_realm_role_desc :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_rl_gr SELECT $new_realm_key, USER_ID, ROLE_KEY, ACTIVE, PROVIDED FROM $source.sakai_realm_rl_gr WHERE REALM_KEY=$old_realm_key")) {
      die ("ERROR: $target.sakai_realm_rl_gr :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_function SELECT FUNCTION_KEY, FUNCTION_NAME FROM $source.sakai_realm_function WHERE FUNCTION_KEY IN (SELECT FUNCTION_KEY FROM $source.sakai_realm_rl_fn WHERE REALM_KEY=$old_realm_key)")) {
      die ("ERROR: $target.sakai_realm_function :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_rl_fn SELECT $new_realm_key, ROLE_KEY, FUNCTION_KEY FROM $source.sakai_realm_rl_fn WHERE REALM_KEY=$old_realm_key")) {
      die ("ERROR: $target.sakai_realm_rl_fn :: $site_id ::: $t->error \n");
    }
    /*
    if(!$t->query("INSERT IGNORE INTO $target.sakai_realm_role_desc SELECT $new_realm_key, ROLE_KEY, DESCRIPTION, PROVIDER_ONLY FROM $source.sakai_realm_role_desc WHERE ROLE_KEY >= 3 AND REALM_KEY=$old_realm_key")) {
      die ("ERROR: $target.sakai_realm_role_desc :: $site_id ::: $t->error \n");
    }
    $res2 = $s->query("SELECT fn.REALM_KEY, fn.ROLE_KEY, fn.FUNCTION_KEY, f.FUNCTION_NAME FROM sakai_realm_rl_fn fn INNER JOIN sakai_realm_function f ON fn.FUNCTION_KEY=f.FUNCTION_KEY WHERE fn.REALM_KEY=$old_realm_key");
    while ($row2 = $res2->fetch_object()) {
      $new_function = 0;
      if (in_array($row2->FUNCTION_NAME, $targetFunctions)) {
        $new_function = array_search($row2->FUNCTION_NAME, $targetFunctions);
        $new_role_key = $row2->ROLE_KEY >= 3 ? $row2->ROLE_KEY : $row2->ROLE_KEY;
        if(!$t->query("INSERT INTO sakai_realm_rl_fn VALUES ($new_realm_key, $new_role_key, $new_function)")) {
          die ("ERROR: $target.sakai_realm_rl_fn :: $site_id ::: $t->error \n");
        }
      }
      else {
        print "Could not find function $row2->FUNCTION_NAME :: $site_id \n";
      }
    }
    */
  }

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

    if (count($assignments) > 0) {
      $a_sql = "'" . implode("','", $assignments) . "'";
      if(!$t->query("INSERT IGNORE INTO $target.assignment_assignment SELECT * FROM $source.assignment_assignment WHERE ASSIGNMENT_ID IN ($a_sql) ")) {
        die ("ERROR: $target.assignment_assignment :: $site_id ::: $t->error \n");
      }
      if(!$t->query("INSERT IGNORE INTO $target.assignment_submission SELECT SUBMISSION_ID,CONTEXT,XML,SUBMITTER_ID,SUBMIT_TIME,SUBMITTED,GRADED FROM $source.assignment_submission WHERE CONTEXT IN ($a_sql) ")) {
        die ("ERROR: $target.assignment_submission :: $site_id ::: $t->error \n");
      }
    }
    if (count($contents) > 0) {
      $c_sql = "'" . implode("','", $contents) . "'";
      if(!$t->query("INSERT IGNORE INTO $target.assignment_content SELECT * FROM $source.assignment_content WHERE CONTENT_ID IN ($c_sql) ")) {
        die ("ERROR: $target.assignment_content :: $site_id ::: $t->error \n");
      }
    }
  }

  // Fourth the blog posts
  if ($res4 = $t->query("SELECT id FROM $source.blogwow_blog WHERE location = '/site/$site_id' ")) {
    $blog_ids = array();
    while ($row4 = $res4->fetch_object()) {
      $blog_ids[] = $row4->id;
    }
    $blog_sql = "'" . implode("','", $blog_ids) . "'";

    if(!$t->query("INSERT INTO $target.blogwow_blog SELECT * FROM $source.blogwow_blog WHERE id IN ($blog_sql) ")) {
      die ("ERROR: $target.blogwow_blog :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT INTO $target.blogwow_entry SELECT * FROM $source.blogwow_entry WHERE blog_id IN ($blog_sql) ")) {
      die ("ERROR: $target.blogwow_entry :: $site_id ::: $t->error \n");
    }
    if(!$t->query("INSERT INTO $target.blogwow_comment SELECT * FROM $source.blogwow_comment WHERE entry_id IN (SELECT id FROM $source.blogwow_entry WHERE blog_id IN ($blog_sql) ) ")) {
      die ("ERROR: $target.blogwow_comment :: $site_id ::: $t->error \n");
    }
  }

  // Fifth the calendars
  if(!$t->query("INSERT IGNORE INTO $target.calendar_calendar SELECT * FROM $source.calendar_calendar WHERE CALENDAR_ID = '/calendar/calendar/$site_id/main' ")) {
    die ("ERROR: $target.calendar_calendar :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.calendar_event SELECT * FROM $source.calendar_event WHERE CALENDAR_ID = '/calendar/calendar/$site_id/main' ")) {
    die ("ERROR: $target.calendar_event :: $site_id ::: $t->error \n");
  }

  // Fifth the announcements
  if(!$t->query("INSERT IGNORE INTO $target.announcement_channel SELECT * FROM $source.announcement_channel WHERE CHANNEL_ID = '/announcement/channel/$site_id/main' ")) {
    die ("ERROR: $target.announcement_channel :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.announcement_message SELECT * FROM $source.announcement_message WHERE CHANNEL_ID = '/announcement/channel/$site_id/main' ")) {
    die ("ERROR: $target.announcement_channel :: $site_id ::: $t->error \n");
  }

  // Sixth the chats
  if(!$t->query("INSERT IGNORE INTO $target.chat2_channel SELECT * FROM $source.chat2_channel WHERE CONTEXT = '$site_id' ")) {
    die ("ERROR: $target.chat2_channel :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.chat2_message SELECT * FROM $source.chat2_message WHERE CHANNEL_ID IN (SELECT CHANNEL_ID FROM chat2_channel WHERE CONTEXT = '$site_id') ")) {
    die ("ERROR: $target.chat2_message :: $site_id ::: $t->error \n");
  }

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

  // Seventh the mail archive
  if(!$t->query("INSERT IGNORE INTO $target.mailarchive_channel SELECT * FROM $source.mailarchive_channel WHERE CHANNEL_ID='/mailarchive/channel/$site_id/main'")) {
    die ("ERROR:  $target.mailarchive_channel :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mailarchive_message SELECT * FROM $source.mailarchive_message WHERE CHANNEL_ID='/mailarchive/channel/$site_id/main'")) {
    die ("ERROR:  $target.mailarchive_message :: $site_id ::: $t->error \n");
  }

  // Eigth message center
  $msg_area_plus = $new_values['mfr_area_t'];
  $msg_of_plus = $new_values['mfr_open_forum_t'];
  $msg_pf_plus = $new_values['mfr_private_forum_t'];
  $msg_topic_plus = $new_values['mfr_topic_t'];
  $msg_message_plus = $new_values['mfr_message_t'];
  $msg_perm_plus = $new_values['mfr_permission_level_t'];
  $msg_member_plus = $new_values['mfr_membership_item_t'];
  $msg_syn_plus = $new_values['mfr_synoptic_item'];
  $msg_unread_plus = $new_values['mfr_unread_status_t'];
  $msg_attach_plus = $new_values['mfr_attachment_t'];

  if(!$t->query("INSERT IGNORE INTO $target.cmn_type_t SELECT * FROM $source.cmn_type_t WHERE UUID IN (SELECT TYPE_UUID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')")) {
      die ("ERROR: $target.mfr_area_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_area_t SELECT ID, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, CONTEXT_ID, `NAME`, HIDDEN, TYPE_UUID, ENABLED, LOCKED,
    MODERATED, SENDEMAILOUT, AUTO_MARK_THREADS_READ, AVAILABILITY_RESTRICTED, AVAILABILITY, OPEN_DATE, CLOSE_DATE, POST_FIRST, SEND_TO_EMAIL
    FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'")) {
      die ("ERROR: $target.mfr_area_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_open_forum_t SELECT ID, FORUM_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, DEFAULTASSIGNNAME, TITLE, SHORT_DESCRIPTION, EXTENDED_DESCRIPTION,
    TYPE_UUID, SORT_INDEX, LOCKED, DRAFT,surrogateKey,MODERATED,AUTO_MARK_THREADS_READ,AVAILABILITY_RESTRICTED,AVAILABILITY,OPEN_DATE,CLOSE_DATE,POST_FIRST
    FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ")) {
      die ("ERROR: $target.mfr_open_forum_t :: $site_id ::: $t->error \n");
    }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_private_forum_t SELECT ID, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, SHORT_DESCRIPTION, EXTENDED_DESCRIPTION,
    TYPE_UUID, SORT_INDEX, OWNER, AUTO_FORWARD, AUTO_FORWARD_EMAIL, PREVIEW_PANE_ENABLED, surrogateKey
    FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ")) {
      die ("ERROR: $target.mfr_open_forum_t :: $site_id ::: $t->error \n");
    }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_topic_t SELECT * FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ")) {
      die ("ERROR: $target.mfr_topic_t :: open_forums :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_topic_t SELECT * FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ")) {
      die ("ERROR2: $target.mfr_topic_t :: private_forums :: $site_id ::: $t->error \n");
    }
  $t->query("SET foreign_key_checks = 0");
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT * FROM $source.mfr_message_t WHERE IN_REPLY_TO IS NULL AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR1: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT * FROM $source.mfr_message_t WHERE IN_REPLY_TO IS NULL AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR2: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT * FROM $source.mfr_message_t WHERE IN_REPLY_TO > 0 AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR3: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT * FROM $source.mfr_message_t WHERE IN_REPLY_TO > 0 AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR4: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  $t->query("SET foreign_key_checks = 1");
  if(!$t->query("INSERT IGNORE INTO $target.mfr_permission_level_t SELECT * FROM $source.mfr_permission_level_t WHERE ID IN (
    SELECT PERMISSION_LEVEL FROM $source.mfr_membership_item_t WHERE
      a_surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')
       OR
      of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))
       OR
      t_surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
       OR
      t_surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
    )
    ")) {
      die ("ERROR: $target.mfr_permission_level_t :: $site_id ::: $t->error \n");
    }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_membership_item_t SELECT ID,VERSION,UUID,CREATED,CREATED_BY,MODIFIED,MODIFIED_BY,`NAME`,`TYPE`,PERMISSION_LEVEL_NAME,PERMISSION_LEVEL,a_surrogateKey, t_surrogateKey, of_surrogateKey
    FROM $source.mfr_membership_item_t WHERE a_surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')")) {
      die ("ERROR1: $target.mfr_membership_item_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_membership_item_t SELECT ID,VERSION,UUID,CREATED,CREATED_BY,MODIFIED,MODIFIED_BY,`NAME`,`TYPE`,PERMISSION_LEVEL_NAME,PERMISSION_LEVEL,a_surrogateKey, t_surrogateKey, of_surrogateKey
    FROM $source.mfr_membership_item_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ")) {
      die ("ERROR2: $target.mfr_membership_item_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_membership_item_t SELECT ID,VERSION,UUID,CREATED,CREATED_BY,MODIFIED,MODIFIED_BY,`NAME`,`TYPE`,PERMISSION_LEVEL_NAME,PERMISSION_LEVEL,a_surrogateKey, t_surrogateKey, of_surrogateKey
    FROM $source.mfr_membership_item_t WHERE t_surrogateKey IN
        (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
        OR
        t_surrogateKey IN
        (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
      ")) {
      die ("ERROR3: $target.mfr_membership_item_t :: $site_id ::: $t->error \n");
      }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT ID, MESSAGE_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, BODY, AUTHOR, HAS_ATTACHMENTS, GRADEASSIGNMENTNAME,
    LABEL, IN_REPLY_TO, TYPE_UUID, APPROVED, DRAFT, surrogateKey, EXTERNAL_EMAIL, EXTERNAL_EMAIL_ADDRESS, RECIPIENTS_AS_TEXT, DELETED, NUM_READERS,THREADID,LASTTHREADATE, LASTTHREAPOST,RECIPIENTS_AS_TEXT_BCC
    FROM $source.mfr_message_t WHERE MESSAGE_DTYPE='PM' AND surrogateKey IS NULL AND ID IN (SELECT messageSurrogateKey FROM $source.mfr_pvt_msg_usr_t WHERE CONTEXT_ID='$site_id') ")) {
      die ("ERROR1: $target.mfr_pvt_msg_usr_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_pvt_msg_usr_t SELECT messageSurrogateKey,USER_ID,TYPE_UUID,CONTEXT_ID,READ_STATUS,user_index_col,BCC,REPLIED
    FROM $source.mfr_pvt_msg_usr_t WHERE CONTEXT_ID='$site_id'")) {
      die ("ERROR2: $target.mfr_pvt_msg_usr_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_synoptic_item SELECT SYNOPTIC_ITEM_ID,VERSION,USER_ID,SITE_ID,SITE_TITLE,NEW_MESSAGES_COUNT,MESSAGES_LAST_VISIT_DT,NEW_FORUM_COUNT,FORUM_LAST_VISIT_DT,HIDE_ITEM
    FROM $source.mfr_synoptic_item WHERE SITE_ID='$site_id'")) {
      print ("ERROR: $target.mfr_synoptic_item :: $site_id ::: $t->error \n");
    }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_unread_status_t SELECT ID,VERSION,TOPIC_C,MESSAGE_C,USER_C,READ_C FROM $source.mfr_unread_status_t WHERE TOPIC_C IN
    (SELECT ID FROM $source.mfr_topic_t WHERE
      of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))
       OR
       pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))
     )
    ")) {
      die ("ERROR: $target.mfr_unread_status_t :: $site_id ::: $t->error \n");
    }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_attachment_t SELECT ID, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, ATTACHMENT_ID, ATTACHMENT_URL, ATTACHMENT_NAME, ATTACHMENT_SIZE, ATTACHMENT_TYPE,
    m_surrogateKey, of_surrogateKey, pf_surrogateKey, t_surrogateKey, of_urrogateKey FROM $source.mfr_attachment_t WHERE m_surrogateKey IN
      (SELECT ID FROM $source.mfr_message_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))))
      OR m_surrogateKey IN
      (SELECT ID FROM $source.mfr_message_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))))
      ")) {
    die ("ERROR1: $target.mfr_attachment_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_attachment_t SELECT ID, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, ATTACHMENT_ID, ATTACHMENT_URL, ATTACHMENT_NAME, ATTACHMENT_SIZE, ATTACHMENT_TYPE,
    m_surrogateKey, of_surrogateKey, pf_surrogateKey, t_surrogateKey, of_urrogateKey FROM $source.mfr_attachment_t WHERE t_surrogateKey IN
      (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
      OR  t_surrogateKey IN
      (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id')))
    ")) {
    die ("ERROR2: $target.mfr_attachment_t :: $site_id ::: $t->error \n");
  }

  // Samigo
  $sam_authz_plus = $new_values['sam_authzdata_t'];
  $sam_ass_plus = $new_values['sam_assessmentbase_t'];
  $sam_ass_met_plus = $new_values['sam_assessmetadata_t'];
  $sam_sec_plus = $new_values['sam_section_t'];
  $sam_sec_met_plus = $new_values['sam_sectionmetadata_t'];
  $sam_item_plus = $new_values['sam_item_t'];
  $sam_item_feed_plus = $new_values['sam_itemfeedback_t'];
  $sam_item_meta_plus = $new_values['sam_itemmetadata_t'];
  $sam_item_text_plus = $new_values['sam_itemtext_t'];
  $sam_ans_plus = $new_values['sam_answer_t'];
  $sam_ans_feed_plus = $new_values['sam_answerfeedback_t'];
  // ********
  $samp_ass_plus = $new_values['sam_publishedassessment_t'];
  $samp_ass_met_plus = $new_values['sam_publishedmetadata_t'];
  $samp_sec_plus = $new_values['sam_publishedsection_t'];
  $samp_sec_met_plus = $new_values['sam_publishedsectionmetadata_t'];
  $samp_item_plus = $new_values['sam_publisheditem_t'];
  $samp_item_feed_plus = $new_values['sam_publisheditemfeedback_t'];
  $samp_item_meta_plus = $new_values['sam_publisheditemmetadata_t'];
  $samp_item_text_plus = $new_values['sam_publisheditemtext_t'];
  $samp_ans_plus = $new_values['sam_publishedanswer_t'];
  $samp_ans_feed_plus = $new_values['sam_publishedanswerfeedback_t'];
  // ********
  $sam_ag_plus = $new_values['sam_assessmentgrading_t'];
  $sam_ig_plus = $new_values['sam_itemgrading_t'];

  $unpub = $pub = $sec = $item = $p_sec = $p_item = array();
  $unpub_res = $t->query("SELECT DISTINCT QUALIFIERID FROM $source.sam_authzdata_t WHERE AGENTID='$site_id' AND FUNCTIONID NOT LIKE '%PUBLISHED%'");
  while ($u = $unpub_res->fetch_object()) {
    $unpub[] = $u->QUALIFIERID;
  }
  if (count($unpub) > 0) {
    $sec_res = $t->query("SELECT DISTINCT SECTIONID FROM $source.sam_section_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")");
    while ($sec_row = $sec_res->fetch_object()) {
      $sec[] = $sec_row->SECTIONID;
    }
    $item_res = $t->query("SELECT DISTINCT ITEMID FROM $source.sam_item_t WHERE SECTIONID IN (" . implode(',', $sec) . ")");
    while ($item_row = $item_res->fetch_object()) {
      $item[] = $item_row->ITEMID;
    }
  }
  $pub_res = $t->query("SELECT DISTINCT QUALIFIERID FROM $source.sam_authzdata_t WHERE AGENTID='$site_id' AND FUNCTIONID LIKE '%PUBLISHED%'");
  while ($p = $pub_res->fetch_object()) {
    $pub[] = $p->QUALIFIERID;
  }
  if (count($pub) > 0) {
    $sec_res = $t->query("SELECT DISTINCT SECTIONID FROM $source.sam_publishedsection_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")");
    while ($sec_row = $sec_res->fetch_object()) {
      $p_sec[] = $sec_row->SECTIONID;
    }
    $item_res = $t->query("SELECT DISTINCT ITEMID FROM $source.sam_publisheditem_t WHERE SECTIONID IN (" . implode(',', $p_sec) . ")");
    while ($item_row = $item_res->fetch_object()) {
      $p_item[] = $item_row->ITEMID;
    }
  }

  if (count($unpub) > 0) {
    if(!$t->query("INSERT IGNORE INTO $target.sam_authzdata_t SELECT * FROM $source.sam_authzdata_t WHERE AGENTID='$site_id' AND FUNCTIONID NOT LIKE '%PUBLISHED%' ")) {
        die ("ERROR: $target.sam_authzdata_t :: $site_id ::: $t->error \n");
      }

    if(!$t->query("INSERT IGNORE INTO $target.sam_assessmentbase_t SELECT * FROM $source.sam_assessmentbase_t WHERE ID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_assessmentbase_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_assessaccesscontrol_t SELECT * FROM $source.sam_assessaccesscontrol_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_assessaccesscontrol_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_assessevaluation_t SELECT * FROM $source.sam_assessevaluation_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_assessevaluation_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_assessfeedback_t SELECT * FROM $source.sam_assessfeedback_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_assessfeedback_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_assessmetadata_t SELECT * FROM $source.sam_assessmetadata_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_assessmetadata_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_section_t SELECT * FROM $source.sam_section_t WHERE ASSESSMENTID IN (" . implode(',', $unpub) . ")")) {
        die ("ERROR: $target.sam_section_t :: $site_id ::: $t->error \n");
      }

    if(!$t->query("INSERT IGNORE INTO $target.sam_sectionmetadata_t SELECT * FROM $source.sam_sectionmetadata_t
      WHERE SECTIONID IN (" . implode(',', $sec) . ")")) {
        die ("ERROR: $target.sam_sectionmetadata_t :: $site_id :: $t->error \n");
      }
    if (count($item) > 0) {
      if(!$t->query("INSERT IGNORE INTO $target.sam_item_t SELECT * FROM $source.sam_item_t WHERE SECTIONID IN (" . implode(',', $sec) . ")")) {
          die ("ERROR: $target.sam_item_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_itemfeedback_t SELECT * FROM $source.sam_itemfeedback_t WHERE ITEMID IN (" . implode(',', $item) . ")")) {
          die ("ERROR: $target.sam_itemfeedback_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_itemmetadata_t SELECT * FROM $source.sam_itemmetadata_t WHERE ITEMID IN (" . implode(',', $item) . ")")) {
          die ("ERROR: $target.sam_itemmetadata_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_itemtext_t SELECT * FROM $source.sam_itemtext_t WHERE ITEMID IN (" . implode(',', $item) . ")")) {
          die ("ERROR: $target.sam_itemtext_t :: $site_id :: $t->error \n");
        }

      if(!$t->query("INSERT IGNORE INTO $target.sam_answer_t SELECT * FROM $source.sam_answer_t WHERE ITEMID IN (" . implode(',', $item) . ")")) {
          die ("ERROR: $target.sam_answer_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_answerfeedback_t SELECT * FROM $source.sam_answerfeedback_t WHERE ANSWERID IN (SELECT ANSWERID FROM $source.sam_answer_t WHERE ITEMID IN (" . implode(',', $item) . ") )")) {
          die ("ERROR: $target.sam_answerfeedback_t :: $site_id :: $t->error \n");
        }
    }
  }

  // PUBLISHED ASSESSMENTS
  if (count($pub) > 0) {
    if(!$t->query("INSERT IGNORE INTO $target.sam_authzdata_t SELECT * FROM $source.sam_authzdata_t WHERE AGENTID='$site_id' AND FUNCTIONID LIKE '%PUBLISHED%' ")) {
        die ("ERROR2: $target.sam_authzdata_t :: $site_id ::: $t->error \n");
      }

    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedassessment_t SELECT * FROM $source.sam_publishedassessment_t WHERE ID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedassessment_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedaccesscontrol_t SELECT * FROM $source.sam_publishedaccesscontrol_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedaccesscontrol_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedevaluation_t SELECT * FROM $source.sam_publishedevaluation_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedevaluation_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedfeedback_t SELECT * FROM $source.sam_publishedfeedback_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedfeedback_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedmetadata_t SELECT * FROM $source.sam_publishedmetadata_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedmetadata_t :: $site_id ::: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedsection_t SELECT * FROM $source.sam_publishedsection_t WHERE ASSESSMENTID IN (" . implode(',', $pub) . ")")) {
        die ("ERROR: $target.sam_publishedsection_t :: $site_id ::: $t->error \n");
      }

    if(!$t->query("INSERT IGNORE INTO $target.sam_publishedsectionmetadata_t SELECT * FROM $source.sam_publishedsectionmetadata_t
      WHERE SECTIONID IN (" . implode(',', $p_sec) . ")")) {
        die ("ERROR: $target.sam_publishedsectionmetadata_t :: $site_id :: $t->error \n");
      }
    if (count($p_item) > 0) {
      if(!$t->query("INSERT IGNORE INTO $target.sam_publisheditem_t SELECT * FROM $source.sam_publisheditem_t WHERE SECTIONID IN (" . implode(',', $p_sec) . ")")) {
          die ("ERROR: $target.sam_publisheditem_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_publisheditemfeedback_t SELECT * FROM $source.sam_publisheditemfeedback_t WHERE ITEMID IN (" . implode(',', $p_item) . ")")) {
          die ("ERROR: $target.sam_publisheditemfeedback_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_publisheditemmetadata_t SELECT * FROM $source.sam_publisheditemmetadata_t WHERE ITEMID IN (" . implode(',', $p_item) . ")")) {
          die ("ERROR: $target.sam_publisheditemmetadata_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_publisheditemtext_t SELECT * FROM $source.sam_publisheditemtext_t WHERE ITEMID IN (" . implode(',', $p_item) . ")")) {
          die ("ERROR: $target.sam_publisheditemtext_t :: $site_id :: $t->error \n");
        }

      if(!$t->query("INSERT IGNORE INTO $target.sam_publishedanswer_t SELECT * FROM $source.sam_publishedanswer_t WHERE ITEMID IN (" . implode(',', $p_item) . ")")) {
          die ("ERROR: $target.sam_publishedanswer_t :: $site_id :: $t->error \n");
        }
      if(!$t->query("INSERT IGNORE INTO $target.sam_publishedanswerfeedback_t SELECT * FROM $source.sam_publishedanswerfeedback_t WHERE ANSWERID IN (SELECT ANSWERID FROM $source.sam_publishedanswer_t WHERE ITEMID IN (" . implode(',', $p_item) . ") )")) {
          die ("ERROR: $target.sam_publishedanswerfeedback_t :: $site_id :: $t->error \n");
        }
    }

    // Samigo grading
    if(!$t->query("INSERT IGNORE INTO $target.sam_assessmentgrading_t SELECT * FROM $source.sam_assessmentgrading_t WHERE PUBLISHEDASSESSMENTID IN (" . implode(',', $pub) . ")")) { 
        die ("ERROR: $target.sam_assessmentgrading_t :: $site_id :: $t->error \n");
      }
    if(!$t->query("INSERT IGNORE INTO $target.sam_itemgrading_t SELECT * FROM $source.sam_itemgrading_t WHERE ASSESSMENTGRADINGID IN (SELECT ASSESSMENTGRADINGID FROM $source.sam_assessmentgrading_t WHERE PUBLISHEDASSESSMENTID IN (" . implode(',', $pub) . ") )")) { 
        die ("ERROR: $target.sam_itemgrading_t :: $site_id :: $t->error \n");
      }
  }

  // Gradebook
  $gb_plus = $new_values['gb_gradebook_t'];
  $gb_map_plus = $new_values['gb_grade_map_t'];
  $gb_cat_plus = $new_values['gb_category_t'];
  $gb_obj_plus = $new_values['gb_gradable_object_t'];
  $gb_rec_plus = $new_values['gb_grade_record_t'];
  $gb_ev_plus = $new_values['gb_grading_event_t'];

  $t->query("SET foreign_key_checks = 0");
  if(!$t->query("INSERT IGNORE INTO $target.gb_gradebook_t SELECT * FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID = '$site_id' ")) {
    die ("ERROR: $target.gb_gradebook_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.gb_grade_map_t SELECT * FROM $source.gb_grade_map_t WHERE GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id')")) {
    die ("ERROR: $target.gb_grade_map_t :: $site_id ::: $t->error \n");
  }
  $t->query("SET foreign_key_checks = 1");
  if(!$t->query("INSERT IGNORE INTO $target.gb_grade_to_percent_mapping_t SELECT * FROM $source.gb_grade_to_percent_mapping_t WHERE GRADE_MAP_ID IN
    (SELECT ID FROM $source.gb_grade_map_t WHERE GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id'))")) {
    die ("ERROR: $target.gb_grade_to_percent_mapping_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.gb_category_t SELECT * FROM $source.gb_category_t WHERE GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id')")) {
    die ("ERROR: $target.gb_category_t :: $site_id ::: $t->error \n");
  }

  if(!$t->query("INSERT IGNORE INTO $target.gb_gradable_object_t SELECT * FROM $source.gb_gradable_object_t WHERE EXTERNAL_APP_NAME='Tests & Quizzes' AND GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id')")) {
      die ("ERROR3: $target.gb_gradable_object_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.gb_gradable_object_t SELECT * FROM $source.gb_gradable_object_t WHERE (EXTERNAL_APP_NAME != 'Tests & Quizzes' OR EXTERNAL_APP_NAME IS NULL) AND GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id')")) {
      die ("ERROR4: $target.gb_gradable_object_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.gb_grade_record_t SELECT * FROM $source.gb_grade_record_t WHERE GRADABLE_OBJECT_ID IN
    (SELECT ID FROM $source.gb_gradable_object_t WHERE GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id'))")) {
      die ("ERROR: $target.gb_grade_record_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.gb_grading_event_t SELECT * FROM $source.gb_grading_event_t WHERE GRADABLE_OBJECT_ID IN
    (SELECT ID FROM $source.gb_gradable_object_t WHERE GRADEBOOK_ID IN (SELECT ID FROM $source.gb_gradebook_t WHERE GRADEBOOK_UID='$site_id'))")) {
      die ("ERROR: $target.gb_grading_event_t :: $site_id ::: $t->error \n");
  }

  // Syllabus
  $syl_item_plus = $new_values['sakai_syllabus_item'];
  $syl_data_plus = $new_values['sakai_syllabus_data'];
  $syl_attach_plus = $new_values['sakai_syllabus_attach'];

  if(!$t->query("INSERT IGNORE INTO $target.sakai_syllabus_item SELECT * FROM $source.sakai_syllabus_item WHERE contextId='$site_id'")) {
    die ("ERROR: $target.sakai_syllabus_item :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.sakai_syllabus_data SELECT * FROM $source.sakai_syllabus_data WHERE surrogateKey IN (SELECT ID FROM $source.sakai_syllabus_item WHERE contextId='$site_id') ")) {
    die ("ERROR: $target.sakai_syllabus_data  :: $site_id ::: $t->error \n"); 
  }
  if(!$t->query("INSERT IGNORE INTO $target.sakai_syllabus_attach SELECT * FROM $source.sakai_syllabus_attach WHERE syllabusId IN 
      (SELECT ID FROM $source.sakai_syllabus_data WHERE surrogateKey IN (SELECT ID FROM $source.sakai_syllabus_item WHERE contextId='$site_id') )")) {
    die ("ERROR: $target.sakai_syllabus_attach  :: $site_id ::: $t->error \n"); 
  }

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


  //echo("INSERT INTO $target.$table SELECT * FROM $source.$table WHERE SITE_ID='$site_id'");
  print "COMPLETE: $site_id \n";
  sleep(15);
}
