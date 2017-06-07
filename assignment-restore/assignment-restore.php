<?php

if (count ($argv) !== 4) {
 print ("Three parameters are required: database name, site id, and lowercase 0/1 flag. \n\n");
 exit (1);
}

$dbname = $argv[1];
$site_id = $argv[2];
$lower = (bool)$argv[3];

$aa_t = "ASSIGNMENT_ASSIGNMENT";
$ac_t = "ASSIGNMENT_CONTENT";
$as_t = "ASSIGNMENT_SUBMISSION";

if ($lower) {
  $aa_t = strtolower ($aa_t);
  $ac_t = strtolower ($ac_t);
  $as_t = strtolower ($as_t);
}

$output = array();
$conn = new mysqli('est58-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com', 'apurestore', 'restore19395', $dbname);
var_dump($conn);

$assignments = array();
$res = $conn->query("SELECT ASSIGNMENT_ID FROM $aa_t WHERE CONTEXT='$site_id'");
if (!$res) die ("Could not retrieve where CONTEXT=$site_id");

while ($row = $res->fetch_object()) {
  $assignments[] = $row->ASSIGNMENT_ID;
}

print "Found " . count($assignments) . " assignments in $aa_t \n";

$contents = array();
foreach ($assignments AS $assignment) {
  // Find the content id by looking through the XML
  $res = $conn->query("SELECT * FROM $aa_t WHERE ASSIGNMENT_ID='$assignment'");
  $obj = $res->fetch_object();
  $xml = simplexml_load_string(utf8_encode($obj->XML));
  $ac = $xml->attributes()->assignmentcontent;
  $parts = explode("/", $ac);
  $contents[] = array_pop($parts);
}

$a_sql = '"' . implode('","', $assignments) . '"';
$c_sql = '"' . implode('","', $contents) . '"';

print "Restoring $aa_t to /tmp/assignment-restore.sql \n";
exec("mysqldump --extended-insert=FALSE --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -h est58-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com -u apurestore -prestore19395 --where 'ASSIGNMENT_ID IN ($a_sql)' $dbname $aa_t >> /tmp/assignment-restore.sql");

print "Restoring $ac_t to /tmp/assignment-restore.sql \n";
exec("mysqldump --extended-insert=FALSE --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -h est58-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com -u apurestore -prestore19395 --where 'CONTENT_ID IN ($c_sql)' $dbname $ac_t >> /tmp/assignment-restore.sql");

print "Restoring $as_t to /tmp/assignment-restore.sql \n";
exec("mysqldump --extended-insert=FALSE --skip-set-charset --skip-add-locks --skip-disable-keys --skip-comments --no-create-info -h est58-cluster.cluster-ctj7ow5gksfj.us-east-1.rds.amazonaws.com -u apurestore -prestore19395 --where 'CONTEXT IN ($a_sql)' $dbname $as_t >> /tmp/assignment-restore.sql");

exec ("cd /tmp;zip -9 --password TZ9mnqR7FTvwWQ4J assignment-restore.sql.zip assignment-restore.sql");

$separator = md5(time());
$eol = PHP_EOL;
$attachment = chunk_split(base64_encode(file_get_contents("/tmp/assignment-restore.sql.zip")));
$headers  = "From: lssakai@longsight.com".$eol;
$headers .= "MIME-Version: 1.0".$eol; 
$headers .= "Content-Type: multipart/mixed; boundary=\"".$separator."\"";

$body = "--".$separator.$eol;
$body .= "Content-Transfer-Encoding: 7bit".$eol.$eol;
$body .= "This is a MIME encoded message.".$eol;

// message
$body .= "--".$separator.$eol;
$body .= "Content-Type: text/html; charset=\"iso-8859-1\"".$eol;
$body .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
// $body .= $message.$eol;

// attachment
$body .= "--".$separator.$eol;
$body .= "Content-Type: application/octet-stream; name=\"assignment-restore.sql.zip\"".$eol; 
$body .= "Content-Transfer-Encoding: base64".$eol;
$body .= "Content-Disposition: attachment".$eol.$eol;
$body .= $attachment.$eol;
$body .= "--".$separator."--";

// send message
//if (mail("ottenhoff@longsight.com, derek@longsight.com", "Assignment Restore for Site: $site_id", $body, $headers)) {
if (mail("ottenhoff@longsight.com", "Assignment Restore for Site: $site_id", $body, $headers)) {
  echo "mail sent";
} 
else {
  echo "Error,Mail not sent";
}
