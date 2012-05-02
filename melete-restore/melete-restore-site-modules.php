<?php
$out = array();
$tail = array();
$modules = array();
$sections = array();
$resources = array();
$files = array();

if (count ($argv) !== 6) {
 exit ("Five parameters are required: database, site_id, dest_dir, restore date, base dir. \n\n");
}

$db = $argv[1];
$site_id = $argv[2];
$dest_dir = $argv[3];
$date = $argv[4];
$base_dir = $argv[5];

// create the database connection
$conn = new mysqli ("localhost", "root", "", $db);

if (!$conn) {
    exit ("Could not create database connection\n");
}

// create the destination dir
if (!is_dir ($dest_dir)) {
    mkdir ($dest_dir);
}

$res = $conn->query ("SELECT * fROM melete_course_module WHERE COURSE_ID='$site_id'");

$fields = array();
while ($finfo = $res->fetch_field()) {
    $fields[] = $finfo->name;
}

while ($row = $res->fetch_row() ) {
  $z = array();

  // store the module ids 
  $modules[] = $row[0];

  // now get all data to insert
  foreach ($row AS $r) {
    $z[] = $r;
  }

  $tmp = "INSERT INTO melete_course_module (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
  $tmp = str_replace ('"0"', 0, $tmp);
  $tmp = str_replace ('"1"', 1, $tmp);
  $tmp = str_replace ('""', 'NULL', $tmp);
  $tail[] = $tmp;
}

// now get the melete_modules data
foreach ($modules AS $module) {
     $res = $conn->query ("SELECT * fROM melete_module WHERE MODULE_ID='$module'");

    $fields = array();
    while ($finfo = $res->fetch_field()) {
        $fields[] = $finfo->name;
    }

    while ($row = $res->fetch_row() ) {
        $z = array();
         foreach ($row AS $r) {
             $z[] = $conn->real_escape_string ($r);
         }

         $out[] = "INSERT INTO melete_module (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
    }

    // now get the sections
    $res = $conn->query ("SELECT * fROM melete_section WHERE MODULE_ID='$module'");

    $fields = array();
    while ($finfo = $res->fetch_field()) {
        $fields[] = $finfo->name;
    }

    while ($row = $res->fetch_row() ) {
        $z = array();
         $sections[] = $row[0];

         foreach ($row AS $r) {
             $z[] = $conn->real_escape_string ($r);
         }

         $tmp = "INSERT INTO melete_section (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
         $tmp = str_replace ('"0"', 0, $tmp);
         $tmp = str_replace ('"1"', 1, $tmp);
         $tmp = str_replace ('""', 'NULL', $tmp);
         $out[] = $tmp;
    }
}

// get the dates applied to each
foreach ($modules AS $module) {
     $res = $conn->query ("SELECT * fROM melete_module_shdates WHERE MODULE_ID='$module'");

    $fields = array();
    while ($finfo = $res->fetch_field()) {
        $fields[] = $finfo->name;
    }

    while ($row = $res->fetch_row() ) {
        $z = array();
         foreach ($row AS $r) {
             $z[] = $conn->real_escape_string ($r);
         }

         $tmp = "INSERT INTO melete_module_shdates (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
         $out[] = str_replace ('""', 'NULL', $tmp);
    }
}

// now get the section_resource data
foreach ($sections AS $section) {
     $res = $conn->query ("SELECT * fROM melete_section_resource WHERE SECTION_ID='$section'");

    $fields = array();
    while ($finfo = $res->fetch_field()) {
        $fields[] = $finfo->name;
    }

    while ($row = $res->fetch_row() ) {
        $resource_id = $row[1];
        $res2 = $conn->query ("SELECT * fROM melete_resource WHERE RESOURCE_ID='$resource_id'");
    
        $fields2 = array();
        while ($finfo = $res2->fetch_field()) {
            $fields2[] = $finfo->name;
        }

        while ($row2 = $res2->fetch_row() ) {
            $z = array();
            foreach ($row2 AS $r) {
                $z[] = $conn->real_escape_string ($r);
            }

            $out[] = "INSERT INTO melete_resource (" . implode (",", $fields2) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
        }

        $z = array();
         $resources[] = $row[1];

         foreach ($row AS $r) {
             $z[] = $conn->real_escape_string ($r);
         }

         $out[] = "INSERT INTO melete_section_resource (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
    }
}

// now get the resources data
foreach ($resources AS $resource) {
     $res = $conn->query ("SELECT * fROM CONTENT_RESOURCE WHERE RESOURCE_ID='$resource'");

    $fields = array();
    while ($finfo = $res->fetch_field()) {
        $fields[] = $finfo->name;
    }

    while ($row = $res->fetch_row() ) {
        $z = array();
        $files[] = $row[4];

         foreach ($row AS $r) {
             $z[] = $conn->real_escape_string ($r);
         }

         $out[] = "INSERT INTO CONTENT_RESOURCE (" . implode (",", $fields) . ") VALUES (\"" . implode ("\",\"", $z) . "\");";
    }
}

// write the insert statements to file
$sql = implode ("\n", $out) . "\n\n\n" . implode ("\n", $tail);
file_put_contents ($dest_dir . "/restore-inserts.sql", $sql);
echo "wrote the insert to $dest_dir/restore-inserts.sql \n\n";

$restores = array();

foreach ($files AS $file) {
    // remove the first slash
    $file = substr ($file, 1);

    $path = pathinfo ($dest_dir . "/" . $file);
    if (!is_dir ($path['dirname'])) {
      mkdir ($path['dirname'], 0755, true);
    }

    $restores[] = ("rdiff-backup -r \"$date\" $base_dir/$file $dest_dir/$file");
}

// write the rdiffs to file
file_put_contents ($dest_dir . "/rdiff-commands.sh", implode ("\n", $restores));
