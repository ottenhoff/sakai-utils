<?php
$dbtype = 'mysql';
$host = '127.0.0.1';
$port = 13682;
$db   = 'database';
$user = 'user';
$pass = 'password';
$charset = 'utf8';

$dsn = "$dbtype:host=$host;dbname=$db;port=$port;charset=utf8";
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$pdo = new PDO($dsn, $user, $pass, $opt);

$content_stmt = $pdo->prepare("UPDATE ASSIGNMENT_CONTENT SET XML=:xml WHERE CONTENT_ID=:content_id");
$submission_stmt = $pdo->prepare("UPDATE ASSIGNMENT_SUBMISSION SET XML=:xml WHERE SUBMISSION_ID=:submission_id");

$stmt = $pdo->query('SELECT SITE_ID, TITLE, count(*) AS cnt FROM SAKAI_SITE ss JOIN ASSIGNMENT_ASSIGNMENT aa ON ss.SITE_ID=aa.CONTEXT WHERE ss.type="course" AND ss.SITE_ID="47eefffb-80ec-450a-8a4e-c252f83635c8" GROUP BY SITE_ID, TITLE ORDER BY ss.SITE_ID ASC LIMIT 100');
while ($row = $stmt->fetch()) {
  print "Processing site $row->SITE_ID ($row->TITLE): $row->cnt assignments. \n";

  $stmt2 = $pdo->query('SELECT * FROM ASSIGNMENT_ASSIGNMENT WHERE CONTEXT="' . $row->SITE_ID . '" ORDER BY ASSIGNMENT_ID');
  while ($row2 = $stmt2->fetch()) {
    $xml = simplexml_load_string($row2->XML);

    if (!$xml) {
      print "Error parsing XML $row->SITE_ID ($row->TITLE) \n";
      var_dump($row);
      exit(1);
    }

    // Extract the contentid from the assignment XML chunk
    $xpath = $xml->xpath('/assignment/@assignmentcontent')[0];
    $contentstring = (string)$xpath->assignmentcontent;
    $contentpieces = explode('/', $contentstring);
    $contentid = array_pop($contentpieces);
  
    $stmt3 = $pdo->query('SELECT * FROM ASSIGNMENT_CONTENT WHERE CONTENT_ID="' . $contentid . '"');
    while ($row3 = $stmt3->fetch()) {
      $xml = simplexml_load_string($row3->XML);
      if (!$xml) {
        print "Error parsing XML $row->SITE_ID ($row->TITLE) contentid: $contentid\n";
        exit(1);
      }

      // Loop through all attributes in the content XML blob looking for the scale factors
      $scaled_maxgradepoint = $scaled_factor = null;
      foreach ($xml->attributes() AS $k => $v) {
        if (strpos($k, 'scaled_maxgradepoint') !== FALSE) {
          $scaled_maxgradepoint = (string)$v[0];
        }
        if (strpos($k, 'scaled_factor') !== FALSE) {
          $scaled_factor = (string)$v[0];
        }
      }

      // This is a non-graded assignment
      if (empty($scaled_maxgradepoint)) {
        continue;
      }
      // This is an assignment without a scale factor that needs updating
      else if (empty($scaled_factor) || $scaled_factor == '10') {
        $xml['scaled_maxgradepoint'] = (int)$scaled_maxgradepoint * 10;
        $xml['scaled_factor'] = 100;
        $content_stmt->bindParam(':xml', $xml->asXML());
        $content_stmt->bindPAram(':content_id', $row3->CONTENT_ID);
        $success = $content_stmt->execute();
        if ($success) {
          print "Updated ASSIGNMENT_CONTENT $row3->CONTENT_ID with new XML. \n";
        }
        else {
          print "Error updating ASSIGNMENT_CONTENT $row3->CONTENT_ID \n";
        }

        // Now loop through all student submissions
        $stmt4 = $pdo->query('SELECT * FROM ASSIGNMENT_SUBMISSION WHERE CONTEXT="' . $row2->ASSIGNMENT_ID . '"');
        while ($row4 = $stmt4->fetch()) {
          $submission_xml = simplexml_load_string($row4->XML);
          if (!$submission_xml) {
            print "Error parsing XML $row->SITE_ID ($row->TITLE) submission_id: $row4->ID \n";
            exit(1);
          }

          $studentscaledgrade = $studentscalefactor = null;
          foreach ($submission_xml->attributes() AS $k => $v) {
            if (stripos($k, 'scaled_grade') !== FALSE) {
              $studentscaledgrade = (string)$v[0];
            }
            else if (stripos($k, 'scaled_factor') !== FALSE) {
              $studentscalefactor = (string)$v[0];
            }
          }

          if (empty($studentscaledgrade)) {
            continue;
          }
          else if ($studentscalefactor == null) {
            $submission_xml['scaled_grade'] = (int)$studentscaledgrade * 10;
            // $submission_xml->addAttribute('scaled_factor', 100);
            $submission_stmt->bindParam(':xml', $submission_xml->asXML());
            $submission_stmt->bindParam(':submission_id', $row4->SUBMISSION_ID);
            $success = $submission_stmt->execute();
            if ($success) {
              print "Updated ASSIGNMENT_SUBMISSION $row4->SUBMISSION_ID with new XML. \n";
            }
            else {
              print "Error updating ASSIGNMENT_SUBMISSION $row4->SUBMISSION_ID \n";
            }
          }
        }
      }
    }
  }
  sleep(1);
}

// Try to free up statements
$content_stmt->closeCursor();
$submission_stmt->closeCursor();
