#!/usr/bin/php
<?php
define('GOOD', 1);
define('TOMCAT_DOWN', 2);
define('TOMCAT_NOTEXIST', 3);
define('TOMCAT_NOSHUTDOWN', 4);
define('FILE_MISSING', 5);
define('IN_PROGRESS', 10);

$args = $_SERVER['argv'];

// ESTABLISH VARIABLES
$immediate = null;
$now = time();
$output = array();
$token = "xxxxxxxxxxxxxxxxx";
$url = "https://example.com/sakai";
$patchdir = "/tmp/patches/";


// FIND ALL IPS THIS SERVER IS RESPONSIBLE FOR 
$ips = array();
$ignore = exec("/sbin/ifconfig -a | perl -nle'/(\d+\.\d+\.\d+\.\d+)/ && print $1'", $ips);
$context = stream_context_create(array('http'=> array('method' => "GET", 'header' => "X-Auth-Token: $token\r\n")));

// IGNORE NOT INTERNAL IPS
foreach ($ips AS $key => $val) {
        $pieces = explode('.', $val);

        if ((int)$pieces[0] !== 192 && (int)$pieces[0] != 10) {
                unset($ips[$key]);
        }
}

// SEE IF WE NEED TO RESTART SERVER IMMEDIATELY
if (count($args) == 2) {
        $immediate = addslashes($args[1]);
        $str = file_get_contents ($url . "/json/patches?ips=" . json_encode (array_values($ips)) . "&jvm_route=" . $immediate, false, $context);
        $patch = json_decode ($str);

        if (!$patch) {
                $login_user = posix_getlogin();

        // The submitted form data, encoded as query-string-style
        // name-value pairs
                $body = "login_user=$login_user&jvm_route=$immediate";
                $c = curl_init ($url . '/remote/patch/update');
                curl_setopt ($c, CURLOPT_POST, true);
                curl_setopt ($c, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt ($c, CURLOPT_POSTFIELDS, $body);
                curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
                $page = curl_exec ($c);
                curl_close ($c);
        }
}
// DIE IF WE HAVE NO IPS
if (count($ips) == 0) {
        exit(1);
}
// CHECK IF WE HAVE ANY RELEVANT PATCHES
$str = file_get_contents ($url . "/json/patches?ips=" . json_encode (array_values($ips)), false, $context);
$patch = json_decode ($str);

// EXIT IF NO PATCHES
if (!$patch) {
        exit(0);
}
// run the patch
patch($patch);

function patch($patch) {
        $hotdeploy = $patch->hotdeploy && $patch->hotdeploy > 0 ? true : false;
        error_log('' . $patch->hotdeploy, 3, "/tmp/myerror.log");
        error_log('' . $hotdeploy, 3, "/tmp/myerror.log");

        $test_file = $patch->tomcat_dir . '/conf/server.xml';

        // DIE IF WE DONT HAVE ACCESS TO THE PATCH
        if (!is_file($test_file)) {
                update_patch($patch->patch_id, TOMCAT_NOTEXIST);
        }
        // FIND THE OWNER OF A CONF FILE
  $sakai_uid = fileowner($test_file);
  $script_uid = posix_getuid();


  if (!$sakai_uid || !$script_uid) {
                update_patch ($patch->patch_id, TOMCAT_NOTEXIST);
  }
  // ONLY RUN IF WE ARE RUNNING UNDER THE RIGHT USER!!!!!!!!!
  if ($script_uid === 0 || $sakai_uid !== $script_uid) {
    exit(0);
  }
        // MARK THAT WE ARE IN PROGRESS SO PATCHES DONT OVERRUN EACH OTHER
        update_patch($patch->patch_id, IN_PROGRESS);
  // SEE IF WE NEED TO MODIFY THE SAKAI.PROEPRTIES
  if (!empty($patch->sakaiprops)) {
    $propstring = preg_replace('/\r\n|\r/', "\n", $patch->sakaiprops);
    $props = explode("\n", $propstring);
    
    $lines = file($patch->tomcat_dir . '/sakai/sakai.properties');
    $newlines = array();

    foreach ($lines AS $line_num => $line) {
      if (strpos($line, "=") === FALSE) {
        $newlines[] = $line; 
        continue;
      }
      if (substr($line, 0, 1) == "#") {
        $newlines[] = $line; 
        continue;
      }

      $first_part_line = explode("=", $line);

      $matched = FALSE;
      foreach ($props AS $prop_key => $prop) {
        $first_part_prop = explode("=", $prop);

        // MATCH FOR AN EXISTING LINE
        if ($first_part_line[0] == $first_part_prop[0]) {
          $newlines[] = "#" . $line;
          $newlines[] = "#SAKAIPATCHER ID: " . $patch->patch_id . " ; DATE: " . date('c') . "\n";
          $newlines[] = $prop . "\n";

          $matched = TRUE;
          unset ($props[$prop_key]);
        }
      }

      if (!$matched) {
        $newlines[] = $line;
      }
    }

    // SEE IF ANY OF THE PROPS WERE NEW ONES AND NOT REPLACEMENTS
    if (count($props) > 0) {
      foreach ($props AS $prop_key => $prop) {
        $newlines[] = $prop . "\n";
      }
    }

    file_put_contents($patch->tomcat_dir . '/sakai/sakai.properties', $newlines);
  }
        // SET UP ARRAY OF FILES TO CLEAN BEFORE PATCHING
        $removes = array();
        $removes[] = 'work/Catalina/localhost';

    // ONLY CHECK OUT THE TAR IF WE HAVE FILES
    if (!empty($patch->files)) {
        // Need to transfer the files over to this server
        $individual_files = array($patch->files);
        if (strpos($patch->files, " ") !== FALSE) {
          $individual_files = explode(" ", $patch->files);
        }

        foreach ($individual_files AS $sf) {
          $f = pathinfo($sf);
          if (!is_file($patchdir . $f['basename'])) {
            $ret = copy ('http://74.201.2.240/public_patches/' . $f['basename'], $patchdir . $f['basename']);
            print "Copying " . $f['basename'] . " to $patchdir :: $ret \n";
          }
        }
      }

      // SEE WHAT FILES ARE IN THE PATCH
      $tar_contents = array();

      // SEE IF THERE ARE MULTIPLE FILES
      if (strpos($patch->files, " ") !== false) {
        $thefiles = explode(" ", $patch->files);

        foreach ($thefiles AS $indiv) {
          if (!is_file($indiv)) update_patch ($patch->patch_id, FILE_MISSING);

          $ignore = exec('tar tzf ' . $indiv, $tar_contents);
        }
      }
      else {
        if (!is_file($patch->files)) update_patch ($patch->patch_id, FILE_MISSING);
        $ignore = exec('tar tzf ' . $patch->files, $tar_contents);
      }

      // LOOK INSIDE THE PATCH
      //    $components_number = array();
            $components_number = 0;
      $comp = array();
                        $comp_wildcards = array();

      foreach ($tar_contents AS $key => $filename) {
        $ext = null;
        
        // CHECK THE FINAL FOUR CHARACTERS
        $four = substr($filename, strlen($filename) - 4);

        if (substr($four, 0, 1) == '.') {
          $ext = substr($filename, strrpos($filename, '.') + 1);
        }

        $first = substr($filename, 0, strpos($filename, '/'));

        // CLEAN OUT ENTIRE COMPONENTS DIR 
              if ($first == 'components' && strlen($filename) > strlen('components/a') && count($tar_contents) > 5 ) {
          $compStr = substr($filename, 0, strpos($filename, '/', 12));
                $comp[md5($compStr)] = $compStr;
          $components_number++;

                                        if (!empty($ext) && strtolower($ext) == 'jar') {
                                                $replace_name_array = get_wildcarded_filename($filename);
                                                $dirname = dirname($filename);

                                                foreach ($replace_name_array AS $replace_name) {
                                                        $comp_wildcards[] = $dirname . '/' . $replace_name;
                                                }
                                        }
        }
        // CLEAN OUT SHARED WITH AGGRESSIVE WILDCARD
        elseif ($first == 'shared' && !empty($ext) && strtolower($ext) == 'jar' && strlen($filename) > strlen('shared/lib/a')) {
                                        $replace_name_array = get_wildcarded_filename($filename);
                                        foreach ($replace_name_array AS $replace_name) {
                                                $replace_name = 'shared/lib/' . $replace_name;
                                                $removes[md5($replace_name)] = $replace_name;
                                        }

        }
        // IF THIS IS A WAR FILE, CLEAN OUT OLD ONE TO BE SAFE
        elseif ($first == 'webapps' && !empty($ext) && strtolower($ext) == 'war') {
          $removes[md5($filename)] = $filename;
          $removes[md5($filename)] = str_replace('.' . $ext, '', $filename);
        }
              elseif ($first == 'common' && !empty($ext) && strtolower($ext) == 'jar' && strlen($filename) > strlen('common/lib/a')) {
          $replace_name_array = get_wildcarded_filename($filename);
          foreach ($replace_name_array AS $replace_name) {
            $replace_name = 'common/lib/' . $replace_name;
            $removes[md5($replace_name)] = $replace_name;
          }                                     
       }
     }
          if (count($comp_wildcards) > 0) {
                  foreach ($comp_wildcards AS $cw) {
                          $removes[md5($cw)] = $cw;
                  }
          }

    }

  // CHECK IF THE PROCESS IS ALIVE
  $proc = check_for_process($patch->tomcat_dir);
 
  if ($proc) {
    if(!$hotdeploy){
            $cmd = "cd " . $patch->tomcat_dir . ";bin/shutdown.sh";
            $output[] = $cmd;
        exec($cmd, $output);

        for ($x = 15; $x < 135;$x++) {
                $proc = check_for_process($patch->tomcat_dir);
                sleep(1);
                if (!$proc) break;
        }

        // CHECK PROCESS ONE LAST TIME
        $proc = check_for_process($patch->tomcat_dir);

        // TRY TO KILL TOMCAT
        if ($proc) {
        $ps = get_process_number($patch->tomcat_dir);
        if ($ps) {
                $cmd = 'kill -9 ' . $ps;
                      $output[] = $cmd;
                exec($cmd, $output);
                      sleep(5);
        }
        }

        // CHECK PROCESS ONE LAST TIME
        $proc = check_for_process($patch->tomcat_dir);
        if ($proc) {
                update_patch($patch->patch_id, TOMCAT_NOSHUTDOWN, $output);
        }

     //end if(!$hotdeploy)
     }
    //end if(proc)
    }
        // REMOVE OLD DIRS
        foreach ($removes AS $dir) {
                $first_letter = (substr($dir,0,1));

                if ($first_letter == "/" || $first_letter == ".") {
                        die("BAD REMOVE FIELDS");
                }

                $cmd = "cd " . $patch->tomcat_dir . ";rm -rf " . $dir;
                    $output[] = $cmd;
                exec($cmd, $output);
        }
    // ONLY UNROLL THE TAR IF WE HAVE FILES
    if (!empty($patch->files)) {
      // SEE IF THERE ARE MULTIPLE FILES
      if (strpos($patch->files, " ") !== false) {
        $thefiles = explode(" ", $patch->files);

        foreach ($thefiles AS $indiv) {
                $cmd = "cd " . $patch->tomcat_dir . ";tar xzf " . $indiv;
                $output[] = $cmd;
                exec($cmd, $output);
        }
      }
      else {
        $cmd = "cd " . $patch->tomcat_dir . ";tar xzf " . $patch->files;
        $output[] = $cmd;
        exec($cmd, $output);
                        }
    }
  
  if(!$hotdeploy){
        $cmd = "cd " . $patch->tomcat_dir . ";bin/startup.sh";
        $output[] = $cmd;
        exec($cmd, $output);
   
        // SLEEP FOR 60 seconds and then check the logs
        sleep(200);

        for ($x = 0; $x < 320; $x++) {
      $cmd = 'tail -n 250 ' . $patch->tomcat_dir . '/logs/catalina.out';

      $logs = array();
      exec ($cmd, $logs);

      // LOOK AT THE LOGS
      if (in_array('destroy', $logs)) {
        update_patch($patch->patch_id, TOMCAT_DOWN, $output);
      }

      // SEE HOW LONG IT TOOK TO STARTUP
      foreach ($logs AS $key => $lin) {
        if (preg_match('/Server startup in/i', $lin)) {
          $twoparts = explode('Server startup in', $lin);
          $int1 = (int)$twoparts[1];

          update_patch($patch->patch_id, GOOD, $output, $int1);
        }
      }

      sleep(2);
        }
  }
  else {
    //Since this is hotdeployed, just update the patch to GOOD (which will exit the script)
    update_patch($patch->patch_id, GOOD, $output, 0);
  }
  update_patch($patch->patch_id, TOMCAT_DOWN, $output, 0);
}

