<?php
require 'config.php';

date_default_timezone_set('Africa/Nairobi');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$today =  date("Y-m-d H:i:s");
$mintoday =  date("Y-m-d");


function sendJsonResponse($statusCode, $resultcode = false, $message = null, $data = null)
{

    $resultcode ??= false;
    http_response_code($statusCode);

    if (!$message) {
        switch ($statusCode) {
            case 200:
                $message = 'OK';
                $resultcode = true;
                break;
            case 201:
                $message = 'Action was executed successfully';
                break;
            case 204:
                $message = 'No Content';
                break;
            case 400:
                $message = 'Bad Request: [' . $_SERVER['REQUEST_METHOD'] . '] is Not Allowed';
                break;
            case 401:
                $message = 'Unauthorized';
                break;
            case 403:
                $message = 'Forbidden';
                break;
            case 404:
                // $message = '404 Not Found';
                $message = null;
                break;
            case 422:
                $message = 'Unprocessable Entity Missing Parameters.';
                break;
            case 0:
                $message = 'Timed out Connection: Try again Later';
                notify(1, "Timed out Connection: Try again Later.", 0, 1);
                break;
            default:
                $message = 'Timed out Connection: Try again Later';
        }
    }

    $response = ['status' => $statusCode, 'resultcode' => $resultcode, 'msg' => $message];

    if (strstate($data)) {
        $response['data'] = $data;
    }

    if (isset($_SESSION['notify'])) {
        $response['info'] = $_SESSION['notify'];
    }

    unset($_SESSION);
    header('Content-Type: application/json');
    echo json_encode($response);

    exit;
}

function greet()
{
    $hour = date('H');
    if ($hour >= 0 && $hour < 12) {
        $greeting = 'Good morning, ';
    } elseif ($hour >= 12 && $hour < 18) {
        $greeting = 'Good afternoon, ';
    } elseif ($hour >= 18 && $hour < 22) {
        $greeting = 'Good evening, ';
    } else {
        $greeting = 'Have a peaceful night, ';
    }
    return $greeting;
}

function fne($fn)
{
    if (function_exists($fn)) {
        $fn();
    }
}
function jDecode($expect = null)
{

    $json = file_get_contents("php://input");
    $inputs = json_decode($json, true);

    if ($inputs === null && json_last_error() !== JSON_ERROR_NONE) {
        return sendJsonResponse(422, false, "Bad Request: Invalid JSON format");
    }

    if ($expect) {
        foreach ($expect as $key) {
            // Check if the required key is missing or empty
            if (!array_key_exists($key, $inputs) || !strstate($inputs[$key])) {
                return sendJsonResponse(422, false, "Missing Parameters", [
                    "Your_Request" => $inputs,
                    "Required" => $expect
                ]);
            }
        }
    }

    return $inputs;
}

function strstate($value)
{
    if (is_array($value)) {
        return !empty($value); // Check if array is not empty
    }
    return !empty($value) && $value !== false && $value !== null && $value !== '';
}

function msginf($id)
{
    $res  = [];
    if ($id == 0) {
        $res['tra'] = "Awaiting";
        $res['up'] = "Upcoming";
        $res['reg'] = "Undefined";
        $res['color'] = "orange";
        $res['inf'] = "Info";
    } elseif ($id == 1) {
        $res['tra'] = "Declined";
        $res['up'] = "Unsettled";
        $res['reg'] = "Inactive";
        $res['color'] = "#e02007";
        $res['inf'] = "Error";
    } elseif ($id == 2) {
        $res['tra'] = "Confirmed";
        $res['up'] = "Accredit";
        $res['reg'] = "Active";
        $res['color'] = "#24db14";
        $res['inf'] = "Success";
    } else {
        $res['tra'] = "Undefined";
        $res['up'] = "Unsettled";
        $res['reg'] = "Undefined";
        $res['color'] = "#ff790c";
        $res['inf'] = "Info";
    }
    return $res;
}

function notify($state, $msg, $errno, $show)
{
    global $dev;
    global $admin;

    $state ??= '0'; //0=info//1=error//2=success
    $errno ??= null; //error meassage
    $show ??= 3; //1=user to see//2=admin to see//3=dev to see
    $justnow = date('F j, H:i:s A');

    if (!isset($_SESSION['notify'])) {
        $_SESSION['notify'] = [];
    }
    $notification = [
        "state" => $state,
        "color" => msginf($state)['color'],
        "msg" => $msg,
        "errno" => $errno,
        "time" => $justnow,
    ];

    if ($show == 1) {

        $_SESSION['notify'][] = $notification;
    } elseif ($show == 2) {
        sendmail($admin['name'], $admin['email'], $admin['name'] . " " . $msg, "#$errno");
    } else {
        sendmail($dev['name'], $dev['email'], $msg, "Error-Code->$errno");
    }

    return true;
}

function mytrim($string = null)
{
    $string = $string ? trim($string) : "";

    $string =  str_replace(["/", "#", ",", "!", "$", "?", "|", "'", "-", "_", "~", "*", "(", ")", '`', " "], "", $string);
    if (!strstate($string)) {
        return false;
    }

    return $string;
}

function ucap($str)
{
    $capitalizedString = ucfirst(mytrim($str));
    return $capitalizedString;
}

function verifyEmail($email)
{
    // Check if the email is not empty and is a valid email address
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Perform DNS check to see if the domain has a valid MX or A record
        $domain = substr(strrchr($email, "@"), 1);
        if (checkdnsrr($domain, "MX") || checkdnsrr($domain, "A")) {
            return true;
        } else {
            return false; // Invalid domain
        }
    } else {
        return false; // Invalid email format
    }
}

function emailtemp($msg, $uname, $sub)
{
    global $admin;

    $domain = $admin['domain'];
    $company = $admin['company'];

    $msg = "<!DOCTYPE html>
<html lang='en'>
            position: absolute;
            bottom: 0;
            left: 25%;
            width: 50%;
            height: 3px;
            background: white;
        }
        
            
            <div style='text-align: center;'>
                <a href='$domain' class='cta-button'>Account Login</a>
            </div>
            
            <div class='email-footer'>
                With sincere appreciation,<br>
                Fueling Your Success,<br>
                <strong>Driven by: $company Ltd</strong>
            </div>
        </div>
    </div>
</body>
</html>";

    return $msg;
}

function sendPostRequest($url, $data, $authorizationToken = null, $apitoken = null)
{
    // Initialize cURL session
    $ch = curl_init($url);

    // Convert data array to JSON
    $payload = json_encode($data);

    // Base headers
    $headers = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ];

    // Add authorization header if token is provided
    if ($authorizationToken) {
        $headers[] = 'Authorization: Bearer ' . $authorizationToken;
    }

    // Add API token header if token is provided
    if ($apitoken) {
        $headers[] = 'Api-Secret: ' . $apitoken;
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    try {
        // Execute the POST request
        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        // Return the response
        return json_decode($response, true);
    } catch (Exception $e) {
        // Handle the exception (log the error, rethrow, etc.)
        error_log($e->getMessage());
        return false;  // Return false if there is an issue

    } finally {
        // Always close the cURL session
        curl_close($ch);
    }
}





