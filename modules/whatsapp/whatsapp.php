<?php

require_once "admin/whatsapp/whatsapp.php";

function register()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return sendJsonResponse(400);
    }

    $inputs = jDecode(['username', 'email', 'password', 'phone', 'upline']);

    $errors = false;

    if (!isset($inputs['username']) || !mytrim($inputs['username'])) {
        notify(1, "Username required", 506, 1);
        $errors = true;
    }

    if (!isset($inputs['email']) || !mytrim($inputs['email']) || !verifyEmail($inputs['email']) || strlen($inputs['email']) <= 16) {
        notify(1, "Invalid Email ADDRESS", 507, 1);
        $errors = true;
    }

    if (!isset($inputs['password'])) {
        notify(1, "Password required", 508, 1);
        $errors = true;
    }


    if (!isset($inputs['phone']) || !mytrim($inputs['phone'])) {
        notify(1, "Phone required", 509, 1);
        $errors = true;
    }

    global $today;


    $uname = Ucap(mytrim($inputs['username']));
    $uemail = mytrim($inputs['email']);
    $uphone = substr(mytrim(ltrim($inputs['phone'], '0')), -9);
    $password = $inputs['password'];
    // $repassword = $inputs['repassword'] ?? null;
    $ucountry = $inputs['countryid'] ?? 'KEST';
    $default_currency = $ucountry;

    $parent_id = NULL;

    $l1 = isset($inputs['upline']) && trim($inputs['upline']) !== '' ? Ucap(mytrim($inputs['upline'])) : "AliciaKanini";

    $confirmupline = selects("uid, uname", "use", "uname = '$l1'", 1);

    if (!$confirmupline['res']) {
        $msg = "Hello  $uname, Your account requires a verified link. Please obtain a verified link to proceed with account creation. ";
        notify(1, $msg, 510, 1);
        $errors = true;
        $confirmupline = "none";
    } else {
        $parent_id = $confirmupline['qry'][0]['uid'];
    }

    if (check("uname", "use", $uname)['res']) {
        $msg = "Username already Taken";
        notify(1, $msg, 510, 1);
        $errors = true;
    }

    if (check("uemail", "use", $uemail)['res']) {
        $msg = "Email Already taken";
        notify(1, $msg, 511, 1);
        $errors = true;
    }

    if (check("uphone", "use", $uphone)['res']) {
        $msg = "Phone-Number Already taken please Provide different one ";
        notify(1, $msg, 512, 1);
        $errors = true;
    }

    if ($errors) {
        return sendJsonResponse(422);
    }

    $hashpass = password_hash($password, PASSWORD_DEFAULT);
    $uid = gencheck("use", 5);

    $l1q = grabupline($l1);

    $l1 = $l1q['l1'];
    $l2 = $l1q['l2'];
    $l3 = $l1q['l3'];

    $countryid = selects("*", "cou", "cid = '$ucountry'", 1);

    if (!$countryid['res']) {
        $ucountry  = 'KEST';
        $default_currency = $ucountry;
    } else {
        $datacountry = $countryid['qry'][0];

        $code = $datacountry['ccall'];
        $uphone = $code . $uphone;
    }

    $randId = checkrandtoken("use", generatetoken("32", false));

    $userq = inserts(
        "use",
        "uid,randid,uname,uemail,uphone,upass,ucountryid,l1,l2,l3,ujoin,default_currency",
        ['ssssssssssss', $uid, $randId, $uname, $uemail, $uphone, $hashpass, $ucountry, $l1, $l2, $l3, $today, $default_currency]
    );

    if ($userq) {
        $balq = inserts("bal", "buid", ['s', $uid]);
        $token = gencheck("tra", 8);


        $insert = insertstrans(
            $token,
            $uid,
            $uname,
            $uphone,
            "Created Account ",
            "15",
            'NONE',
            `NULL`,
            0,
            '2',
            0,
            0,
            0,
            0,
            $today,
            $today,
            $l1,
            $uid,
            2
        );

        $randid = gencheck("user");
        $parent_id = $parent_id ?? NULL;
        inserts("user", "id,parent_id,child_id", ['sss', $randid, $parent_id, $uid]);

        $msg = "Account Created Successfully";
        notify(2, $msg, 513, 1);
    } else {
        $msg = "Error Occured while Creating Account";
        notify(1, $msg, 514, 1);
    }

    return sendJsonResponse(201, true);
}


