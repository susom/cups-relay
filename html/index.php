<?php

error_reporting(E_ALL);
// ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log','/tmp/php_errors.log');

// phpinfo();

require_once("vendor/autoload.php");
require_once("Plugin.php");


use IU\PHPCap\RedCapProject;

// GET CONFIG
$ini = parse_ini_file('/var/www/config.ini');
$apiUrl = $ini['api-url'];
$apiToken = $ini['api-token'];
$printerName = $ini['printer-name'];

try {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // We have an incoming request
        if (!empty($_POST)) {
            // Post contains body
            $record_id = filter_var(@$_POST['record_id'], FILTER_SANITIZE_STRING);
            $event_name = filter_var(@$_POST['event_name'], FILTER_SANITIZE_STRING);
            $instruments = @$_POST['instruments'];
            $compact_display = (bool) filter_var(@$_POST['compact_display'], FILTER_SANITIZE_NUMBER_INT);

            if (empty($record_id) || empty($event_name) || empty($instruments)) {
                throw new Exception("Missing required input(s) - see logs");
                Plugin::log("Invalid inputs", $_POST);
            }

            $project = new RedCapProject($apiUrl, $apiToken);
            $projectInfo = $project->exportProjectInfo();
            //print "<pre>" . print_r($projectInfo, true) . "</pre>";

            foreach ($instruments as $instrument) {
                $instrument = filter_var($instrument, FILTER_SANITIZE_STRING);
                Plugin::log($record_id, $event_name, $instrument);
                $file = "/tmp/" . $record_id . "_" . $event_name . "_" . $instrument . ".pdf";
                $q = $project->exportPdfFileOfInstruments($file, $record_id, $event_name, $instrument, null, $compact_display);
                // See if result is error
                $q = json_decode($q,true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    // We got json which means an error
                    $errorMsg = empty($q['error']) ? json_encode($q) : $q['error'];
                    throw new Exception( $errorMsg );
                } else {
                    // Result was not json meaning likely a PDF - so let's print the file
                    $output=null;
                    $retval=null;
                    $cmd = 'lp -d ' . $printerName . " " . $file;
                    // exec( $cmd, $output, $retval);
                    Plugin::log($cmd, $output, $retval);
                    // $result = ['success' => "$instrument printed"];
                    Plugin::log($file . " printed to $printerName");
                    unlink($file);
                }
            }
            $result = ["success" => count($instruments) . " printed for $record_id on $printerName"];
        } else {
            throw new Exception("Post is empty");
        }
    } else {
        // Not a post request
        throw new Exception("Request was not a POST");
    }
} catch (\Exception $e) {
    Plugin::log("EXCEPTION", $e);
    $result = [ "error" => $e->getMessage() ];
}

if (!headers_sent()) header('Content-Type: application/json');

echo json_encode($result);
