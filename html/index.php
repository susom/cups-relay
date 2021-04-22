<?php

date_default_timezone_set('America/Los_Angeles');
error_reporting(E_ALL & ~E_NOTICE);
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
$logFileMax = $ini['log-file-max'];
$testMode = $ini['test-mode'];

Plugin::log("Starting");

function pruneLogs($file, $lineCount) {
    // Prune plugin and php_error log files
    $output = "";
    $retval = "";
    if (!file_exists($file)) return;

    exec( "tail -$lineCount $file > $file.temp", $output, $retval);
    exec("rm $file");
    rename("$file.temp",$file);
    // if ($output != 1) error_log("pruneLog failure: " . print_r($output,true));
}



try {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // We have an incoming request
        if (!empty($_POST)) {
            $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);

            // PRINT A SINGLE FIELD
            if ($action == "print_file_field") {
                $record_id = filter_var(@$_POST['record_id'], FILTER_SANITIZE_STRING);
                $event_name = filter_var(@$_POST['event_name'], FILTER_SANITIZE_STRING);
                $field_name = filter_var(@$_POST['field_name'], FILTER_SANITIZE_STRING);

                if (empty($record_id) || empty($event_name) || empty($field_name)) {
                    throw new Exception("Missing required input(s) - see logs");
                    Plugin::log("Invalid inputs for $action", $_POST);
                }

                $project = new RedCapProject($apiUrl, $apiToken);
                // $projectInfo = $project->exportProjectInfo();
                $file     = "/tmp/" . $record_id . "_" . $event_name . "_" . $field_name . ".pdf";
                $contents = $project->exportFile($record_id,$field_name,$event_name);

                Plugin::log("Obtained $file with size " . filesize($file));
                file_put_contents($file, $contents);

                $output=null;
                $retval=null;
                //$options = "-o number-up=2 -o sides=two-sided-long-edge ";
                $options = "-o sides=two-sided-long-edge ";
                $cmd = 'lp -d ' . $printerName . ' ' . $options . $file;
                if ($testMode) {
                    Plugin::log("TESTMODE: $file would have been printed to $printerName");
                } else {
                    exec( $cmd, $output, $retval);
                    Plugin::log($cmd, $output, $retval);
                    Plugin::log($file . " printed to $printerName");
                }
                // $result = ['success' => "$instrument printed"];
                unlink($file);
                $result = ["success" => "$file printed on $printerName"];
            }
            // DO THE PREVIOUS PRINT FORMS BEHAVIOR
            else {

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
                        //$options = "-o number-up=2 -o sides=two-sided-long-edge ";
                        $options = "-o sides=two-sided-long-edge ";
                        $cmd = 'lp -d ' . $printerName . ' ' . $options . $file;
                        if ($testMode) {
                            Plugin::log("TESTMODE: $file would have been printed to $printerName");
                        } else {
                            exec( $cmd, $output, $retval);
                            Plugin::log($cmd, $output, $retval);
                            Plugin::log($file . " printed to $printerName");
                        }
                        // $result = ['success' => "$instrument printed"];
                        unlink($file);
                    }
                }
                $result = ["success" => implode(", ", $instruments) . " printed on $printerName"];

            }

        } else {
            throw new Exception("Post is empty");
        }
    } else {
        // Not a post request
        if(isset($_GET['showLogs'])) {
            $result = [
                'logs'   => file_exists("/tmp/plugin.log")     ? file_get_contents("/tmp/plugin.log") : '',
                'errors' => file_exists("/tmp/php_errors.log") ? file_get_contents("/tmp/php_errors.log") : ''
            ];
            Plugin::log("Logs Viewed");
        } else {
            throw new Exception("Request was not a POST");
        }
    }
} catch (\Exception $e) {
    Plugin::log("EXCEPTION", $e);
    $result = [ "error" => $e->getMessage() ];
}

// Prune plugin and php_error log files
pruneLogs("/tmp/plugin.log", $logFileMax);
pruneLogs("/tmp/php_errors.log", $logFileMax);


if (!headers_sent()) header('Content-Type: application/json');

echo json_encode($result);