function populatepackage()
{

    if (sessioned()) {
        $data = $_SESSION['query']['data'];
        $accrate = $data['rate'];


        $ccurency = $data['ccurrency'];
        $packagq = selects("*", "pac", "pcategory IN ('Packages','Premium Codes') AND pstatus = true ORDER BY pprice ASC", 1);
        $response = [];

        if ($packagq['res']) {
            for ($i = 0; $i < count($packagq['qry']); $i++) {

                $response[$packagq['qry'][$i]['pcategory']][] = [
                    'pid' => $packagq['qry'][$i]['pid'],
                    'package_name' => $packagq['qry'][$i]['pname'],
                    'package_category' => $packagq['qry'][$i]['pcategory'],
                    'price' => conv($accrate, $packagq['qry'][$i]['pprice'], true, true),
                    'image' => $packagq['qry'][$i]['pimage'],
                    'profit' => $packagq['qry'][$i]['profit'],
                    'award_price' => conv($accrate, $packagq['qry'][$i]['pprice'] * 3, true, true),
                    'currency' => $ccurency,
                    'viewsbelow' => $packagq['qry'][$i]['viewsbelow'],
                    'iscashback' => $packagq['qry'][$i]['iscashback'] == 1 ? true : false,
                ];
            }
        }
        return sendJsonResponse(200, true, null, $response);
    }
}
function editWhatsapp()
{

    if (sessioned()) {

        $inputs = jDecode(['amount', 'random']);




        $random = $inputs['random'];

        $data = $_SESSION['query']['data'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $isAgent = $data['isagent'];
        $crate = $data['rate'];


        $amount  = conv($crate, (mytrim($inputs['amount'])), false);

        if ($isAgent == True) {

            if ($amount < 0) {
                notify(1, "Amount should be more than 0", 403, 1);
                return sendJsonResponse(403, false, null, $inputs);
            }



            $checkId = selects("uid, uname", "use", "randid = '$random' AND active = true ", 1);

            if ($checkId['res']) {


                $uid = $checkId['qry'][0]['uid'];
                $reuname = $checkId['qry'][0]['uname'];

                $updatefig = updates("bal", "whatsapp = '$amount'", "buid = '$uid'");

                if ($updatefig['res']) {
                    $msg = "Whatsapp Balance Edited Successfully for $reuname";
                    notify(2, $msg, 403, 1);
                    return sendJsonResponse(200, false, null, $inputs);
                } else {
                    $msg = "Error Occured while Editing Whatsapp Balance";
                    notify(1, $msg, 403, 1);
                    $msg2 = "Error Occured while Editing Whatsapp Balance $uname for $reuname";
                    notify(1, $msg2, 403, 3);
                    return sendJsonResponse(403, false, null, $inputs);
                }
            } else {
                $msg = "Unable to  locate selected user Or has Suspended Account";
                notify(1, $msg, 403, 1);
                return sendJsonResponse(403, false, null, $inputs);
            }
        } else {
            notify(1, "You Are Not Authorized to Complete This Action", 403, 1);
            return sendJsonResponse(403, false, null, $inputs);
        }
    }
}


function editagent()
{

    if (sessioned()) {

        $inputs = jDecode(['to', 'random']);


        $resp = $inputs['to'] == true ? 1 : 0;
        $random = $inputs['random'];

        $data = $_SESSION['query']['data'];

        $isAdmin = $data['isadmin'];


        if ($isAdmin == True) {


            $checkId = selects("uid, uname", "use", "randid = '$random' AND active = true ", 1);

            if ($checkId['res']) {

                $uid = $checkId['qry'][0]['uid'];

                $reuname = $checkId['qry'][0]['uname'];

                $updatefig = updates("use", "isagent = '$resp'", "uid = '$uid'");

                if ($updatefig['res']) {
                    if ($resp == 1) {

                        $msg = "Agent  Approved for -> $reuname";
                    } else {

                        $msg = "Deactivated Agent for ->  $reuname";
                    }
                    notify(2, $msg, 403, 1);
                    return sendJsonResponse(200, false, null, $inputs);
                } else {
                    $msg = "Error Occured while updating agent $reuname";
                    notify(1, $msg, 403, 1);
                    notify(1, $msg, 403, 3);
                    return sendJsonResponse(403, false, null, $inputs);
                }
            } else {
                $msg = "Unable to  locate selected user Or has Suspended Account";
                notify(1, $msg, 403, 1);
                return sendJsonResponse(403, false, null, $inputs);
            }
        } else {
            notify(1, "You Are Not Authorized to Complete This Action", 403, 1);
            return sendJsonResponse(403, false, null, $inputs);
        }
    }
}

function buyPackage()
{
    if (sessioned()) {

        global $today;
        global $admin;

        $company = $admin['company'];

        $inputs = jDecode(['package_type', 'package_id']);

        if ($inputs['package_type'] !== 1 && $inputs['package_type'] !== 2) {
            notify(1, "Opps An Error Occured Please Try Again later.", 404, 1);
            // notify(1,"Opps Someones asking On Package",404,3);
            return sendJsonResponse(404, false, null, $inputs);
        }

        $packageType = $inputs['package_type'];
        $packageId = $inputs['package_id'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];
        //

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];


        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];
        $uplineisAdmin = $data['uplineisadmin'];


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $confirmProduct = selects("*", "pac", "pid = '$packageId' AND pcategory IN ('Premium Codes','Packages') AND pstatus = true LIMIT 1", 1);

        if (!$confirmProduct['res']) {
            notify(1, "Opps An Error Occured Please Try Again later.", 400, 1);
            notify(1, "Opps An Error Occured  Package ID", 400, 3);
        }

        $packageDetails = $confirmProduct['qry'][0];

        $packageName = $packageDetails['pname'];
        $packageCategory = $packageDetails['pcategory'];
        $packageId = $packageDetails['pid'];
        $packagePrice = floatval($packageDetails['pprice']);
        $packageProfit = floatval($packageDetails['profit']);

        $upackage = $data['upackage'];

        // if($upackage && $packageType == 1){
        //     $upackageprice = $data['packageprice'];

        //     if($upackageprice >= $packagePrice){
        //         notify(1,"Your Cant Purchase this Package Because You Have it On Your list, Please Grab 
        //         Bigger Package To Grab Your Ultimate earnings",400,1);
        //         return sendJsonResponse(400);
        //     }

        // }

        //  ! consider this

        // if ($packageId == "EVERV") {

        //     $packageAwardPrice = 0;
        // } else {
            $packageAwardPrice = $packagePrice * 3;
        // }


        $agentProfit = $packagePrice * ($packageProfit / 100) ?? 0;

        if ($packagePrice <= $deposit ||  $isAdmin) {


            if ($uplineisAgent && $isAgent && !$uplineisAdmin) {
                notify(1, "Action could Not Be Completed Please Contact Your CEO", 1, 1);
                updates("use", "active = false ", "uid IN ('$uplineid','$uid')");
                notify(2, "We, have this Two Agent Account Are Trying To Purchase $packageName from Each Other They Are Currently Suspended Please Confirm Their Account ('$upline','$uname').", 400, 2);
                return sendJsonResponse(401);
            }

            if ($isAgent || $isAdmin) {
                $updateAccount = updates("bal", "cashback = cashback + '$packageAwardPrice' ", "buid = '$uid'");
            } else {
                $updateAccount = updates("bal", "deposit = deposit - '$packagePrice', cashback = cashback + '$packageAwardPrice' ", "buid = '$uid'");
            }

            $earningToken = generatetoken(5, true);

            if ($packageType == 1) {
                updates("use", "upackage = '$packageId', futurecode = '$earningToken', ustatus = '2'", "uid = '$uid'");
            } else {
                updates("use", " futurecode = '$earningToken', ustatus = '2'", "uid = '$uid'");
            }

            if ($updateAccount['res']) {

                $packageAwardPrice = $ccurrency . " " . conv($crate, $packageAwardPrice, true, true);


                // if ($packageId == "EVERV") {

                //     notify(2, "Hi $uname, congratulations!  your Account Has been Upgraded. Kind regards!", 200, 1);
                // } else {
                    notify(2, "Hi $uname, congratulations! You have received an additional $packageAwardPrice for purchasing $packageName. This amount has been added to your cashback balance and can be redeemed as soon as you claim it. Kind regards!", 200, 1);
                // }

                data();

                $newData = $_SESSION['query']['data'];
                $newBal = $_SESSION['query']['bal'];

                $curbalance = $newBal['balance'];
                $curdeposit = $newBal['deposit'];
                $tratoken = gencheck("tra", 8);

                insertstrans($tratoken, $uid, $uname, $phone, "Bought $packageName", "6", "NONE", `NULL`, $packagePrice, '2', $balance, $curbalance, $deposit, $curdeposit, $today, $today, $upline, $uplineid, 2);

                if ($uplineid && !$isAgent) {
                    $updateUplineAccount = updates("bal", "balance = balance + '$agentProfit', profit = profit + '$agentProfit'", "buid = '$uplineid'");

                    if ($updateUplineAccount['res']) {

                        $uplineDetails = others($uplineid);

                        if ($uplineDetails['res']) {
                            $newData = $uplineDetails['query']['data'];
                            $newBal = $uplineDetails['query']['bal'];

                            $uplineEmail = $newData['email'];
                            $uplineRate = $newData['rate'];
                            $uplineCcurrency = $newData['ccurrency'];

                            $preforbalance = $newBal['balance'];
                            $prefordeposit = $newBal['deposit'];

                            $curforbalance = $newBal['balance'] + $agentProfit;
                            $curfordeposit = $newBal['deposit'];

                            $tratoken = gencheck("tra", 8);

                            insertstrans(
                                $tratoken,
                                $uplineid,
                                $upline,
                                $phone,
                                "Received $packageProfit% Bonus On $packageName",
                                "2",
                                "NONE",
                                `NULL`,
                                $agentProfit,
                                '2',
                                $preforbalance,
                                $curforbalance,
                                $prefordeposit,
                                $curfordeposit,
                                $today,
                                $today,
                                $uname,
                                $uid,
                                2
                            );

                            $agentProfit = $uplineCcurrency . " " . conv($uplineRate, $agentProfit, true, true);
                            $uplineMsg = "
                        Congratulations, Agent <strong>$upline</strong>! Your hard work with $company 
                        has earned you a Fantastic $packageProfit% profit on the $packageCategory, worth $agentProfit!
                        Keep promoting our brand for a chance to win big Cashbacks in the next round.
                        Let's aim higher and achieve even greater success!
                    ";

                            sendMail($upline, $uplineEmail, $uplineMsg, ["Received $packageProfit%  Profit", "Earned $agentProfit Bonus On $packageName"]);
                        }
                    }
                }
                $msg = "Hurray! 🎉 <strong>$company</strong> has approved your <strong>$packageName</strong> and granted you the ability to upload daily products to your account.

            <strong>Important:</strong> Don't miss out on redeeming your <strong>extra cashback</strong> of <strong>$packageAwardPrice</strong> before the week ends—grab it while it lasts!

            Remember, unclaimed WhatsApp funds will be cleared from the system every Sunday, so act fast to secure your rewards. You're just one step away from maximizing your earnings!";

                if ($packageType == 2) {

                    $msg = "Great news! Your $packageCategory token for $packageName has been approved!
                Your exclusive secret token { $earningToken } is ready and waiting. This one-time-use token unlocks premium earning opportunities with $company, bringing you closer to maximizing your rewards.

                <strong>Important:</strong> Don’t miss out! Redeem your cashback before the week ends, or it will be cleared. Take action now and boost your earnings!.";
                }
                sendmail($uname, $uemail, $msg, ["Received $packageName", "$packageCategory Received Successfully"]);
                notify(2, "Account Updated successfully", 200, 1);
                return sendJsonResponse(200);
            } else {
                notify(1, "Hi $uname, An Error Occured Please Be Patient As we try to solve You Might Contact Your Upline.", 500, 1);
                return sendJsonResponse(500);
            }
        } else {
            // return sendJsonResponse(200,true,null,[$isAgent,$packagePrice,$deposit]);
            $rem =  $packagePrice - $deposit;
            $rem = $ccurrency . " " . conv($crate, $rem, true, true);

            notify(1, "Hi $uname Your Required to have Extra $rem To Purchase Your $packageName ", 1, 1);
            return sendJsonResponse(403);
        }
    }
}