function generateICS($eventDetails)
{
    $eventName = $eventDetails['name'];
    $eventDescription = $eventDetails['description'];
    $eventStart = $eventDetails['start']; // Format: YYYYMMDDTHHMMSSZ
    $eventEnd = $eventDetails['end']; // Format: YYYYMMDDTHHMMSSZ
    $eventLocation = $eventDetails['location'];

    $icsContent = "BEGIN:VCALENDAR
    VERSION:2.0
    BEGIN:VEVENT
    SUMMARY:$eventName
    DESCRIPTION:$eventDescription
    DTSTART:$eventStart
    DTEND:$eventEnd
    LOCATION:$eventLocation
    END:VEVENT
    END:VCALENDAR";

    return $icsContent;
}

// Example event details
$eventDetails = [
    'name' => 'Meeting with Client',
    'description' => 'Discuss project requirements and timelines.',
    'start' => '20220317T090000Z', // Example: March 17, 2022, 09:00 AM (UTC)
    'end' => '20220317T100000Z',   // Example: March 17, 2022, 10:00 AM (UTC)
    'location' => '123 Main St, City'
];

// $icsContent = generateICS($eventDetails);

function getstkpushtoken()
{
    global $admin;

    $array = $admin['stktoken'];

    shuffle($array);
    $array = reset($array);

    return $array;
}

function generatetoken($length = 16, $cap = false)
{
    $length = strstate($length) ? $length : 16;
    $token = bin2hex(random_bytes($length));

    if ($cap) {
        $token = strtoupper($token);
    }
    return $token;
}


function inserts($tb, $tbwhat, $tbvalues)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        // notify(1,"error requested Table =>inserts",501,3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;

    $values = count($tbvalues) - 1;
    $qvalues = implode(', ', array_fill(0, $values, '?'));

    $qry = "INSERT INTO $tb ($tbwhat) VALUES ($qvalues)";
    $stmt = $conn->prepare($qry);

    // Extract data types and values separately
    $dataTypes = str_split(array_shift($tbvalues));
    $stmt->bind_param(implode('', $dataTypes), ...$tbvalues);

    $array['res'] = $stmt->execute();

    // Check for errors
    if (!$array['res']) {
        $array['qry'] = $stmt->error;
        // notify(1,"Error Inserting  Querring " . $array['qry'],400,3);
        //sends me a amil
    }

    // Close the statement
    $stmt->close();

    return $array;
}

function selects($all, $tb, $tbwhere, $datatype =  2)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        notify(1, 'error requested  fn=>selects', 502, 3);
        return sendJsonResponse(500, "ss");
    }
    $all = !empty($all) ? $all . " " : "*";
    $datatype = !empty($datatype) ? $datatype : "2";

    $array = [];
    $array['res'] = false;
    $array['rows'] = 0;
    $array['qry'] = [];

    if (empty($tbwhere) || $tbwhere == null) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere ";
    }

    $selects = "SELECT $all FROM $tb $tbwhere";
    $results = mysqli_query($conn,  $selects);
    if ($results) {
        $num = mysqli_num_rows($results);
        if ($num > 0) {
            if ($datatype == 1) {
                while ($grab = mysqli_fetch_array($results)) {
                    $qry[] = $grab;
                }
            } else {
                while ($grab = mysqli_fetch_row($results)) {
                    $qry[] = $grab;
                }
            }
            $array['res'] = true;
            $array['qry'] = $qry;
            $array['rows'] = $num;
        }
    } else {
        $array['qry']['data'] = mysqli_error($conn);
        notify(1, "Error Selecting Querring " . $array['qry']['data'], 400, 3);
    }
    return $array;
}
function comboselects($query, $datatype =  2)
{
    global $conn;

    $array = [];
    $array['res'] = false;
    $array['rows'] = 0;
    $array['qry'] = [];

    if (empty($query)) {
        return $array;
    }
    $results = mysqli_query($conn,  $query);
    if ($results) {
        $num = mysqli_num_rows($results);
        if ($num > 0) {
            if ($datatype == 1) {
                while ($grab = mysqli_fetch_array($results)) {
                    $qry[] = $grab;
                }
            } else {
                while ($grab = mysqli_fetch_row($results)) {
                    $qry[] = $grab;
                }
            }
            $array['res'] = true;
            $array['qry'] = $qry;
            $array['rows'] = $num;
        }
    } else {
        $array['qry']['data'] = mysqli_error($conn);
        notify(1, "Error Combo Select Querring " . $array['qry']['data'], 400, 3);
    }
    return $array;
}

function table($abrv)
{
    $array = [];
    switch ($abrv) {
        case "rec":
            $array['tb'] = "records";
            $array['id'] = "rid";
            break;
    }

    return $array;
}
function check($type, $tb, $value)
{

    $array = [];

    $array["res"] = false;
    $array["qry"] = null;

    $run = selects($type, $tb, "$type = '$value'");

    if ($run['res'] === true) {
        $array["res"] = true;
        $array["qry"] = $run['qry'][0];
    }
    return $array;
}
function updates($tb, $tbset, $tbwhere)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        notify(1, "error requested fn=>updates", 503, 3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;
    $array['qry'] = null;

    if (empty($tbwhere) || !isset($tbwhere)) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere";
    }

    $updates = "UPDATE $tb SET $tbset $tbwhere";
    $results = mysqli_query($conn,  $updates);
    if ($results === true) {
        $array['res'] = true;
    } else {
        $array['qry'] = $results;
        notify(1, "Error Updating Querring " . $array['qry'], 400, 3);
    }
    return $array;
}

function deletes($tb, $tbwhere)
{
    global $conn;

    $confirmtb = table($tb);

    $tb = isset($confirmtb['tb']) ? $confirmtb['tb'] : [];
    if (!$tb) {
        notify(1, "error requested fn=>deletes", 504, 3);
        return sendJsonResponse(500);
    }
    $array = [];
    $array['res'] = false;

    if (empty($tbwhere) || !isset($tbwhere)) {
        $tbwhere = "";
    } else {
        $tbwhere = " WHERE $tbwhere";
    }

    $deletes = "DELETE FROM $tb $tbwhere ";
    $results = mysqli_query($conn,  $deletes);
    if ($results) {
        $array['res'] = true;
    } else {
        $array['qry'] = mysqli_error($conn);
        notify(1, "Error Deleting Querring " . $array['qry'], 400, 3);
    }
    return $array;
}

