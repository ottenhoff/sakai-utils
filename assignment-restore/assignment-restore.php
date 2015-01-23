<?php

if (count ($argv) !== 3) {
 print ("Two parameters are required: db_name and site id. \n\n");
 exit (1);
}

$db_name = $argv[1];
$site_id = $argv[2];

$output = array();
$conn = new mysqli('localhost', 'root', '', $db_name);

$assignments = array();
$res = $conn->query("SELECT ASSIGNMENT_ID FROM ASSIGNMENT_ASSIGNMENT WHERE CONTEXT='$site_id'");
while ($row = $res->fetch_object()) {
  $assignments[] = $row->ASSIGNMENT_ID;
}

print "Found " . count($assignments) . " assignments in ASSIGNMENT_ASSIGNMENT \n";

$contents = array();
foreach ($assignments AS $assignment) {
  // Find the content id by looking through the XML
  $res = $conn->query("SELECT * FROM ASSIGNMENT_ASSIGNMENT WHERE ASSIGNMENT_ID='$assignment'");
  $obj = $res->fetch_object();
  $xml = simplexml_load_string(utf8_encode($obj->XML));
  $ac = $xml->attributes()->assignmentcontent;
  $parts = explode("/", $ac);
  $contents[] = array_pop($parts);
}

$a_sql = '"' . implode('","', $assignments) . '"';
$c_sql = '"' . implode('","', $contents) . '"';

print "Restoring ASSIGNMENT_ASSIGNMENT to /tmp/assignment-restore.sql \n";
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'ASSIGNMENT_ID IN ($a_sql)' $db_name ASSIGNMENT_ASSIGNMENT >> /tmp/assignment-restore.sql");

print "Restoring ASSIGNMENT_CONTENT to /tmp/assignment-restore.sql \n";
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'CONTENT_ID IN ($c_sql)' $db_name ASSIGNMENT_CONTENT >> /tmp/assignment-restore.sql");

print "Restoring ASSIGNMENT_SUBMISSION to /tmp/assignment-restore.sql \n";
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'CONTEXT IN ($a_sql)' $db_name ASSIGNMENT_SUBMISSION >> /tmp/assignment-restore.sql");
