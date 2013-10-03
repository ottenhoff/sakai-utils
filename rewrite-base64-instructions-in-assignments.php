<?php

$conn = new mysqli('10.4.100.105', 'sakai28_client', 'password', 'sakai28_client');
if (!$conn->set_charset("utf8")) {
  printf("Error loading character set utf8: %s\n", $mysqli->error);
}

$res = $conn->query("SELECT * FROM ASSIGNMENT_CONTENT");

while ($row = $res->fetch_object()) {
  $oldb64 = null;
  $newb64 = null;
  $xml = simplexml_load_string($row->XML);
  if (!$xml) {
    var_dump($row);die();
  }

  foreach ($xml->attributes() AS $k => $v) {
    if (strpos($k, 'instructions') !== FALSE) {
      $oldb64 = $v;
      $d = base64_decode($v);
      if (strpos ($d, 'sakai.oldhost.com')) {
        $r = str_replace ('sakai.oldhost.com', 'sakai.client.com', $d);
        $newb64 = base64_encode($r);
      }
    }
  }

  if ($newb64) {
    $newxml = str_replace ($oldb64, $newb64, $row->XML);
    $stmt = $conn->prepare("UPDATE ASSIGNMENT_CONTENT set XML=? WHERE CONTENT_ID=?");
    $stmt->bind_param('ss', $newxml, $row->CONTENT_ID);
    if ($result = $stmt->execute()){
      echo "success: " . $row->CONTENT_ID . "\n";
      $stmt->free_result();
    }
    else {
      echo "error: " . $row->CONTENT_ID . "\n";
    }
  }
}