function insertstrans($tid, $tuid, $tuname, $tuphone, $ttype, $tcat, $payment_type, $ref_payment, $tamount, $tstatus, $tprebalance, $tbalance, $tpredeposit, $tdeposit, $tdate, $tduedate, $trefuname, $trefuid, $tstate, $ttype_id = null)
{
    $query = [$tid, $tuid, $tuname, $tuphone, $ttype, $tcat, $payment_type, $ref_payment, $tamount, $tstatus, $tprebalance, $tbalance, $tpredeposit, $tdeposit, $tdate, $tduedate, $trefuname, $trefuid, $tstate, $ttype_id];
    $merged = array_merge(['ssssssssssssssssssss'], $query);
    return inserts("tra", "tid,tuid,tuname,tuphone,ttype,tcat,payment_type,ref_payment,tamount,tstatus,tprebalance,tbalance,tpredeposit,tdeposit,tdate,tduedate,trefuname,trefuid,tstate,ttype_id", $merged);
}



function checktoken($tb, $token, $cap = false)
{
    $array = [];

    $id = table($tb)['id'];
    if (!$tb) {
        notify(1, "error requested fn=>checktoken", 505, 3);
        return sendJsonResponse(500);
    }

    $pretoken = $token;
    $token = check($id, $tb, $token);

    if ($token['res']) {
        $token = checktoken($tb, generatetoken(strlen($token['qry'][0]) + 1, $cap), $cap);
    } else {
        $token = $pretoken;
    }

    return $token;
}

function gencheck($tb, $default = 14)
{
    return checktoken($tb, generatetoken($default, true), true);
}



function versionupdate()
{
    if (sessioned()) {
        $data = $_SESSION['query']['data'];
        $uname = $data['uname'];
        $uid = $_SESSION['suid'];

        $isAdmin = $data['isadmin'];


        if (!$isAdmin) {
            notify(1, "You Are Not Authorized To Access This Feature", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }


        $current = selects("lastupdate", "sit", "sid = 'AA11'", 1)['qry'][0]['lastupdate'] ?? null;
        $addmin = date("Y-m-d H:i:s", strtotime($current . "+1 min"));
        if (updates("sit", "lastupdate = '$addmin'", "sid = 'AA11'")['res']) {
            notify(2, "Version Updated", 518, 1);
            return sendJsonResponse(200);
        }
        notify(2, "Error 500 Version Updated", 518, 1);
        return sendJsonResponse(500);
    }
}

function login()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(400);
    }
    $inputs = jDecode(['username', 'password']);

    $errors = false;

    if (!mytrim($inputs['username'])) {
        notify(1, "Username required", 506, 1);
        $errors = true;
    }

    if (!mytrim($inputs['password'])) {
        notify(1, "Password required", 508, 1);
        $errors = true;
    }

    if ($errors) {
        return sendJsonResponse(422);
    }

    $uname = Ucap(mytrim($inputs['username']));
    $password = $inputs['password'];

    $confirm = selects("*", "use", "uname = '$uname'", 1);
    if (!$confirm['res']) {
        notify(1, "Username not found", 515, 1);
        return sendJsonResponse(403);
    }
    if ($confirm['qry'][0]['active'] != 1) {
        notify(1, "Account is Suspended Please contact Your Upline", 516, 1);
        $site = selects("*", "sit", "sid = 'AA11'", 2)['qry'][0];
        $customercare = $site['customercare'] ?? null;
        return sendJsonResponse(403, false, "", $customercare);
    }

    $hashpass = $confirm['qry'][0]['upass'];
    if (password_verify($password, $hashpass)) {

        $uid = $confirm['qry'][0]['uid'];

        $today =  date("Y-m-d H:i:s");
        deletes("ses", "sexpiry <= '$today'");
        $confirmsessions = selects("*", "ses", "suid = '$uid' and sexpiry >= '$today' LIMIT 1", 1);

        if ($confirmsessions['res']) {
            $stoken = $confirmsessions['qry'][0]['stoken'];
            $msg = "Logged In Successful";
            notify(2, $msg, 519, 1);
            $_SESSION['suid'] = $uid;
            data();
            $result =
                [
                    'access_token' => $stoken,
                    "user_data" => [
                        "userdetails" => $_SESSION['query']['data'],
                        "balances" => $_SESSION['query']['conv'],
                    ]
                ];
            return sendJsonResponse(200, true, null, $result);
        } else {
            $stoken = generatetoken(102);
            $ssid = gencheck("ses");

            $thirtyMinutes = date("Y-m-d H:i:s", strtotime("+1 days"));

            $session = inserts("ses", "sid,suid,stoken,sexpiry", ['ssss', $ssid, $uid, $stoken, $thirtyMinutes]);
            if ($session) {
                $msg = "Logged In Successful";
                notify(2, $msg, 520, 1);
                $_SESSION['suid'] = $uid;
                data();
                $result =
                    [
                        "access_token" => $stoken,
                        "user_data" => [
                            "userdetails" => $_SESSION['query']['data'],
                            "balances" => $_SESSION['query']['conv'],
                        ]
                    ];

                return sendJsonResponse(200, true, null, $result);
            }
        }
    } else {
        notify(1, "Invalid Password", 517, 1);
        return sendJsonResponse(401);
    }
}


function sessioned()
{
    if (isset($_SESSION['suid']) && isset($_SESSION['query'])) {
        return true;
    }
    return sendJsonResponse(403);
}

function auths()
{
    $response = [];
    $response['status'] = true;
    // $token = mytrim(isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null);
    // $today =  date("Y-m-d H:i:s");

    // $confirmsessions = selects("*", "ses", "stoken = '$token' and sexpiry >= '$today' LIMIT 1", 1);

    // if ($confirmsessions['res']) {
    //     $_SESSION['suid'] = $confirmsessions['qry'][0]['suid'];
    //     data();
    //     if (isset($_SESSION['query'])) {
    //         $response['status'] = true;
    //         $response['cid'] = $_SESSION['query']['data']['cid'];
    //         $response['lastupdate'] = selects("lastupdate", "sit", "sid = 'AA11'", 1)['qry'][0]['lastupdate'] ?? null;
    //     }
    // }

    return $response;
}





function auth()
{
    $confirm = auths();
    if ($confirm['status']) {
        return sendJsonResponse(200, true, null, $confirm);
    } else {
        return sendJsonResponse(401, false, null, $_COOKIE);
    }
}


function freeuser()
{
    $inputs = jDecode(['username']);


    $uname = Ucap(mytrim($inputs['username']));
    $freeuser = check("uname", "use", $uname);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}


function freeemail()
{
    $inputs = jDecode(['email']);

    $email = mytrim($inputs['email']);
    $freeuser = check("uemail", "use", $email);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}