function update_patch($patch_id, $result, $output=array(), $startup = 0) {
        $time = time();
  $text = '';

  if (count($output)) {
    $text = implode("\n", $output);
  }

  // The submitted form data, encoded as query-string-style
  // name-value pairs
  $body = "result_value=$result&start_uptime=$startup&last_attempt=$time&patch_id=$patch_id&result=$text";
        $c = curl_init ($url . '/remote/patch/update');
        curl_setopt ($c, CURLOPT_POST, true);
        curl_setopt ($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt ($c, CURLOPT_POSTFIELDS, $body);
        curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
        $page = curl_exec ($c);
        curl_close ($c);

        if ($result == IN_PROGRESS) {
                return;
        }

        if ($result == 1) {
                exit(0);
        }
        else {
                exit($result);
        }
}

function check_for_process($dir) {
  // create our system command
  $cmd = "ps x|grep $dir|grep -v grep|grep java";

  if (strpos($dir, 'dev') === FALSE) {
    $cmd .= "|grep -v '\-dev'";
  }
 
  // run the system command and assign output to a variable ($output)
  exec($cmd, $output, $result);
 
  // check the number of lines that were returned
  if(count($output) >= 1){
    // the process is still alive
    return true;
  }
 
  // the process is dead
  return false;
}

function get_process_number($dir) {
  // create our system command
  $cmd = "ps x|grep $dir|grep -v grep|grep java";

  if (strpos($dir, 'dev') === FALSE) {
    $cmd .= "|grep -v '\-dev'";
  }
 
  // run the system command and assign output to a variable ($output)
  exec($cmd, $output, $result);
 
  // check the number of lines that were returned
  if(count($output) == 1) {
          $arr = explode(" ", trim($output[0]));

    $ps = (int)$arr[0];

    if ($ps > 100) {
      return $ps;
    }
  }
 
  // the process is dead
  return false;
}

function get_wildcarded_filename($filename) {
  //assuming all jars consist of {basename}{version-number}, we break it into 2 parts:
  //take just the base name (i.e. everything until a number) and then remove
  //everything that consist of {basename}#.
  //
  //ex:   
  //      "messageforums-api-2.7.4-SNAPSHOT.jar"
  //
  //    $basename = messageforums-api-
  //
  //    rm messageforums-api-1*, rm messageforums-api-2*, rm messageforums-api-3*...

  //step 1, get base name:  (ie all letters until you hit a number)
  $bname = basename($filename, ".jar");
  $newBaseName = "";
  $sSplit = str_split($bname);
  foreach($sSplit as $c){
    if(intval($c)){
      break;
    }
    $newBaseName .= $c;
  }
  $bname = $newBaseName;


  //step 2:  create all "rm" commands based on basename + # + *.jar

  $returnArray = array();
  for ($i = 0; $i < 10; $i++) {
    $returnArray[$i] = $bname . $i . "*.jar";
  }

  return $returnArray;
}

// vim: set filetype=php expandtab tabstop=2 shiftwidth=2 autoindent smartindent:
