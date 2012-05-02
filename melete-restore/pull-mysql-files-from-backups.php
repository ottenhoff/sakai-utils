<?php

if (count ($argv) !== 5) {
 exit ("Four parameters are required: base_dir, dest_dir, db_name, and date (YYYY-MM-DD). \n\n");
}

$base_dir = $argv[1];
$dest_dir = $argv[2];
$db_name = $argv[3];
$date = $argv[4];

$files = array(
'CONTENT_RESOURCE-schema.sql',
'CONTENT_RESOURCE.sql',
'melete_access_group-schema.sql',
'melete_access_group.sql',
'melete_bookmark-schema.sql',
'melete_bookmark.sql',
'melete_cc_license-schema.sql',
'melete_cc_license.sql',
'melete_course_module-schema.sql',
'melete_course_module.sql',
'melete_license-schema.sql',
'melete_license.sql',
'melete_module-schema.sql',
'melete_module_shdates-schema.sql',
'melete_module_shdates.sql',
'melete_module.sql',
'melete_resource-schema.sql',
'melete_resource.sql',
'melete_section_resource-schema.sql',
'melete_section_resource.sql',
'melete_section-schema.sql',
'melete_section.sql',
'melete_site_preference-schema.sql',
'melete_site_preference.sql',
'melete_special_access-schema.sql',
'melete_special_access.sql',
'melete_user_preference-schema.sql',
'melete_user_preference.sql',
);

foreach ($files AS $file) {
  print "Restoring $file as of $date \n";
  exec("rdiff-backup -r \"$date\" $base_dir/$db_name.$file $dest_dir/$file");

}
