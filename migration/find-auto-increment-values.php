<?php
require "migration-helper.php";

$res = $t->query("select * from information_schema.columns where extra like '%auto_increment%' and table_schema='$target' ");

$max = 0;
while ($row = $res->fetch_object()) {
  $res2 = $t->query("SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$row->TABLE_SCHEMA' AND TABLE_NAME = '$row->TABLE_NAME' ");
  $row2 = $res2->fetch_object();
  $ai = (int) $row2->AUTO_INCREMENT;
  // print "'" . $row->TABLE_NAME . "',\n";

  if (in_array($row->TABLE_NAME, $good_tables)) {
    $ai += 380000;
    $ai = ceiling($ai, 10000);
    //print "\$tables['" . $row->TABLE_NAME . "'] = " . $ai . "; \n";
    print "BEGIN WORK; SET foreign_key_checks = 0; INSERT INTO $row->TABLE_NAME ($row->COLUMN_NAME) VALUES ($ai); SET foreign_key_checks = 1; ROLLBACK;\n";
  }

  if ($ai > $max) {
    $max = $ai;
  }
}
