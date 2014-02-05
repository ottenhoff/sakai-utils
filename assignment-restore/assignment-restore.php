<?php

if (count ($argv) !== 4) {
 print ("Three parameters are required: db_name, date (YYYY-MM-DD), and site id. \n\n");
 exit (1);
}

$db_name = $argv[1];
$date = $argv[2];
$site_id = $argv[3];

$output = array();
$conn = new mysqli('localhost', 'root', '', 'aaa_archive');

$files = array(
  'ASSIGNMENT_ASSIGNMENT-schema.sql',
  'ASSIGNMENT_ASSIGNMENT.sql',
  'ASSIGNMENT_CONTENT-schema.sql',
  'ASSIGNMENT_CONTENT.sql',
  'ASSIGNMENT_SUBMISSION-schema.sql',
  'ASSIGNMENT_SUBMISSION.sql',
);

if ($date != 'false') {
  foreach ($files AS $file) {
    $filename = "/mnt/massive06/b/mysql/server105-individual-increments/$db_name/$db_name.$file";
    if (!is_file($filename)) $filename = str_replace("server105", "server106", $filename);
    var_dump($filename);
    if (!is_file($filename)) exit("Could not find file $filename \n");

    print "Restoring $file as of $date \n";
    exec("rdiff-backup -r \"$date\" $filename /tmp/$file", $output);
    if (!is_file("/tmp/$file")) {
      print "Restore did not work \n\n";
      print_r($output);
      exit (1);
    }
  }

  foreach ($files AS $file) {
    print "Loading $file into archive database";
    exec("mysql -u root aaa_archive < /tmp/$file");
  }
}

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
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'ASSIGNMENT_ID IN ($a_sql)' aaa_archive ASSIGNMENT_ASSIGNMENT >> /tmp/assignment-restore.sql");

print "Restoring ASSIGNMENT_CONTENT to /tmp/assignment-restore.sql \n";
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'CONTENT_ID IN ($c_sql)' aaa_archive ASSIGNMENT_CONTENT >> /tmp/assignment-restore.sql");

print "Restoring ASSIGNMENT_SUBMISSION to /tmp/assignment-restore.sql \n";
exec("mysqldump --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -u root --where 'CONTEXT IN ($a_sql)' aaa_archive ASSIGNMENT_SUBMISSION >> /tmp/assignment-restore.sql");