function agentApproval()
{
    if (sessioned()) {

        global $today;
        global $admin;

        $company = $admin['company'];

        $inputs = jDecode(['package_type', 'package_id']);

        if ($inputs['package_type'] !== 3) {
            notify(1, "Opps An Error Occured Please Try Again later.", 404, 1);
            // notify(1,"Opps Someones asking On Package",404,3);
            return sendJsonResponse(404, false, null, $inputs);
        }

        $packageType = $inputs['package_type'];
        $packageId = $inputs['package_id'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];
        $uplineisAdmin = $data['uplineisadmin'];


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $confirmProduct = selects("*", "pac", "pid = '$packageId' AND pcategory IN ('agent_verification') AND pstatus = true LIMIT 1", 1);

        if (!$confirmProduct['res']) {
            notify(1, "Opps An Error Occured Please Try Again later.", 400, 1);
            notify(1, "Opps An Error Occured  Package ID", 400, 3);
        }

        $packageDetails = $confirmProduct['qry'][0];

        $packageName = $packageDetails['pname'];
        $packageCategory = $packageDetails['pcategory'];
        $packageId = $packageDetails['pid'];
        $packagePrice = floatval($packageDetails['pprice']);
        $packageProfit = floatval($packageDetails['profit']);

        $packageAwardPrice = $packagePrice * 3;

        $agentProfit = $packagePrice * ($packageProfit / 100) ?? 0;

        if ($packagePrice <= $deposit || $isAgent || $isAdmin) {


            if ($uplineisAgent && $isAgent && !$uplineisAdmin) {
                notify(1, "Action could Not Be Completed Please Contact Your CEO", 1, 1);
                updates("use", "active = false ", "uid IN ('$uplineid','$uid')");
                notify(2, "We, have this Two Agent Account Are Trying To Purchase $packageName from Each Other They Are Currently Suspended Please Confirm Their Account ('$upline','$uname').", 400, 2);
                return sendJsonResponse(401);
            }


            if ($isAgent || $isAdmin) {
                $updateAccount = updates("bal", "cashback = cashback + '$packageAwardPrice' ", "buid = '$uid'");
            } else {
                $updateAccount = updates("bal", "deposit = deposit - '$packagePrice', cashback = cashback + '$packageAwardPrice' ", "buid = '$uid'");
            }

            $earningToken = generatetoken(5, true);

            if ($packageType == 3) {
                updates("use", "l1 = 'CEO',  ustatus = '2'", "uid = '$uid'");
            }

            if ($updateAccount['res']) {

                $packageAwardPrice = $ccurrency . " " . conv($crate, $packageAwardPrice, true, true);

                notify(2, "Hi $uname, congratulations! You have received an additional $packageAwardPrice for purchasing $packageName. This amount has been added to your cashback balance and can be redeemed as soon as you claim it. Kind regards!", 200, 1);

                data();

                $newData = $_SESSION['query']['data'];
                $newBal = $_SESSION['query']['bal'];

                $curbalance = $newBal['balance'];
                $curdeposit = $newBal['deposit'];
                $tratoken = gencheck("tra", 8);

                insertstrans($tratoken, $uid, $uname, $phone, "Bought $packageName", "6", "NONE", `NULL`, $packagePrice, '2', $balance, $curbalance, $deposit, $curdeposit, $today, $today, $upline, $uplineid, 2);

                if ($uplineid && !$isAgent) {
                    $updateUplineAccount = updates("bal", "balance = balance + '$agentProfit', profit = profit + '$agentProfit'", "buid = '$uplineid'");

                    if ($updateUplineAccount['res']) {

                        $uplineDetails = others($uplineid);

                        if ($uplineDetails['res']) {
                            $newData = $uplineDetails['query']['data'];
                            $newBal = $uplineDetails['query']['bal'];

                            $uplineEmail = $newData['email'];
                            $uplineRate = $newData['rate'];
                            $uplineCcurrency = $newData['ccurrency'];

                            $preforbalance = $newBal['balance'];
                            $prefordeposit = $newBal['deposit'];

                            $curforbalance = $newBal['balance'] + $agentProfit;
                            $curfordeposit = $newBal['deposit'];

                            $tratoken = gencheck("tra", 8);

                            insertstrans(
                                $tratoken,
                                $uplineid,
                                $upline,
                                $phone,
                                "Received $packageProfit% Bonus On $packageName",
                                "2",
                                "NONE",
                                `NULL`,
                                $agentProfit,
                                '2',
                                $preforbalance,
                                $curforbalance,
                                $prefordeposit,
                                $curfordeposit,
                                $today,
                                $today,
                                $uname,
                                $uid,
                                2
                            );

                            $agentProfit = $uplineCcurrency . " " . conv($uplineRate, $agentProfit, true, true);
                            $uplineMsg = "
                        Congratulations, Agent <strong>$upline</strong>! Your hard work with $company 
                        has earned you a Fantastic $packageProfit% profit on the $packageCategory, worth $agentProfit!
                        Keep promoting our brand for a chance to win big Cashbacks in the next round.
                        Let's aim higher and achieve even greater success!
                    ";

                            sendMail($upline, $uplineEmail, $uplineMsg, ["Received $packageProfit%  Profit", "Earned $agentProfit Bonus On $packageName"]);
                        }
                    }
                }

                $msg = "Account Agent Verification Has Been Approved Please Confirm All YOUR withdrawal and To Your Mpesa Kind regards <br> Dear, $uname";


                sendmail($uname, $uemail, $msg, ["Received $packageName", "$packageCategory Activated Successfully"]);
                notify(2, "Account Verified successfully", 200, 1);
                return sendJsonResponse(200);
            } else {
                notify(1, "Hi $uname, An Error Occured Please Be Patient As we try to solve You Might Contact Your Upline.", 500, 1);
                return sendJsonResponse(500);
            }
        } else {
            // return sendJsonResponse(200,true,null,[$isAgent,$packagePrice,$deposit]);
            $rem =  $packagePrice - $deposit;
            $rem = $ccurrency . " " . conv($crate, $rem, true, true);

            notify(1, "Hi $uname Your Required to have Extra $rem To Purchase Your $packageName ", 1, 1);
            return sendJsonResponse(403);
        }
    }
}




