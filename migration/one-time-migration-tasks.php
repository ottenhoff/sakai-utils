<?php
require "migration-helper.php";

$handle = fopen("sites.csv", "r");

$sites = array();
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $sites[] = trim($data[0]);
}

$sites_sql = "'" . implode("','", $sites) . "'";
