<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  if ($site_id === '99219458-b14b-4c37-b6ac-c18066ba4bfd') continue;

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

  /*
  if(!$t->query("INSERT INTO $target.mfr_message_t SELECT ID+$msg_message_plus, MESSAGE_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, BODY, AUTHOR, HAS_ATTACHMENTS, GRADEASSIGNMENTNAME,
    LABEL, IN_REPLY_TO, TYPE_UUID, APPROVED, DRAFT, surrogateKey+$msg_topic_plus, EXTERNAL_EMAIL, EXTERNAL_EMAIL_ADDRESS, RECIPIENTS_AS_TEXT, DELETED, NUM_READERS,THREADID+$msg_message_plus,LASTTHREADATE, LASTTHREAPOST+$msg_message_plus,RECIPIENTS_AS_TEXT_BCC
    FROM $source.mfr_message_t WHERE IN_REPLY_TO IS NULL AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR1: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT INTO $target.mfr_message_t SELECT ID+$msg_message_plus, MESSAGE_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, BODY, AUTHOR, HAS_ATTACHMENTS, GRADEASSIGNMENTNAME,
    LABEL, IN_REPLY_TO, TYPE_UUID, APPROVED, DRAFT, surrogateKey+$msg_topic_plus, EXTERNAL_EMAIL, EXTERNAL_EMAIL_ADDRESS, RECIPIENTS_AS_TEXT, DELETED, NUM_READERS,THREADID+$msg_message_plus,LASTTHREADATE, LASTTHREAPOST+$msg_message_plus,RECIPIENTS_AS_TEXT_BCC
    FROM $source.mfr_message_t WHERE IN_REPLY_TO IS NULL AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ")) {
      die ("ERROR2: $target.mfr_message_t :: $site_id ::: $t->error \n");
    }
   */
  $t->query("SET foreign_key_checks = 0");
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT ID+$msg_message_plus, MESSAGE_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, BODY, AUTHOR, HAS_ATTACHMENTS, GRADEASSIGNMENTNAME,
    LABEL, IN_REPLY_TO+$msg_message_plus, TYPE_UUID, APPROVED, DRAFT, surrogateKey+$msg_topic_plus, EXTERNAL_EMAIL, EXTERNAL_EMAIL_ADDRESS, RECIPIENTS_AS_TEXT, DELETED, NUM_READERS,THREADID+$msg_message_plus,LASTTHREADATE, LASTTHREAPOST+$msg_message_plus,RECIPIENTS_AS_TEXT_BCC
    FROM $source.mfr_message_t WHERE IN_REPLY_TO > 0 AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ORDER BY ID ASC ")) {
      die ("ERROR3: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  if(!$t->query("INSERT IGNORE INTO $target.mfr_message_t SELECT ID+$msg_message_plus, MESSAGE_DTYPE, VERSION, UUID, CREATED, CREATED_BY, MODIFIED, MODIFIED_BY, TITLE, BODY, AUTHOR, HAS_ATTACHMENTS, GRADEASSIGNMENTNAME,
    LABEL, IN_REPLY_TO+$msg_message_plus, TYPE_UUID, APPROVED, DRAFT, surrogateKey+$msg_topic_plus, EXTERNAL_EMAIL, EXTERNAL_EMAIL_ADDRESS, RECIPIENTS_AS_TEXT, DELETED, NUM_READERS,THREADID+$msg_message_plus,LASTTHREADATE, LASTTHREAPOST+$msg_message_plus,RECIPIENTS_AS_TEXT_BCC
    FROM $source.mfr_message_t WHERE IN_REPLY_TO > 0 AND surrogateKey IN (SELECT ID FROM $source.mfr_topic_t WHERE pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id') ) ) ORDER BY ID ASC ")) {
      die ("ERROR4: $target.mfr_message_t :: $site_id ::: $t->error \n");
  }
  $t->query("SET foreign_key_checks = 1");

}
