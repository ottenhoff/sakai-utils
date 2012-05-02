#!/usr/bin/php
<?php
set_time_limit(290);

$post_url = "https://admin.example.edu/sakai/healthinfo";

if (count ($argv) !== 3) {
    exit ("Please include two parameters: secret and ips. \n");
}

$secret = $argv[1];
$ips = $argv[2];

$java = "/usr/lib/jvm/java-1.6.0-openjdk-1.6.0.0.x86_64/jre/bin/java";
$check_http = "/usr/lib64/nagios/plugins/check_http";

// KILL ALL OLD CHECKS
#exec('killall check_jmx');

$skip_external = true;

// THE JMX COMMANDS WE WILL CHECK
$commands = array();
$commands[1] = "java.lang:type=Threading -A DaemonThreadCount -K used -I DaemonThreadCount -J used";
$commands[2] = "java.lang:type=Memory -A HeapMemoryUsage -K used -I HeapMemoryUsage -J used";
$commands[4] = "java.lang:type=Threading -A ThreadCount -K used -I ThreadCount -J used";
$commands[5] = "org.sakaiproject:name=Sessions -A Active15Min -K used -I Active15Min -J used";
$commands[6] = "java.lang:type=OperatingSystem -A MaxFileDescriptorCount -K used -I MaxFileDescriptorCount -J used";
$commands[7] = "java.lang:type=OperatingSystem -A OpenFileDescriptorCount -K used -I OpenFileDescriptorCount -J used";
$commands[8] = "Catalina:type=Manager,path=/,host=localhost -A activeSessions -I activeSessions";
$commands[9] = "java.lang:type=OperatingSystem -A ProcessCpuTime -K used -I ProcessCpuTime -J used";

$str = file_get_contents ("https://admin.example.edu/sakai/json/instances?secret=$secret&ips=$ips");
$res = json_decode ($str);

if (!$res) {
  exit ("Could not get info from admin portal \n");
}

$data = array();
foreach ($res AS $row) {
  $server_id = $row->server_id;
  $server = $row->server_ip . ":" . $row->jmx_port;

  // SEE IF THIS SERVER IS CURRENTLY BEING PATCHED
  $patch_time = max ($row->last_patch, $row->recur_patch);

  // SKIP THIS SERVER IF IT IS CURRENTLY BEING PATCHED
  if ($patch_time > 0) {
    $abs_diff = abs(time() - $patch_time);

    if ($abs_diff < 300) {
      continue;
    }
  }

  // CHECK HTTP SPEED
  $url = "/";

  if ($row->project_name == 'sakai' || strpos($row->tomcat_dir, 'sakai') !== FALSE) {
    $url = "/portal/";
  }
  elseif ($row->jvm_route == 'iaim_014') {
    $url = "/coursework/";
  }

  $c = "$check_http -I " . $row->server_ip . " -p " . $row->http_port . " -u " . $url;
  $ret_str = exec ($c);
  $ret_arr = explode ("|", $ret_str);

  $time = 0;
  foreach ($ret_arr AS $line) {
    if (eregi("time", $line) && strpos($line, ";;;") !== FALSE ) {
      $temp = explode(";;;", $line);
      $t2 = explode("=", $temp[0]);
      $time = $t2[1];
      $time = $time*1000*1000;
    }
  }

  // TEST NON-LDAP TIME
  $ldap_time = 0;
  if (($time > 4000000 || $time == 0) && strpos($row->tomcat_dir, 'sakai') !== FALSE) {
    $c = "$check_http -I " . $row->server_ip . " -p " . $row->http_port . " -u /portal/xlogin";
    $ret_str = exec($c);
    $ret_arr2 = explode("|", $ret_str);

    foreach ($ret_arr2 AS $line) {
      if (eregi("time", $line)) {
        $temp2 = explode(";;;", $line);
        $t3 = explode("=", $temp2[0]);
        $ldap_time = (int) $t3[1];
        $ldap_time = (float)$ldap_time*1000;
      }
    }
  }

  // Add the times to the data array
  $data[$server_id]['time'] = $time;
  $data[$server_id]['ldap_time'] = $ldap_time;

  // CHECK THE JMX COMMANDS
  foreach ($commands AS $data_id => $command) {
    $c = "nice -n19 $java -cp " . getcwd() . "/jmxquery.jar org.nagios.JMXQuery -U service:jmx:rmi:///jndi/rmi://" . $server . "/jmxrmi -O " . $command;

    $ttt = time();
    $ret_str = exec($c);
    if (time() - $ttt > 1) {
//error_log($c);
    }

    if (!empty($ret_str) && eregi("OK", $ret_str)) {
      $arr = explode(" ", $ret_str);
      $arr = array_reverse($arr);

      if (is_numeric($arr[0]) && $num = $arr[0]) {
        $data[$server_id][$data_id] = $num;

        // check the amount of threads against average
        if ($data_id == 4) {
          $thread_count = $num;
        }

      }
    }
  }

}

$body = "time=" . time() . "&secret=$secret&data=" . json_encode ($data);
$c = curl_init ($post_url);
curl_setopt ($c, CURLOPT_POST, true);
curl_setopt ($c, CURLOPT_POSTFIELDS, $body);
curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
$page = curl_exec ($c);
curl_close ($c);