function freephone()
{
    $inputs = jDecode(['phone']);

    $phone = mytrim($inputs['phone']);
    $freeuser = check("uphone", "use", $phone);
    if ($freeuser['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(404);
    }
}

function stkpushinternal($amount, $phone)
{
    if (!sessioned()) {
        return sendJsonResponse(403);
    }

    $apitoken = getstkpushtoken();
    global $today;


    $tratoken = checktoken("tra", generatetoken(4, true), true);
    $uid = $_SESSION['suid'];

    $data = $_SESSION['query']['data'];
    $bal = $_SESSION['query']['bal'];

    $l1 = $data['l1'];
    $uplineid = $data['uplineid'];

    $uname = $data['uname'];

    $balance = $bal['balance'];
    $predeposit = $bal['deposit'];

    $prebalance = $bal['balance'];

    $phone = "0" . substr(preg_replace('/\D/', '', $phone), -9);
    $amount = mytrim($amount);

    if (empty($amount) || empty($phone)) {
        return sendJsonResponse(422);
    }

    $apiUrl = 'https://api.boogiecoin.com';
    $data = [
        'amount' => $amount,
        'phone' => $phone,
        'load_response' => true,
    ];

    $response =  sendPostRequest($apiUrl, $data, null, $apitoken);

    $rescode = $response['Resultcode'] ?? null;
    $desc = $response['Desc']  ?? null;

    if ($response['Status'] === true && $rescode === 0) {

        $upbal = updates("bal", "deposit = deposit+'$amount'", "buid='$uid'");


        $deposit = $bal['deposit'] + $amount;
        notify(2, "payment activation for loan", 200, 3);
        insertstrans($tratoken, $uid, $uname, $phone, "Account Deposit", "7", 'KDOE', `NULL`, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $today, $today, $l1, $uplineid, 2);
        return true;
    }
    notify(1, "stkpush failed: $desc", "stk->$rescode", 1);
    sendJsonResponse(403);
}
function stkpush()
{
    $inputs = jDecode(['amount', 'phone']);

    if (sessioned()) {

        $amount = mytrim($inputs['amount']);
        $phone = "0" . substr(preg_replace('/\D/', '', $inputs['phone']), -9);

        $array = [];
        $apitoken = getstkpushtoken();
        global $today;
        global $admin;


        $uid = $_SESSION['suid'];
        $apiUrl = 'https://api.boogiecoin.com';
        $data = [
            'amount' => $amount,
            'phone' => $phone,
            'load_response' => true,
        ];

        $response = sendPostRequest($apiUrl, $data, null, $apitoken);

        $rescode = $response['Resultcode'] ?? null;
        $desc = $response['Desc']  ?? "null";

        $tratoken = checktoken("tra", generatetoken(4, true), true);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $l1 = $data['l1'];
        $uplineid = $data['uplineid'];

        $uname = $data['uname'];

        $prebalance = $bal['balance'];
        $predeposit = $bal['deposit'];


        if ($response['Status'] === true && $rescode === 0) {

            $upbal = updates("bal", "deposit = deposit+'$amount'", "buid='$uid'");

            if ($upbal['res'] == true) {
                data();

                $newdata = $_SESSION['query']['data'];
                $newbal = $_SESSION['query']['bal'];

                $balance = $newbal['balance'];
                $deposit = $newbal['deposit'];

                insertstrans($tratoken, $uid, $uname, $phone, "Account Deposit", "7", 'KDOE', `NULL`, $amount, '2', $prebalance, $balance, $predeposit, $deposit, $today, $today, $l1, $uplineid, 2);
            }

            $curdate = date("Y-m-d");
            $totaldip = selects("SUM(tamount)", "tra", "tcat = '7' AND tstatus = '2' AND tdate like '%$curdate%'", 1)['qry'][0][0] ?? "1";
            $totalwith = selects("SUM(tamount)", "tra", "tcat = '3' AND tdate like '%$curdate%'", 1)['qry'][0][0] ?? "1";
            $amount = $amount . " KES";
            notify(2, $desc, "$rescode", 1);
            $msg = " Confirmed New-Deposit;
        <ul>
        <li>Name => $uname</li>
        <li>Amount => $amount</li>
        <li>Phone => $phone</li>
        <li>Total Deposit => <strong>$totaldip</strong></li>
        <li>Total Withdrawal =>  <strong>$totalwith</strong></li>
        </ul>
        You'll Be Notified On the Next Transaction, Deposit Approved Worth $amount";
            $subject = "New Deposit Approved";

            sendmail($admin['name'], $admin['email'], $msg, $subject);
            sendmail("Boogie", "primemarkboogie@gmail.com", $msg, $subject);

            $array['desc'] = $desc;
            $array['res'] = true;
            unset($array['qry']);

            return sendJsonResponse(200, true, null, $array);
        } else {
            $instra =   insertstrans($tratoken, $uid, $uname, $phone, "Account Deposit", "7", 'KDOE', `NULL`, $amount, '1', $prebalance, $prebalance, $predeposit, $predeposit, $today, $today, $l1, $uplineid, 2);

            notify(0, $desc, "stk->$rescode", 1);

            $array['qry'] = $desc;
            $array['code'] = $rescode;

            return sendJsonResponse(403, true, null, $array);
        }
    }
}

function data()
{
    if (isset($_SESSION['suid'])) {

        $uid = $_SESSION['suid'];

        $dataq = "SELECT u.*, pc.*, c.cid AS country_id, pm.*, p.uid AS uplineid, p.isagent AS uplineisagent, p.isadmin AS uplineisadmin, ll.uid AS l2id, 
        lll.uid AS l3id, u.active AS useractive, b.*, c.*, e.*, e.active AS feeactive, u.l1 AS upline FROM users u 
    INNER JOIN balances b 
    ON u.uid = b.buid 
    LEFT JOIN users p 
    ON u.l1 = p.uname 
    LEFT JOIN users ll 
    ON u.l2 = ll.uname 
    LEFT JOIN users lll 
    ON u.l3 = lll.uname 
    INNER JOIN countrys c 
    ON u.default_currency = c.cid 
    LEFT JOIN affiliatefee e
    ON u.default_currency = e.cid AND e.active = true
    LEFT JOIN payment_method pm
    ON u.default_currency = pm.cid AND pm.pstatus = true
    LEFT JOIN payment_procedure pb
    ON pb.pid = pm.pid 
    LEFT JOIN package pc
    ON pc.pid = u.upackage AND pc.pcategory = 'Packages' 
    WHERE u.uid = '$uid' AND u.active = true ORDER BY pm.gateway";
        $dataquery = comboselects($dataq, 1);

        if ($dataquery['res']) {
            $dataquery = $dataquery['qry'][0];

            $totalq = "SELECT SUM(tamount) FROM transactions WHERE tuid = '$uid' AND tcat = 1 AND tstatus = 2";
            $totalquery = comboselects($totalq, 1);

            $totala = "SELECT SUM(tamount) FROM transactions WHERE tuid = '$uid' AND tcat = 3 AND tstatus = 2";
            $totalacquery = comboselects($totala, 1);


            $site = selects("*", "sit", "sid = 'AA11'", 1)['qry'][0];

            $todayproduct = $site['dailyproduct'] ?? null;
            $customercare = $site['customercare'] ?? null;
            $lastupdate = $site['lastupdate'] ?? null;

            $rendomname = ['Kioko', 'Mulamwah', 'Jatelo', 'Amberray'];

            $randomname = $rendomname[array_rand($rendomname)];


            $userdata = [
                'uname' => $dataquery['uname'],
                'email' => $dataquery['uemail'],
                'phone' => $dataquery['uphone'],
                'status' => $dataquery['ustatus'],
                'upline' => $dataquery['upline'] == 'SYSTEMID' ||  $dataquery['upline'] == 'AliciaKanini' ? "None" : $dataquery['upline'],
                'uplineid' => $dataquery['uplineid'],
                'l1' => $dataquery['l1'],
                'l2' => $dataquery['l2'],
                'l2id' => $dataquery['l2id'],
                'l3' => $dataquery['l3'],
                'l3id' => $dataquery['l3id'],
                'uplineisagent' => $dataquery['uplineisagent'] == 1 ? true : false,
                'uplineisadmin' => $dataquery['uplineisadmin'] == 1 ? true : false,
                'active' => floatval($dataquery['useractive']),
                'country' => $dataquery['cname'],
                'cid' => $dataquery['country_id'],
                'upackage' => $dataquery['upackage'],
                'packageprice' => $dataquery['pprice'],
                'package_name' => $dataquery['upackage']  ? $dataquery['pname'] : false,
                'package_image' => $dataquery['upackage'] ? $dataquery['pimage'] : false,
                'abrv' => $dataquery['cuabrv'],
                'dial' => $dataquery['ccall'],
                'rate' => $dataquery['crate'],
                'nid' => $dataquery['nid'],
                'ccurrency' => $dataquery['ccurrency'],
                'join' => date("d-M-y H:i:s A", strtotime($dataquery['ujoin'])),
                'accactive' => $dataquery['accactive'],
                'daily_product' => $todayproduct ?? '200.png',
                'payment_id' => $dataquery['pid'],
                'payment_name' => $dataquery['gateway'] ?? 'Mpesa',
                'isagent' => $dataquery['isagent'] == 1 ? true : false,
                'isadmin' => $dataquery['isadmin'] == 1 ? true : false,
                'manualpayment' => $dataquery['ptype'] == 1 ?  false : true,
                'customercare' => $customercare,
                'lastupdate' => $lastupdate,
            ];

            $userbal = [
                'profit' => floatval($dataquery['profit']),
                'whatsapp' => floatval($dataquery['whatsapp']),
                'balance' => floatval($dataquery['balance']),
                'deposit' => floatval($dataquery['deposit']),
                'youtube' => floatval($dataquery['youtube']),
                'tiktok' => floatval($dataquery['tiktok']),
                'academic' => floatval($dataquery['academic']),
                'welcome' => floatval($dataquery['welcome']),
                'spin' => floatval($dataquery['spin']),
                'cashback' => floatval($dataquery['cashback']),
                'totalwithdrawal' => $totalquery['qry'][0][0] ?? 0,
                'totalagentwith' => $totalacquery['qry'][0][0] ?? 0,
                'nowithdrawal' => count($totalquery['qry'][0]),
            ];

            $conv = [
                'profit' => conv($dataquery['crate'], $dataquery['profit'], true, true),
                'whatsapp' => conv($dataquery['crate'], $dataquery['whatsapp'], true, true),
                'invested' => conv($dataquery['crate'], $dataquery['invested'], true, true),
                'balance' => conv($dataquery['crate'], $dataquery['balance'], true, true),
                'deposit' => conv($dataquery['crate'], $dataquery['deposit']),
                'youtube' => conv($dataquery['crate'], $dataquery['youtube'], true, true),
                // 'tiktok' => conv($dataquery['crate'], $dataquery['tiktok'], true, true),
                // 'academic' => conv($dataquery['crate'], $dataquery['academic'], true, true),
                // 'welcome' => conv($dataquery['crate'], $dataquery['welcome'], true, true),
                'remotask' => conv($dataquery['crate'], 1000, true, true),
                'min_invest' => conv($dataquery['crate'], 1000, true, true),
                'loans' => conv($dataquery['crate'], 1000, true, true),
                // 'spin' => conv($dataquery['crate'], $dataquery['spin'], true, true),
                'reward' => conv($dataquery['crate'], $dataquery['cashback'], true, true),
                'totalwithdrawal' => conv($dataquery['crate'], $totalquery['qry'][0][0] ?? 0, true, true),
                'totalagentwith' => conv($dataquery['crate'], $totalacquery['qry'][0][0] ?? 0, true, true),
            ];


            $_SESSION['query']['uid'] = $dataquery['uid'];
            $_SESSION['query']['upass'] = $dataquery['upass'];
            $_SESSION['query']['data'] = $userdata;
            $_SESSION['query']['bal'] = $userbal;
            $_SESSION['query']['conv'] = $conv;
        } else {
            unset($_SESSION['suid']);
            unset($_SESSION['query']);
            return sendJsonResponse(403);
        }
    } else {
        notify(1, "No Session Available Please Login", 403, 1);
        return sendJsonResponse(403);
    }
}


function userdata()
{
    if (sessioned()) {
        $senddata = [];
        $senddata['userdetails'] = $_SESSION['query']['data'];
        $senddata['balances'] = $_SESSION['query']['conv'];
        return sendJsonResponse(200, true, null, $senddata);
    }
}



function adminsite()
{
    $response = [];

    $adminq = "SELECT * FROM site";
    $adminquery = comboselects($adminq, 1);

    return $adminquery['qry'][0];
}

function currencyupdate()
{
    $inputs = jDecode(['ccurrency', 'crate']);

    $ccurrency = $inputs['ccurrency'];
    $crate = $inputs['crate'];

    $query = updates('cou', "ccurrency = '$ccurrency', crate = '$crate'", "ccurrency = '$ccurrency'");
    if ($query['res']) {
        return sendJsonResponse(200, true);
    } else {
        return sendJsonResponse(201);
    }
}




function conv($cRate, $amount, $convert = true, $comma = false)
{

    $cRate = floatval($cRate);
    $amount = floatval($amount);

    if ($convert) {
        $amount *= $cRate;
    } else {
        $amount /= $cRate;
    }
    if ($comma) {
        return max(0, number_format($amount, 2));
    } else {
        return max(0, round($amount, 2));
    }
}



function others($uid = null)
{

    $response = [];
    $response['res'] = false;
    if ($uid) {

        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, e.active AS feeactive, u.l1 AS upline FROM users u 
    INNER JOIN balances b 
    ON u.uid = b.buid 
    INNER JOIN countrys c 
    ON u.default_currency = c.cid 
    LEFT JOIN affiliatefee e
    ON u.default_currency = e.cid AND e.active = true
    WHERE (u.uid = '$uid' OR u.uname = '$uid') AND u.active = true LIMIT 1";

        $dataquery = comboselects($dataq, 1);

        if ($dataquery['res']) {
            $dataquery = $dataquery['qry'][0];
            $uid = $dataquery['uid'];

            $userdata = [
                'uname' => $dataquery['uname'],
                'email' => $dataquery['uemail'],
                'phone' => $dataquery['uphone'],
                'status' => $dataquery['ustatus'],
                'upline' => $dataquery['upline'],
                'l1' => $dataquery['l1'],
                'l2' => $dataquery['l2'],
                'l3' => $dataquery['l3'],
                'active' => floatval($dataquery['useractive']),
                'country' => $dataquery['cname'],
                'abrv' => $dataquery['cuabrv'],
                'dial' => $dataquery['ccall'],
                'rate' => floatval($dataquery['crate']),
                'ccurrency' => $dataquery['ccurrency'],
                'join' => $dataquery['ujoin'],
                'isagent' => $dataquery['isagent'] == 1 ? true : false,
                'isadmin' => $dataquery['isadmin'] == 1 ? true : false,
            ];

            $userbal = [
                'profit' => floatval($dataquery['profit']),
                'whatsapp' => floatval($dataquery['whatsapp']),
                'balance' => floatval($dataquery['balance']),
                'deposit' => floatval($dataquery['deposit']),
                'youtube' => floatval($dataquery['youtube']),
                'tiktok' => floatval($dataquery['tiktok']),
                'academic' => floatval($dataquery['academic']),
                'welcome' => floatval($dataquery['welcome']),
                'spin' => floatval($dataquery['spin']),
                'cashback' => floatval($dataquery['cashback']),

            ];
            $fee = [
                'reg' => floatval($dataquery['creg']) ?? 1293.16,
                'fl1' => floatval($dataquery['fl1']) ?? 646.58,
                'fl2' => floatval($dataquery['fl2']) ?? 323.29,
                'fl3' => floatval($dataquery['fl3']) ?? 129.32,
                'min_with' => floatval($dataquery['min_with']) ?? 12,
                'charges' => floatval($dataquery['charges']) ?? 129.32,
            ];
            $response['query']['uid'] = $dataquery['uid'];
            $response['query']['data'] = $userdata;
            $response['query']['bal'] = $userbal;
            $response['query']['fee'] = $fee;
            $response['res'] = true;
        }
    }
    return $response;
}



function updatepassword()
{

    if (sessioned()) {

        $inputs = jDecode(['curpassword', 'newpassword', 'repassword']);
        $errors = false;

        $curpassword = $inputs['curpassword'];
        $newpassword = $inputs['newpassword'];
        $repassword = $inputs['repassword'];

        if ($newpassword !== $repassword) {
            $msg = "Your New Password Din't Match the Confirmed Password";
            notify(1, $msg, 510, 1);
            $errors = true;
        }

        if ($errors) {
            return sendJsonResponse(422);
        }

        $hashpass = $_SESSION['query']['upass'];
        if (password_verify($curpassword, $hashpass)) {


            $hashpass = password_hash($newpassword, PASSWORD_DEFAULT);
            $uid = $_SESSION['suid'];

            $uppass = updates("use", "upass = '$hashpass'", "uid = '$uid'");

            if ($uppass['res']) {
                $msg = "Password Updated Successfully";
                notify(2, $msg, 201, 1);
                return sendJsonResponse(200);
            }
        } else {
            $msg = "Old Password is Incorrect";
            notify(1, $msg, 510, 1);
            return sendJsonResponse(401);
        }
    }
}

function newpasswords()
{

    $uemail = jDecode(['email'])['email'];

    if ($uemail) {

        $response = [];

        $query = selects("*", "use", "uemail = '$uemail' AND active = true", 1);
        if (!$query['res']) {
            notify(0, "Sorry, we couldn't find the email you typed. Please enter your registered email.", 404, 1);
            return sendJsonResponse(404);
        }
        if (!verifyEmail($uemail)) {
            notify(0, "Email Extension Not Found", 403, 1);
            return sendJsonResponse(403);
        }

        if (isset($query['qry'][0]['uid'])) {

            $uid = $query['qry'][0]['uid'];
            $uname = $query['qry'][0]['uname'];
            $uemail = $query['qry'][0]['uemail'];

            $response['res'] = false;

            $_SESSION['suid'] = $uid;

            $new = generatetoken(5, true);

            $hashed =  password_hash($new, PASSWORD_DEFAULT);

            if (updates("use", "upass = '$hashed'", "uid = '$uid'")['res']) {
                $response['res'] = true;
                $response['hashed'] = $new;

                $subject  = "New Password";
                $msg = "
                    Hi $uname, <br>
            Your new password has been generated. You may use it to log in and change your preferred password:

            <ul>
                <li>Username: <strong>$uname</strong></li>
                <li>Password: <strong>$new</strong></li>
            </ul>

            ";
                sendmail($uname, $uemail, $msg, $subject);
                notify(2, "We've sent you a new password to your registered email. Kindly check your inbox or Spam Folder", 200, 1);
                return sendJsonResponse(200);
            }

            return sendJsonResponse(200, true, null, $response);
        } else {
            notify(1, "Account Couldnt Be Found", 404, 1);
            return sendJsonResponse(404);
        }
    } else {
        return sendJsonResponse(403);
    }
}

function grabupline($uname)
{
    $response = [
        'l1' => 'SYSTEMID',
        'l2' => 'SYSTEMID',
        'l3' => 'SYSTEMID'
    ];

    $l1q = selects("uname, l1, l2, l3", "use", "uname = '$uname'", 1);

    if ($l1q['res']) {
        $response['l1'] = $l1q['qry'][0]['uname'];
        $response['l2'] = $l1q['qry'][0]['l1'];
        $response['l3'] = $l1q['qry'][0]['l2'];
    }

    return $response;
}


function sendmail($uname, $uemail, $msg, $subarray, $attachmentPath = null, $attachmentName = null, $calendarEvent = null)
{

    $sub = $subarray;
    $sbj = $subarray;

    // if (is_array($subarray)) {
    //     $sub = $subarray[0];
    //     $sbj = $subarray[1];
    // }
    $url = 'https://subsaver.co.ke/auth/';

    $data = [
        'uname' => $uname,
        'uemail' => $uemail,
        'msg' => $msg,
        'subject' => $sbj,
    ];

    $jsonData = json_encode($data);

    $ch = curl_init($url);
    if ($ch === false) {
        error_log('Failed to initialize cURL');
        return;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Don't wait for the response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Content-Length: " . strlen($jsonData)
    ]);

    // Set a longer timeout and enable verbose output
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout in seconds
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w')); // Output verbose info to stderr

    // Execute the request
    $result = curl_exec($ch);

    // Check for errors
    if ($result === false) {
        error_log('cURL error: ' . curl_error($ch));
    } else {
        // error_log('Request successful');
    }

    curl_close($ch);


    // global $admin;


    // $emails = getemails();

    // $thost = $emails['thost'];
    // $tuser = $emails['tuser'];
    // $tpass = $emails['tpass'];
    // $tfrom = $emails['tuser'];

    // $attachmentPath = !empty($attachmentPath) ? $attachmentPath : null;
    // $attachmentName = !empty($attachmentName) ? $attachmentName : null;

    // $mail = new PHPMailer(true);

    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // $mail->isSMTP();
    // $mail->SMTPAuth = true;
    // $mail->Host = $thost;
    // $mail->Port = 587;

    // $mail->Username = $tuser;
    // $mail->Password = $tpass;

    // $mail->setFrom($tfrom, $admin['company']);
    // $mail->addAddress($uemail, $uname);
    // // $mail->addReplyTo($admin['email'], $admin['company']);

    // $mail->Subject = $subject;
    // $mail->isHTML(true);
    // $mail->Body = emailtemp($msg);

    // // Check if an attachment is provided
    // if ($attachmentPath !== null) {
    //     $mail->addAttachment($attachmentPath, $attachmentName);
    // }

    // // Add Google Calendar event attachment
    // if ($calendarEvent !== null) {
    //     $mail->addStringAttachment($calendarEvent, 'event.ics', 'base64', 'text/calendar');
    // }

    // try {
    //     $mail->send();
    // } catch (Exception $e) {
    //     // Handle the exception (you can log it or show an error message)
    //     error_log("Mailer Error: " . $mail->ErrorInfo);
    //     // Optionally, you can set a flag or add a message for further processing
    //     echo $errorMessage = "Mailer Error: " . $mail->ErrorInfo;
    // }
    return true;
}