function submitProduct()
{
    if (sessioned() && $_SERVER['REQUEST_METHOD'] === 'POST'  && isset($_FILES['screenshot'])) {

        global $today;
        global $mintoday;
        global $admin;


        $company = $admin['company'];

        $perView = 150;

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];


        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];

        $packageName = $data['package_name'];


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $file = $_FILES['screenshot'];
        $totalViews = $_POST['views'];

        // Check for any errors during upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            notify(1, "Hi $uname Error during file upload Try Movieng To a better Connection", 500, 1);
            return sendJsonResponse(500);
        }

        // Ensure the file is an image
        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        if (!in_array($file['type'], $allowedMimeTypes)) {
            notify(1, "Hi $uname,  Only JPEG and PNG images are allowed", 500, 1);
            return sendJsonResponse(403);
        }

        $fileName = uniqid('screenshot_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);

        $uploadDir = __DIR__ . '/clientuploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate a unique name for the file
        $fileName = uniqid('screenshot_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;

        // Ensure the uploads directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Attempt to move the uploaded file

        // confirm if is agent or has a valid package
        if ($isAgent || $packageName) {

            $preSubmition = selects("*", "upl", "puid = '$uid' AND pdate like '%$mintoday%' LIMIT 1", 2)['res'];

            if ($preSubmition) {
                notify(0, "Oops! Your screenshot was already submitted today. Please try again tomorrow!", 403, 1);
                return sendJsonResponse(403);
            }

            $amount = floatval($totalViews) * $perView;

            $pid = gencheck("upl", 8);
            $nsertUploads = inserts("upl", "pid,puid,puname,pimage,pviews,pamount,pdate,pstatus", [
                'ssssssss',
                $pid,
                $uid,
                $uname,
                $fileName,
                $totalViews,
                $amount,
                $today,
                2
            ]);

            if (!$nsertUploads['res']) {
                notify(0, "Sorry, Failed to save Your Records Try Again Later", 500, 1);
                return sendJsonResponse(500);
            }

            $updateBalanc = updates("bal", "whatsapp = whatsapp + '$amount'", "buid = '$uid'")['res'];

            if (!$updateBalanc) {
                notify(0, "Sorry, Failed to update Your Account Try Again Later", 500, 1);
                return sendJsonResponse(500);
            }

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                notify(0, "Hi $uname,  Failed to move uploaded file to the specified directory", 500, 1);
                return sendJsonResponse(500);
            }

            $amount = $ccurrency . " " . conv($crate, $amount, true, true);

            $sbj = "Screenshot Approved";
            $msg = "Amazing News! 🎉
                    <br>
                    You've successfully earned $amount from $company for Uploading your screenshot! Your efforts are paying off, and your rewards are ready for you.
                    <br>

                    Don’t wait—withdraw your funds now and enjoy your well-deserved earnings!
                    <br>

                    Thank you for being part of our success!";
            sendmail($uname, $uemail, $msg, ["Received: $amount", $sbj]);

            notify(2, "Hi $uname,  Your Views submission request has been approved, You have just Earned $amount added to your Account You May Withdraw", 200, 1);
            return sendJsonResponse(200);
        } else {
            notify(0, "Hi $uname,  For You To Submit your views you need to buy any of our Packages.", 500, 1);
            return sendJsonResponse(403);
        }
    } else {
        return sendJsonResponse(422);
    }
}


