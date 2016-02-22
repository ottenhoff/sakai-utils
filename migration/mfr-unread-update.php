<?php
require "migration-helper.php";
require "start-values.php";

$handle = fopen("sites.csv", "r");

$files = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $site_id = trim($data[0]);

  if ($site_id === 'a4008d61-8204-4f3f-89e5-606a093aa51b') continue;

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

  //if(!$t->query("update $target.mfr_unread_status_t SET MESSAGE_C=MESSAGE_C+$msg_message_plus WHERE ID IN (SELECT ID+$msg_unread_plus FROM $source.mfr_unread_status_t WHERE TOPIC_C IN
  if($res = $t->query("SELECT ID+$msg_unread_plus AS newid FROM $source.mfr_unread_status_t WHERE TOPIC_C IN
    (SELECT ID FROM $source.mfr_topic_t WHERE
      of_surrogateKey IN (SELECT ID FROM $source.mfr_open_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))
       OR
       pf_surrogateKey IN (SELECT ID FROM $source.mfr_private_forum_t WHERE surrogateKey IN (SELECT ID FROM $source.mfr_area_t WHERE CONTEXT_ID='$site_id'))
     )
     ")) {
      while ($row = $res->fetch_object()) {
        $rret = $t->query("update $target.mfr_unread_status_t SET MESSAGE_C=MESSAGE_C+$msg_message_plus WHERE ID=$row->newid");
        var_dump("$site_id :: update $target.mfr_unread_status_t SET MESSAGE_C=MESSAGE_C+$msg_message_plus WHERE ID=$row->newid :: $rret");
      }
    }
}