function transferfunds()
{

    if (sessioned()) {

        $inputs = jDecode(['acc', 'reusername', 'amount']);

        global $today;

        $acc = $inputs['acc'];
        $reusername = $inputs['reusername'];

        $amount = $inputs['amount'];

        $data = $_SESSION['query']['data'];

        $bal = $_SESSION['query']['bal'];

        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $accname = $data['uname'];
        $accemail = $data['email'];
        $l1 = $data['l1'];
        $accphone = $data['phone'];
        $ccurrency = $data['ccurrency'];
        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $isAdmin = $data['isadmin'];


        $sysamount = conv($crate, $amount, false);

        if ($acc == 1) {
            $query = "deposit";
            $querydata = $deposit;
            if (!$isAdmin) {
                notify("1", "Transfers are Only Available for Account Balance", 1, 1);
                sendJsonResponse(403);
            }
        } elseif ($acc == 2) {
            $query = "balance";
            $querydata = $balance;
        } else {
            notify(1, "Please Choose Between Your Balance or Deposit to complete these Action ", 422, 1);
            return sendJsonResponse(422);
        }

        $confirmdownline = others($reusername);



        if (!$confirmdownline['res']) {
            notify(0, "The Username Couldnt Be Located Please Confirm The Typed Username", 404, 1);
            return sendJsonResponse(404);
        }

        $newData = $confirmdownline['query']['data'];
        $newBal = $confirmdownline['query']['bal'];
        $downlineId = $confirmdownline['query']['uid'];

        $downlineUname = $newData['uname'];
        $downlineEmail = $newData['email'];
        $downlinePhone = $newData['phone'];
        $downlineRate = $newData['rate'];
        $downlineCcurrency = $newData['ccurrency'];

        $preforbalance = $newBal['balance'];
        $prefordeposit = $newBal['deposit'];

        if ($sysamount > 0) {
            if ($sysamount <= $querydata) {
                $deduct = updates("bal", "$query = $query - '$sysamount'", "buid = '$uid'");
                if ($deduct['res']) {
                    data();
                    $curbalance = $_SESSION['query']['bal']['balance'];
                    $curdeposit = $_SESSION['query']['bal']['deposit'];

                    $token = gencheck("tra", 8);
                    $insert = insertstrans(
                        $token,
                        $uid,
                        $accname,
                        $accphone,
                        "Transferred To $reusername",
                        "4",
                        'NONE',
                        `NULL`,
                        $sysamount,
                        '2',
                        $balance,
                        $curbalance,
                        $deposit,
                        $curdeposit,
                        $today,
                        $today,
                        $downlineUname,
                        $downlineId,
                        2
                    );

                    if ($insert['res']) {
                        $token = gencheck("tra", 8);

                        if ($isAdmin) {
                            $add = updates("bal", "balance = balance + '$sysamount'", "buid = '$downlineId'");
                            if (!$add['res']) {
                                notify(1, "Failed To Update Balance For $reusername Please Contact Upline $l1", 500, 1);
                                return sendJsonResponse(500);
                            }
                        } else {
                            $add = updates("bal", "deposit = deposit + '$sysamount'", "buid = '$downlineId'");
                            if (!$add['res']) {
                                notify(1, "Failed To Update Balance For $reusername Please Contact Upline $l1", 500, 1);
                                return sendJsonResponse(500);
                            }
                        }

                        $confiml3 = others($reusername);

                        $curbalance = $confiml3['query']['bal']['balance'];
                        $curdeposit = $confiml3['query']['bal']['deposit'];


                        $insert = insertstrans(
                            $token,
                            $downlineId,
                            $downlineUname,
                            $downlinePhone,
                            "Received From $accname",
                            "4",
                            'NONE',
                            `NULL`,
                            $sysamount,
                            '2',
                            $preforbalance,
                            $curbalance,
                            $prefordeposit,
                            $curdeposit,
                            $today,
                            $today,
                            $downlineUname,
                            $downlineId,
                            2
                        );


                        $userysamount = $ccurrency . " " . conv($crate, $sysamount, true, true);
                        notify(2, "Payment Request Sent Successfully to $reusername Worth $userysamount", 200, 1);
                        $userSbj = "Successful Sent Funds";
                        $userMsg  = "You have successfully transferred funds to $downlineUname amounting to $userysamount. Please confirm this transaction.";
                        sendmail($accname, $accemail, $userMsg, ['Funds Transfer', $userSbj]);


                        $sysamount = $downlineCcurrency . " " . conv($downlineRate, $sysamount, true, true);
                        $downlineSbj = "You,ve Received Funds";
                        $downlineMsg  = "Your account has been credited with funds amounting to $sysamount. Please confirm this transaction at your earliest convenience.";
                        sendmail($downlineUname, $downlineEmail, $downlineMsg, ['Deposit Balance Updated', $downlineSbj]);
                        return sendJsonResponse(200);
                    } else {
                        notify(1, "Failed To Send Payment Request to $reusername Please Contact Upline $l1", 500, 1);
                        return sendJsonResponse(500);
                    }
                }
            } else {
                notify(1, "Insufficient Funds To Perform Transfer", 403, 1);
                return sendJsonResponse(403);
            }
        } else {
            notify(1, "Failed! Kindly Enter A valid Figure", 403, 1);
            return sendJsonResponse(403);
        }
        return sendJsonResponse(200, true, null);
    }
}