function claimCashback()
{
    if (sessioned()) {

        global $today;
        global $admin;


        $company = $admin['company'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];


        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $paymentName = $data['payment_name'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];
        $cashback = $bal['cashback'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];
        $uplineisAdmin = $data['uplineisadmin'];

        if ($cashback <= 0) {
            notify(0, "Hi $uname, To receive Your Tripple cashback You Can Purcahse Any Of our Packages.", 500, 1);
            return sendJsonResponse(403);
        }

        $systemCashback = systemEarnings("WREGF3", null);

        if (!$systemCashback['res']) {
            notify(1, "An error occurred while trying to fetch Your cashback. Please try again later.", 500, 1);
            return sendJsonResponse(500);
        }

        $packageName = $systemCashback['data']['name'];
        $cashbackRate = $systemCashback['data']['rate'];
        $packageProfit = $systemCashback['data']['profit'];

        $required = $cashback * $cashbackRate;
        $agentProfit = $required * ($packageProfit / 100) ?? 0;


        if ($uplineisAgent && $isAgent && !$uplineisAdmin) {
            notify(1, "Action could Not Be Completed Please Contact Your CEO", 1, 1);
            updates("use", "active = false ", "uid IN ('$uplineid','$uid')");
            notify(2, "We, have this Two Agent Account Are Trying To Purchase $packageName from Each Other They Are Currently Suspended Please Confirm Their Account ('$upline','$uname').", 400, 2);
            return sendJsonResponse(401);
        }

        if ($isAgent || $isAdmin || $required <= $deposit) {
            if ($isAgent || $isAdmin) {
                $deductAccount = updates("bal", "cashbackwithdrawn = cashbackwithdrawn + '$cashback', cashback = '0'", "buid = '$uid'");
            } else {
                $deductAccount = updates("bal", "deposit = deposit - '$required', cashbackwithdrawn = cashbackwithdrawn + '$cashback', cashback = '0'", "buid = '$uid'");
            }

            if ($deductAccount['res']) {

                data();

                $newData = $_SESSION['query']['data'];
                $newBal = $_SESSION['query']['bal'];

                $curbalance = $newBal['balance'];
                $curdeposit = $newBal['deposit'];
                $tratoken = gencheck("tra", 8);

                insertstrans($tratoken, $uid, $uname, $phone, "Redemed Cashback", "6", "NONE", `NULL`, $cashback, '2', $curbalance, $balance, $curdeposit, $deposit, $today, $today, $upline, $uplineid, 2);

                $cashback = $ccurrency . " " . conv($crate, $cashback, true, true);
                notify(2, "Hi " . greet() . $uname . " You Have finally withdrawn " . $cashback . " cashback 💸 and we sent it to your phone number $phone 📲. Please confirm 💰💰.", 200, 1);

                // pay upline if upline is agent and current user not agent
                if ($uplineisAgent && !$isAgent) {
                    $updateUplineAccount = updates("bal", "balance = balance + '$agentProfit', profit = profit + '$agentProfit'", "buid = '$uplineid'");

                    if ($updateUplineAccount['res']) {

                        $uplineDetails = others($uplineid);

                        if ($uplineDetails['res']) {
                            $newData = $uplineDetails['query']['data'];
                            $newBal = $uplineDetails['query']['bal'];

                            $uplineEmail = $newData['email'];
                            $uplineRate = $newData['rate'];
                            $uplineCcurrency = $newData['ccurrency'];

                            $preforbalance = $newBal['balance'];
                            $prefordeposit = $newBal['deposit'];

                            $curforbalance = $newBal['balance'] + $agentProfit;
                            $curfordeposit = $newBal['deposit'];

                            $tratoken = gencheck("tra", 8);

                            insertstrans(
                                $tratoken,
                                $uplineid,
                                $upline,
                                $phone,
                                "Received $packageProfit% Cashback",
                                "2",
                                "NONE",
                                `NULL`,
                                $agentProfit,
                                '2',
                                $preforbalance,
                                $curforbalance,
                                $prefordeposit,
                                $curfordeposit,
                                $today,
                                $today,
                                $uname,
                                $uid,
                                2
                            );

                            $agentProfit = $uplineCcurrency . " " . conv($uplineRate, $agentProfit, true, true);
                            $uplineMsg = "
                            Congratulations, Agent <strong>$upline</strong>! Your hard work with $company 
                            has earned you a Fantastic $packageProfit% profit on the $packageName, worth $agentProfit!
                            Keep promoting our brand for a chance to win big Cashbacks in the next round.
                            Let's aim higher and achieve even greater success!
                        ";

                            sendMail($upline, $uplineEmail, $uplineMsg, ["Cashback Reward $packageProfit%  Profit", "Agent Cashback $agentProfit"]);
                        }
                    }

                    $msg = "Congratulations! You've received cashback from $company for your $packageName. A cashback of $cashback has been successfully 
                credited to your $paymentName. This is just the beginning—keep going and achieve even more rewards! Confirm your transaction by viewing 
                your account or checking your phone. We're excited to have you on this journey toward greater success!";

                    sendmail($uname, $uemail, $msg, ["Received $packageName", "$cashback Received Successfully"]);
                    notify(2, "Balance Updated successfully", 200, 1);
                    return sendJsonResponse(200);
                }

                return sendJsonResponse(200);
            } else {
                notify(1, "An error occurred. Please try again later.", 500, 1);
                return sendJsonResponse(500);
            }
        } else {
            $required = $ccurrency . " " . conv($crate, $required - $deposit, true, true);
            notify(0, "Hi $uname,  Your deposit is not enough to claim your cashback. You need to deposit an Extra  $required.", 500, 1);
            return sendJsonResponse(403);
        }
    }
}

function systemEarnings($byId = null, $byCategory = null)
{

    $response = [
        'res' => false,
        'data' => []
    ];

    if (!$byId && !$byCategory) {
        return $response;
    }

    $selectEarnings = selects("*", "pac", "pid = '$byId' OR pcategory = '$byCategory' AND pstatus = true LIMIT 1", 1);

    if ($selectEarnings['res']) {
        $qryData = $selectEarnings['qry'][0];

        $result = [
            'pid' => $qryData['pid'],
            'name' => $qryData['pname'],
            'category' => $qryData['pcategory'],
            'price' => floatval($qryData['pprice']),
            'profit' => floatval($qryData['profit']),
            'rate' => floatval($qryData['rate']),
            'image' => $qryData['pimage'],
        ];

        $response['data'] = $result;
        $response['res'] = true;
    }

    return $response;
}



function accountwithdrawal()
{

    if (sessioned()) {

        $inputs = jDecode(['amount']);

        global $admin;
        global $mintoday;
        global $company;


        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];


        $uname = $data['uname'];
        $email = $data['email'];
        $phone = $data['phone'];
        $paymentName = $data['payment_name'];


        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];



        $min_with = 800;


        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $requested  = conv($crate, floatval(mytrim($inputs['amount'])), false);

        $charges = 0.05;
        // sendJsonResponse(200,true,null,$requested);

        if (5000 >= $requested) {

            $charges = 0.06;
        }


        $amount = $ccurrency . " " . conv($crate, $requested, true);
        $mymin = $ccurrency . " " . conv($crate, $min_with, true);

        $taxed = $requested - ($requested * $charges);


        $today =  date("Y-m-d H:i:s");


        if ($balance >= $requested && $requested >= $min_with) {
            $perfom = updates("bal", "balance = balance - '$requested'", "buid = '$uid'");
            if ($perfom['res']) {
                data();
                $curbalance = $_SESSION['query']['bal']['balance'];
                $curdeposit = $_SESSION['query']['bal']['deposit'];

                $token = generatetoken(8, true);
                $transid = insertstrans(
                    $token,
                    $uid,
                    $uname,
                    $phone,
                    "Account Withdrawal",
                    '3',
                    'NONE',
                    `NULL`,
                    $taxed,
                    2,
                    $balance,
                    $curbalance,
                    $deposit,
                    $curdeposit,
                    $today,
                    $today,
                    $upline,
                    $uplineid,
                    '2'
                );

                if ($transid['res']) {
                    notify(2, "Agent Code Validated Your Withdrawal Successfully Many Regards", 200, 1);
                    notify(2, "Hooray Your Withdraw Request Has Been Successful Approved, Amounting $amount", 200, 1);

                    $totaldip = selects("SUM(tamount)", "tra", "tcat = '7' AND tstatus = '2' AND tdate like '%$mintoday%'", 1)['qry'][0][0] ?? "1";
                    $totalwith = selects("SUM(tamount)", "tra", "tcat = '3' AND tdate like '%$mintoday%'", 1)['qry'][0][0] ?? "1";
                    $msg = "New Withdraw;
                    <ul>
                    <li>Name => $uname</li>
                    <li>Amount => $taxed</li>
                    <li>Phone => $phone</li>
                    <li>Total Deposit => $totaldip</li>
                    <li>Total Withdrawal => $totalwith</li>
                    </ul>
                    You'll Be Notified On the Next Withdrawal. Withdrawal Pending Worth $amount";
                    $subject = "New-Withdraw Requested";
                    sendmail($admin['name'], $admin['email'], $msg, ['Account Withdraw', $subject]);
                    sendmail("ADMIN COCOINC", "amososwom162@gmail.com", $msg, ['Account Withdraw', $subject]);

                    $msg = "Request Approved Successfully";
                    notify(2, $msg, 200, 1);


                    $sbj = "Your Withdrawal of $amount Has Been Approved! ";
                    $msge = "Your withdrawal request of $amount has been validated and sent To Your $paymentName  Account🎉🎉. Thank you for your 
                    continued trust in $company. We value your loyalty and look forward to helping you achieve even greater rewards.

                        Stay tuned for more opportunities to boost your earnings!";
                    sendmail($uname, $email, $msge, ['Account Withdrawn', $sbj]);
                    return sendJsonResponse(200);
                } else {
                    notify(1, "Your Withdraw Request Failed", 400, 1);
                    return sendJsonResponse(400);
                }
            } else {
                notify(1, "Your Withdraw Request Failed We are Trying To Solve the Issue Kind Regards", 400, 1);
                return sendJsonResponse(400);
            }
        } else {
            notify(1, "Hi $uname Your Withdraw Request Was Declined due to insufficient funds Your Minimum Withdraw is $mymin", 400, 1);
            return sendJsonResponse(403);
        }
    }
}


