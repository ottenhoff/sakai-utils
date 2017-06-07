<?php

$source = 'point_lamp_migration';
$target = 'sakai_point_dev';

$s = new mysqli('lsdevaurora-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com', 'point', 'temp123!', $source);
$t = new mysqli('lsdevaurora-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com', 'sakai_point', '94XKi7NNd5b0', $target);
if (!$s || !$t) die('Bad mysql connection');

$sourceFunctions = $targetFunctions = array();
$res = $s->query("SELECT FUNCTION_KEY, FUNCTION_NAME FROM sakai_realm_function ORDER BY FUNCTION_KEY");
while ($row = $res->fetch_object()) {
  $sourceFunctions[$row->FUNCTION_KEY] = $row->FUNCTION_NAME;
}

$res = $t->query("SELECT FUNCTION_KEY, FUNCTION_NAME FROM sakai_realm_function ORDER BY FUNCTION_KEY");
while ($row = $res->fetch_object()) {
  $targetFunctions[$row->FUNCTION_KEY] = $row->FUNCTION_NAME;
}

$good_tables = array(
'gb_category_t',
'gb_comment_t',
'gb_gradable_object_t',
'gb_grade_map_t',
'gb_grade_record_t',
'gb_gradebook_t',
'gb_grading_event_t',
'gb_spreadsheet_t',
'lesson_builder_comments',
'lesson_builder_groups',
'lesson_builder_items',
'lesson_builder_log',
'lesson_builder_p_eval_results',
'lesson_builder_pages',
'lesson_builder_properties',
'lesson_builder_q_responses',
'lesson_builder_qr_totals',
'lesson_builder_student_pages',
'mfr_area_t',
'mfr_attachment_t',
'mfr_email_notification_t',
'mfr_membership_item_t',
'mfr_message_t',
'mfr_open_forum_t',
'mfr_private_forum_t',
'mfr_synoptic_item',
'mfr_topic_t',
'mfr_unread_status_t',
'poll_option',
'poll_poll',
'poll_vote',
'sakai_realm',
'sakai_syllabus_attach',
'sakai_syllabus_data',
'sakai_syllabus_item',
'sam_answer_t',
'sam_answerfeedback_t',
'sam_assessmentbase_t',
'sam_assessmentgrading_t',
'sam_assessmetadata_t',
'sam_attachment_t',
'sam_authzdata_t',
'sam_eventlog_t',
'sam_gradingattachment_t',
'sam_item_t',
'sam_itemfeedback_t',
'sam_itemgrading_t',
'sam_itemmetadata_t',
'sam_itemtext_t',
'sam_media_t',
'sam_publishedanswer_t',
'sam_publishedanswerfeedback_t',
'sam_publishedassessment_t',
'sam_publishedattachment_t',
'sam_publisheditem_t',
'sam_publisheditemfeedback_t',
'sam_publisheditemmetadata_t',
'sam_publisheditemtext_t',
'sam_publishedmetadata_t',
'sam_publishedsection_t',
'sam_publishedsectionmetadata_t',
'sam_publishedsecuredip_t',
'sam_questionpool_t',
'sam_section_t',
'sam_sectionmetadata_t',
'sam_securedip_t',
'sam_studentgradingsummary_t',
'sam_type_t',
'signup_meetings',
'signup_sites',
'signup_ts',
);

function ceiling($number, $significance = 1) {
  return ( is_numeric($number) && is_numeric($significance) ) ? (ceil($number/$significance)*$significance) : false;
}