function agentClaim()
{
    if (sessioned()) {
        $inputs = jDecode(['reusername']);
        global $today;

        $reusername = $inputs['reusername'];


        $confirmdownline = others($reusername);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];


        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        if ($reusername == $uname) {
            notify(1, "Action Not Available", 403, 1);
            return sendJsonResponse(403);
        }

        if (!$confirmdownline['res']) {
            notify(0, "The Username Couldnt Be Found Please Confirm The Typed Username", 404, 1);
            return sendJsonResponse(404);
        }

        $newData = $confirmdownline['query']['data'];
        $newBal = $confirmdownline['query']['bal'];
        $downlineId = $confirmdownline['query']['uid'];

        $downlineUname = $newData['uname'];

        $downlineIsAdmin = $newData['isadmin'];




        if ($deposit >= 50) {
            $deduct = updates("bal", "deposit = deposit - '50'", "buid = '$uid'");
        } else if ($balance >= 50) {
            $deduct = updates("bal", "balance = balance - '50'", "buid = '$uid'");
        } else {
            notify(1, "You Have insufficient Funds Account, Please Recharge Your Account To complete the Claim", 500, 1);
            return sendJsonResponse(500);
        }

        if (!$isAgent && !$isAdmin) {
            notify(1, "Hi $uname, this request is not available for you.", 401, 1);
            return sendJsonResponse(401);
        }

        if ($downlineIsAdmin && !$isAdmin) {
            notify(1, "Action could Not Be Completed Please Contact Your CEO", 1, 1);
            updates("use", "active = false ", "uid IN ('$uid')");
            notify(2, "Agent => $uname Has Been Suspended Because He Tried To Claim One Of The Admin Account Which is $downlineUname.", 400, 2);
            return sendJsonResponse(statusCode: 401);
        }

        if ($deduct['res']) {
            data();
            $curbalance = $_SESSION['query']['bal']['balance'];
            $curdeposit = $_SESSION['query']['bal']['deposit'];

            $token = gencheck("tra", 8);
            $insert = insertstrans(
                $token,
                $uid,
                $uname,
                $phone,
                "Agent Claim Request",
                "4",
                'NONE',
                `NULL`,
                50,
                '2',
                $balance,
                $curbalance,
                $deposit,
                $curdeposit,
                $today,
                $today,
                $downlineUname,
                $downlineId,
                2
            );

            if ($insert['res']) {
                $token = gencheck("tra", 8);
                $userAccount = updates("use", "l1 = '$uname'", "uid = '$downlineId'");
                if (!$userAccount['res']) {
                    notify(1, "Your request could not be completed. Please contact your upline for assistance", 500, 1);
                    return sendJsonResponse(500);
                }

                notify(2, "Hi $uname successfully Claimed $downlineUname's Account", 200, 1);
                return sendJsonResponse(200);
            } else {
                notify(1, "Your request could not be completed. Please contact your upline for assistance", 500, 1);
                return sendJsonResponse(500);
            }
        }
    }
}