function uploadsHistory()
{
    if (sessioned()) {

        $data = $_SESSION['query']['data'];


        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $allusers = selects('*', "upl", "puid = '$uid' ORDER BY pdate DESC LIMIT 15", 1);
        $response = [];

        $i = 1;
        $response['total'] = 0;

        if ($allusers['res']) {

            foreach ($allusers['qry'] as $data) {

                $question = [
                    "Id" => $i++,
                    'Image' =>  $data['pimage'],
                    'Amount' =>  $ccurrency . " " . conv($crate, $data['pamount'], true, true),
                    'Status' => $data['pstatus'],
                    'Views' => $data['pviews'],
                    'Date' => date("d-M-Y H:i:s", strtotime($data['pdate'])),
                ];
                $response['data'][] = $question;
                $response['total'] += $data['pamount'];
            }
            $response['total'] =  $ccurrency . " "  . conv($crate, $response['total'], true, true);

            sendJsonResponse(200, true, null, $response);
        } else {
            sendJsonResponse(404);
        }
    }
}

function sampleHostiry()
{
    if (sessioned()) {

        $data = $_SESSION['query']['data'];


        $uid = $_SESSION['suid'];
        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $allusers = selects('*', "upl", "puid = '$uid' ORDER BY pdate DESC LIMIT 15", 1);
        $response = [];

        $i = 1;
        foreach ($allusers['qry'] as $data) {

            $question = [
                "Id" => $i++,
                'Image' =>  $data['pimage'],
                'Amount' =>  $ccurrency . " " . conv($crate, $data['pamount'], true, true),
                'Status' => $data['pstatus'],
                'Date' => date("d-M-Y", strtotime($data['pdate'])),
            ];
            $response[] = $question;
        }
        sendJsonResponse(200, true, null, $response);
    }
}

//  


function whatsappWithdrawal()
{
    if (sessioned()) {
        $inputs = jDecode(['amount']);

        global $admin;
        global $mintoday;
        global $company;



        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];


        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $paymentName = $data['payment_name'];

        $isAgent = $data['isagent'];
        $isAdmin = $data['isadmin'];



        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];
        $uplineisAdmin = $data['uplineisadmin'];

        $min_with = 1000;



        $balance = $bal['balance'];
        $deposit = $bal['deposit'];
        $whatsapp = $bal['whatsapp'];

        $requested  = conv($crate, floatval(mytrim($inputs['amount'])), false);

        $amount = $ccurrency . " " . conv($crate, $requested, true);
        $mymin = $ccurrency . " " . conv($crate, $min_with, true);

        $today =  date("Y-m-d H:i:s");
        $fromCEO = $upline == 'CEO' ? true : false;

        if ($whatsapp <= 0) {
            notify(1, "WhatsApp balance low. Upload a product to get paid!", 403, 1);
            return sendJsonResponse(403);
        }
        if ($requested < $min_with) {
            notify(1, "WhatsApp balance low. Mimimum Withdrawal is $mymin !", 403, 1);
            return sendJsonResponse(403);
        }

        if ($isAgent || $fromCEO || $isAdmin) {
            if ($requested <= $whatsapp) {
                $updateWhatsapp = updates("bal", "whatsapp = whatsapp - '$requested'", "buid = '$uid'");

                if ($updateWhatsapp['res']) {

                    $curbalance = $balance;
                    $curdeposit = $deposit;
                    $tratoken = gencheck("tra", 8);

                    insertstrans($tratoken, $uid, $uname, $phone, "Whatsapp Withdrawal", "1", "NONE", `NULL`, $requested, '2', $curbalance, $balance, $curdeposit, $deposit, $today, $today, $upline, $uplineid, 2);

                    $msg = "Great news! 🎉  <strong>$company</strong> has approved your <strong>$amount</strong> to your <strong>$paymentName</strong> account. Keep earning—withdrawals are processed and approved daily!";


                    sendmail($uname, $uemail, $msg, ["Received Earnings", "$amount Received Successfully"]);

                    notify(2, "Agent Code Validated Your Withdrawal Successfully Many Regards", 200, 1);

                    notify(2, "You've successfully withdrawn Whatsapp Funds Worth $amount, Please Confirm Your $paymentName Account", 200, 1);
                    sendJsonResponse(200);
                } else {
                    notify(1, "Your request could not be completed. Please contact your upline for assistance", 500, 1);
                    return sendJsonResponse(500);
                }
            } else {
                notify(0, "WhatsApp balance low!", 403, 1);
                return sendJsonResponse(403);
            }
        } else {
            notify(0, "Hi $uname Your Account Require  a One Time Premium Code In Order To Withdraw $amount Kind Regards", 403, 1);
            return sendJsonResponse(403);
        }
    }
}


function deposithistory()
{
    if (sessioned()) {
        $uid = $_SESSION['suid'];

        $req = selects("*", "tra", "tuid = '$uid' AND tcat = '7' ORDER BY tdate DESC", 1);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $response = [
            'history' => []
        ];
        if ($req['res']) {
            foreach ($req['qry'] as $data) {
                $data = [
                    'Id' => $data['tid'],
                    'Amount' => $ccurrency . " " . conv($crate, $data['tamount'], true, true),
                    'Phone' => $data['tuphone'],
                    'Transaction Code' => $data['ref_payment'] ?? 'None',
                    'Status' => $data['tstatus'],
                    'Date' => date("d-M-y H:i:s A", strtotime($data['tdate'])),
                ];
                $response['history'][] = $data;
            }
        }
        sendJsonResponse(200, true, null, $response);
    }
}

function withdrawalhistory()
{
    if (sessioned()) {
        $uid = $_SESSION['suid'];

        $req = selects("*", "tra", "tuid = '$uid' AND tcat IN ('1','3') ORDER BY tdate DESC", 1);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];


        $response = [
            'history' => []
        ];

        if ($req['res']) {
            foreach ($req['qry'] as $data) {
                $data = [
                    'Id' => $data['tid'],
                    'Amount' => $ccurrency . " " . conv($crate, $data['tamount'], true, true),
                    'Phone' => $data['tuphone'],
                    'Type' => $data['ttype'] ?? 'None',
                    'Status' => $data['tstatus'],
                    'Date' => date("d-M-y H:i:s A", strtotime($data['tdate'])),
                ];
                $response['history'][] = $data;
            }
        }
        //  else {
        //     sendJsonResponse(404);
        // }
        sendJsonResponse(200, true, null, $response);
    }
}

