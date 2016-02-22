<?php
set_time_limit(0);

$arr = sakai_soap_connect('http://10.10.10.10:8080', 'import', 'password', true);
list ($soap, $session, $soapLS) = $arr;

$handle = fopen("sites.csv", "r");

if (!$handle) {
  die("No CSV");
}

$terms = array();
$terms['9999'] = '2012 Spring Term';
$terms['OTHER'] = 'Other';

$cnt = 0;
while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
  $cnt++;

  // skip the header line
  if ($cnt == 1) continue;

  $filename = '/path/to/files/' . $data[0];

  $site_id = $data[1];
  $users = (array) explode(",", $data[2]);
  $term = $data[3];
  $type = $data[4];

  if (!is_file($filename)) {
    print "Missing file: $filename \n";
    continue;
  }

  $site_exist = $soapLS->longsightSiteExists($session, $site_id);

  if((string)$site_exist == "false") {
    $ret = $soap->addNewSite($session, $site_id, $site_id, $site_id, $site_id, '', '', false, 'Student', false, false, '', $type);
    sleep(1);

    if ($type != 'project') {
      $ret = $soapLS->longsightAddPropertiesToSite($session, $site_id, 'help@domain.edu', 'Sakai Admin', $terms[$term], $term);
    }

    $ret = $soap->addNewPageToSite($session, $site_id, "Home", 1);
    $ret = $soapLS->addConfigPropertyToPage($session, $site_id, "Home", "is_home_page", "true");
    $ret = $soap->addNewToolToPage($session, $site_id, "Home", "Site Information Display", "sakai.iframe.site", "0,0");
    $ret = $soap->addConfigPropertyToTool($session, $site_id, "Home", "Worksite Information", "special", "worksite");
    $ret = $soap->addNewToolToPage($session, $site_id, "Home", "Recent Announcements", "sakai.synoptic.announcement", "0,1");

    $ret = $soap->addNewPageToSite($session, $site_id, "Announcements", 0);
    $ret = $soap->addNewToolToPage($session, $site_id, "Announcements", "Announcements", "sakai.announcements", "0,0");

    $ret = $soap->addNewPageToSite($session, $site_id, "Resources", 0);
    $ret = $soap->addNewToolToPage($session, $site_id, "Resources", "Resources", "sakai.resources", "0,0");

    $ret = $soap->addNewPageToSite($session, $site_id, "Site Info", 0);
    $ret = $soap->addNewToolToPage($session, $site_id, "Site Info", "Site Info", "sakai.siteinfo", "0,0");

    $ret = $soap->addNewPageToSite($session, $site_id, "Tests & Quizzes", 0);
    $ret = $soap->addNewToolToPage($session, $site_id, "Tests & Quizzes", "Tests & Quizzes", "sakai.samigo", "0,0");


    foreach ($users AS $uid) {
      $uid = $uid . '@domain.edu';

      if ($type == 'project') {
        $ret = $soap->addMemberToSiteWithRole($session, $site_id, strtolower($uid), "maintain");
      }
      else {
        $ret = $soap->addMemberToSiteWithRole($session, $site_id, strtolower($uid), "Instructor");
      }
    }
    $ret = $soapLS->longsightRemoveMemberFromSite($session, $site_id, "import");
    sleep(1);

    $ret = $soapLS->longsightImportFromFile($session, $site_id, $filename);
    print "Imported into $site_id, return value: $ret \n";
    sleep(3);
  }
}

function sakai_soap_connect($host=null, $user=null, $pass=null, $longsight=false) {
  $login_wsdl = $host .'/sakai-axis/SakaiLogin.jws?wsdl';
  $script_wsdl = $host .'/sakai-axis/SakaiScript.jws?wsdl';
  $longsight_wsdl = $host .'/sakai-axis/WSLongsight.jws?wsdl';

  $login = new SoapClient($login_wsdl, array('exceptions' => 0));

  $session = $login->login($user, $pass);

  if (_soap_error($session)) {
    return false;
  }

  $active = new SoapClient($script_wsdl, array('exceptions' => 0));

  if ($longsight) {
    $soapLS = new SoapClient($longsight_wsdl, array('exceptions' => 0));
    return array($active, $session, $soapLS);
  }

  return array($active, $session);
}