function systemwithdrawal()
{

    if (sessioned()) {

        $inputs = jDecode(expect: ['acc', 'amount']);

        $account = $inputs['acc'];

        if ($account == 3) {
            return accountwithdrawal();
        } elseif ($account == 1) {
            return whatsappWithdrawal();
        } else {
            notify(state: 0, msg: "Insufficient balance for withdrawal.", errno: 400, show: 1);
            return sendJsonResponse(statusCode: 200);
        }
    }
}



function giveOutRandId()
{
    $response = [
        "Total Users" => 0,
        "Already Updated" => 0,
        "Completed Updates" => 0,
        "Total Errors" => 0,
    ];

    $allUser = selects("*", "use", "", 1);
    if ($allUser['res']) {
        $response['Total Users'] = $allUser['rows'];
        foreach ($allUser['qry'] as $data) {
            if (strlen($data['randid']) < 18) {

                $randId = checkrandtoken("use", generatetoken("32", false));
                $uid = $data['uid'];
                $confirm = updates("use", "randid = '$randId'", "uid = '$uid'");
                if ($confirm['res']) {
                    $response['Completed Updates']++;
                } else {
                    $response['Total Errors']++;
                }
            } else {
                $response['Already Updated']++;
            }
        }
    }
    sendJsonResponse(200, true, null, $response);
}