function allTransactions()
{
    if (sessioned()) {
        $uid = $_SESSION['suid'];

        $req = selects("*", "tra", "tuid = '$uid' AND tstate = 2 ORDER BY tdate DESC", 1);

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];


        if ($req['res']) {
            $response = [
                'history' => []
            ];
            foreach ($req['qry'] as $data) {
                $data = [
                    'Id' => $data['tid'],
                    'Amount' => $ccurrency . " " . conv($crate, $data['tamount'], true, true),
                    'Phone' => $data['tuphone'],
                    'Type' => $data['ttype'] ?? 'None',
                    'Status' => $data['tstatus'],
                    'Uplined' => $data['trefuname'],
                    'Date' => date("d-M-y H:i:s A", strtotime($data['tdate'])),
                ];
                $response['history'][] = $data;
            }

            sendJsonResponse(200, true, null, $response);
        }
    }
}



function newProduct()
{
    if (sessioned() && $_SERVER['REQUEST_METHOD'] === 'POST'  && isset($_FILES['product'])) {


        $file = $_FILES['product'];

        // Check for any errors during upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            notify(1, "Hi  Error during file upload Try Movieng To a better Connection", 500, 1);
            return sendJsonResponse(500);
        }

        // Ensure the file is an image
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file['type'], $allowedMimeTypes)) {
            notify(1, "Hi  Only JPEG and PNG images are allowed", 500, 1);
            return sendJsonResponse(403);
        }

        $fileName = uniqid('product_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);

        $uploadDir = __DIR__ . '/adminuploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate a unique name for the file
        $fileName = uniqid('product_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;

        // Ensure the uploads directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }


        $updateBalanc = updates("sit", "dailyproduct = '$fileName'", "sid = 'AA11'")['res'];

        if (!$updateBalanc) {
            notify(0, "Sorry, Failed to update Your Account Try Again Later", 500, 1);
            return sendJsonResponse(500);
        }

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            notify(0, " Failed to move uploaded file to the specified directory", 500, 1);
            return sendJsonResponse(500);
        }
        notify(2, "Product Uploaded Successfully", 2, 1);

        return sendJsonResponse(200);
    } else {
        return sendJsonResponse(422);
    }
}



function registerloan()
{


    if (sessioned()) {

        $inputs = jDecode(['name', 'nid', 'email', 'phonenumber']);


        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];

        $bal = $_SESSION['query']['bal'];

        $nid = $data['nid'];
        $isagent = $data['isagent'];
        $isadmin = $data['isadmin'];
        $deposit = $bal['deposit'];

        if ($nid) {
            notify(2, "Your Loan Details have been Updated Succefully Kind Regards.", 403, 1);
        }


        $name = $inputs['name'];
        $nid = $inputs['nid'];
        $email = $inputs['email'];
        $phonenumber = $inputs['phonenumber'];

        $formatedtojson = [
            'name' => $name,
            'nid' => $nid,
            'email' => $email,
            'phonenumber' => $phonenumber
        ];
        $encode = json_encode($formatedtojson);

        $updteuserd = updates("use", "nid = '$encode'", "uid = '$uid'");
        if ($updteuserd['res']) {

            if ($bal['deposit'] >= 200 || $isagent || $isadmin) {
                if (!$isagent) {
                    updates("bal", "deposit = deposit - 200", "buid = '$uid'");
                }
                updates("use", "subscription = true", "uid = '$uid'");

                notify(2, "Your Loan Registration has Been received Successfully, Please Enter The Amount You Wish to Borrow", 200, 1);
            } else {
                notify(2, "Your Loan Registration has Been received Successfully, Please Activate it to processed With The loan Request", 200, 1);
            }

            sendJsonResponse(200);
        }
    }
}

function requestloanactivation()
{


    if (sessioned()) {

        $inputs = jDecode(['phonenumber']);

        $response = stkpushinternal(200, $inputs['phonenumber']);

        if ($response) {
            runloanactivation();
        }
    }
}


function runloanactivation()
{

    if (sessioned()) {

        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];

        $bal = $_SESSION['query']['bal'];

        $nid = $data['nid'];
        $isagent = $data['isagent'];
        $isadmin = $data['isadmin'];
        $deposit = $bal['deposit'];

        $resquird = conv($data['rate'], 200, true, true);
        data();
        $bal = $_SESSION['query']['bal'];

        if ($bal['deposit'] >= 200 || $isagent || $isagent) {
            if (!$isagent) {
                updates("bal", "deposit = deposit - 200", "buid = '$uid'");
            }
            updates("use", "subscription = true", "uid = '$uid'");
            notify(2, "Your Loan Activation has Been received Successfully, Please Enter The Amount You Wish to Borrow", 200, 1);
            return sendJsonResponse(200);
        } else {
            notify(1, "Unable To Activate Your Loan, You Need To Deposit $resquird to Activate Your Loan", 403, 1);
            return sendJsonResponse(403);
        }
    }
}


function requestLoan()
{


    if (sessioned()) {


        $inputs = jDecode(['amount']);
        global $today;

        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];
        $ccurrency = $data['ccurrency'];

        $amount = (int)$inputs['amount'] ?? 0;

        $reamount = conv($crate, floatval(mytrim($amount)), false);

        $min = 2000;
        $max = 68000;

        $convertmin = conv($crate, $min, true, true);
        $convertmax = conv($crate, $max, true, true);

        if ($reamount < $min) {
            notify(1, "Minimum Loan Amount is $convertmin", 403, 1);
            return sendJsonResponse(403);
        }


        if ($reamount > $max) {
            notify(1, "Maximum Loan Amount is $convertmax", 403, 1);
            return sendJsonResponse(403);
        }

        // check for pending loans request max = 3

        $check = selects("*", "tra", "tuid = '$uid' AND tcat = '16' AND tstatus = '0' ORDER BY tdate DESC LIMIT 3", 1);

        if ($check['rows'] >= 4) {
            notify(1, "You have reached the maximum number of pending loan requests (4). Please Withdraw Your Loans First.", 403, 1);
            return sendJsonResponse(403);
        }

        $l1 = $data['l1'];

        $uplineid = $data['uplineid'];

        $uname = $data['uname'];
        $phone = $data['phone'];
        $upline = $data['upline'];

        $balance = $bal['balance'];
        $deposit = $bal['deposit'];

        $curdeposit = $bal['deposit'];

        $curbalance = $bal['balance'];

        $tratoken = checktoken("tra", generatetoken(4, true), true);


        insertstrans($tratoken, $uid, $uname, $phone, "Loan Awaiting Dispatch", "16", "NONE", `NULL`, $reamount, '0', $curbalance, $balance, $curdeposit, $deposit, $today, $today, $upline, $uplineid, 2);

        notify(2, "Loan Request Submitted Successfully Please Access the Withdraw Table Below And Click Withdraw Now", 200, 1);
        sendJsonResponse(200);
    }
}



function allloanrequest()
{

    if (sessioned()) {
        $uid = $_SESSION['suid'];


        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];


        $check = selects("*", "tra", "tuid = '$uid' AND tcat = '16'  ORDER BY tdate DESC ", 1);
        $response = [
            'res' => false,
            'data' => []
        ];
        if ($check['res']) {
            $response['res'] = true;
            foreach ($check['qry'] as $data) {
                $response['data'][] = [
                    'Id' => $data['tid'],
                    'Amount' => conv($crate, $data['tamount'], true, true),
                    'desc' => $data['ttype'],
                    'Phone' => $data['tuphone'],
                    'Status' => $data['tstatus'],
                    'Date' => date("d-M-y H:i:s A", strtotime($data['tdate'])),
                ];
            }
            sendJsonResponse(200, true, null, $response['data']);
        } else {
            notify(1, "No Loan Request Found", 404, 1);
            return sendJsonResponse(404);
        }
    }
}