function checkrandtoken($tb, $token, $cap = false)
{
    $array = [];

    $id = "randid";
    if (!$tb) {
        notify(1, "error requested fn=>checktoken", 505, 3);
        return sendJsonResponse(500);
    }

    $pretoken = $token;
    $token = check($id, $tb, $token);

    if ($token['res']) {
        $token = checkrandtoken($tb, generatetoken(strlen($token['qry'][0]) + 1, $cap), $cap);
    } else {
        $token = $pretoken;
    }

    return $token;
}


function populateCountrys()
{


    $allusers = comboselects(
        "SELECT  c.*  FROM countrys c WHERE cstatus = 1 ORDER BY c.cname ASC",

        1
    );

    $response = [];

    if ($allusers['res']) {

        foreach ($allusers['qry'] as $row) {
            $question = [
                'id' => $row['cid'],
                'country' => $row['cid'] == "USDT" ? "Others" : $row['cname'],
                'dial' => $row['ccall'],
                'abrv' => $row['cid'] == "USDT" ? "" : $row['cuabrv'],
            ];
            $response[] = $question;
        }

        sendJsonResponse(200, true, null, $response);
    } else {
        sendJsonResponse(200, true, null, []);
    }
}


function updatecurrency()
{
    if (sessioned()) {
        $inputs = jDecode(expect: ['countryid']);

        $countryid = $inputs['countryid'];

        $confirmcode = check("cid", "cou", $countryid);

        $uid = $_SESSION['suid'];


        if ($confirmcode['res']) {
            $update = updates("use", "ucountryid = '$countryid', default_currency = '$countryid'", "uid = '$uid'");

            if ($update['res']) {
                notify(2, "Currency Status Changed Successfully To Active", 200, 1);
                return sendJsonResponse(200, true, "", $confirmcode);
            } else {
                notify(1, "Failed To Change Currency Status", 500, 1);
                return sendJsonResponse(500);
            }
        } else {
            notify(1, "Failed To Change Currency Status", 500, 1);
            return sendJsonResponse(500);
        }
    }
}

function populateAllCountrys()
{


    $allusers = comboselects(
        "SELECT c.*  FROM  countrys c  ORDER BY c.cname ASC",

        1
    );

    $response = [];

    if ($allusers['res']) {

        foreach ($allusers['qry'] as $row) {
            $question = [
                'id' => $row['cid'],
                'country' => $row['cname'],
                'dial' => $row['ccall'],
                'abrv' => $row['cuabrv'],
            ];
            $response[] = $question;
        }

        sendJsonResponse(200, true, null, $response);
    } else {
        sendJsonResponse(200, true, null, []);
    }
}

//  yoo

// Host github.com
//     HostName github.com
//     User git
//     IdentityFile ~/.ssh/official