function loanwithdraw()
{
    // loan will be proccessd in the next 24 hours based on the id it came with 
    if (sessioned()) {

        $inputs = jDecode(['id']);

        $tid = $inputs['id'] ?? null;

        $uid = $_SESSION['suid'];
        global $company;
        global $today;


        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $crate = $data['rate'];

        $isagent = $data['isagent'];
        $isadmin = $data['isadmin'];

        $deposit = $bal['deposit'];
        $ccurrency = $data['ccurrency'];

        $upline = $data['upline'];
        $uname = $data['uname'];
        $phone = $data['phone'];
        $uplineid = $data['uplineid'];
        $uplineisAgent = $data['uplineisagent'];
        $uplineisAdmin = $data['uplineisadmin'];

        $selectId = selects("*", "tra", "tid = '$tid' AND tuid = '$uid' AND tcat = '16' AND tstatus = '0'", 1);

        if (!$selectId['res']) {
            notify(1, "Loan Request Not Found", 404, 1);
            return sendJsonResponse(404);
        }

        if ($selectId['res']) {
            $data = $selectId['qry'][0];
            $convamount = conv($crate, $data['tamount'], true, true);
            $amount = $data['tamount'];
            $status = $data['tstatus'];
            $date = date("Y-m-d", strtotime($data['tdate']));
            $curdate = date("Y-m-d", strtotime($today));

            $grabrates = selects("*", "pac", "pid = 'SDRGVET'", 1);

            if (!$grabrates['res']) {
                notify(1, "An Error Occured on Our end please Contact Customer care", 1, 1);

                sendJsonResponse(500);
            }

            if ($date >= $curdate) {
                notify(0, "Your Loan Will Be available After 24-HRS Kind Regards", 1, 1);
                sendJsonResponse(403);
            }

            $charges = $grabrates['qry'][0]['rate'];
            $packageName = $grabrates['qry'][0]['pname'];
            $packageCategory = $grabrates['qry'][0]['pcategory'];

            $todeduct = $charges * $amount;

            $profit = $grabrates['qry'][0]['profit'];
            $profits = $grabrates['qry'][0]['profit'];

            $agentProfit = $todeduct * ($profit / 100);


            if ($deposit >= $todeduct || $isagent || $isagent) {
                if (!$isagent) {
                    $deductfunds = updates("bal", "deposit = deposit - '$todeduct'", "buid = '$uid'")['res'];
                } else {
                    $deductfunds = true;
                }

                if ($deductfunds) {
                    notify(2, "Loan Withdrawal Processed Successfully Please Await Mpesa Notification", 200, 1);
                    updates("tra", "tstatus = 2", "tid = '$tid'");

                    if ($uplineid && !$isagent) {
                        $updateUplineAccount = updates("bal", "balance = balance + '$agentProfit', profit = profit + '$agentProfit'", "buid = '$uplineid'");

                        if ($updateUplineAccount['res']) {

                            $uplineDetails = others($uplineid);

                            if ($uplineDetails['res']) {
                                $newData = $uplineDetails['query']['data'];
                                $newBal = $uplineDetails['query']['bal'];

                                $upline = $newData['uname'];
                                $uplineEmail = $newData['email'];
                                $uplineRate = $newData['rate'];
                                $uplineCcurrency = $newData['ccurrency'];

                                $preforbalance = $newBal['balance'];
                                $prefordeposit = $newBal['deposit'];

                                $curforbalance = $newBal['balance'] + $agentProfit;
                                $curfordeposit = $newBal['deposit'];

                                $tratoken = gencheck("tra", 8);

                                insertstrans(
                                    $tratoken,
                                    $uplineid,
                                    $upline,
                                    $phone,
                                    "Received $profits% Bonus On $packageName",
                                    "2",
                                    "NONE",
                                    `NULL`,
                                    $agentProfit,
                                    '2',
                                    $preforbalance,
                                    $curforbalance,
                                    $prefordeposit,
                                    $curfordeposit,
                                    $today,
                                    $today,
                                    $uname,
                                    $uid,
                                    2
                                );

                                $agentProfit = $uplineCcurrency . " " . conv($uplineRate, $agentProfit, true, true);
                                $uplineMsg = "
                        Congratulations, Agent <strong>$upline</strong>! Your hard work with $company 
                        has earned you a Fantastic $profits% profit on the $packageCategory, worth $agentProfit!
                        Keep promoting our brand for a chance to win big Cashbacks in the next round.
                        Let's aim higher and achieve even greater success!
                        ";

                                sendMail($upline, $uplineEmail, $uplineMsg, ["Received $profits%  Profit", "Earned $agentProfit Bonus On $packageName"]);
                            }
                        }
                    }
                    sendJsonResponse(200);
                }
            } else {
                $todeduct = conv($crate, $todeduct, true, true);
                notify(0, "To complete your withdrawal of $convamount, an interest fee of $todeduct will be deducted from your deposit balance. Please recharge your account to proceed with the transfer to your M-Pesa.", 0, 1);
                sendJsonResponse(403);
            }
        }
    }
}


function grabloandetails()
{
    if (sessioned()) {

        $uid = $_SESSION['suid'];

        $data = $_SESSION['query']['data'];
        $bal = $_SESSION['query']['bal'];

        $jsonloandat  = $data['nid'];
        $loandata = json_decode($jsonloandat, true);

        if (!$loandata) {
            notify(1, "No Loan Details Found", 404, 1);
            return sendJsonResponse(404);
        }

        $response = [
            'res' => true,
            'data' => [
                'name' => $loandata['name'] ?? '',
                'nid' => $loandata['nid'] ?? '',
                'email' => $loandata['email'] ?? '',
                'phonenumber' => $loandata['phonenumber'] ?? '',
            ]
        ];

        sendJsonResponse(200, true, null, $response['data']);
    }
}


function cashbackads()
{
    if (sessioned()) {
        $data = $_SESSION['query']['data'];
        $accrate = $data['rate'];


        $ccurency = $data['ccurrency'];
        $packagq = selects("*", "pac", "pcategory IN ('Packages') AND pstatus = true ORDER BY pprice ASC", 1);
        $response = [
            "header" =>  "🎉 " .  date("l") . " Giveaway Cashback! 🎄 Only at MetaWave",
            "footer" =>  "📲 Fast payouts via M-Pesa 💼 Powered by MetaWave",
        ];

        if ($packagq['res']) {
            for ($i = 0; $i < count($packagq['qry']); $i++) {

                $response[$packagq['qry'][$i]['pcategory']][] = [
                    'package_name' => $packagq['qry'][$i]['pname'],
                    'price' => conv($accrate, $packagq['qry'][$i]['pprice'], true, true),
                    'award_price' => conv($accrate, $packagq['qry'][$i]['pprice'] * 3, true, true),
                    'currency' => $ccurency,
                ];
            }
        }
        return sendJsonResponse(200, true, null, $response);
    }
}
