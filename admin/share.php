<?php

require_once "config/func.php";


function myDownlines($level = null){
    if(sessioned() && isset($_GET['level'])){
        global $conn;

        $level = mytrim($_GET['level']);
        $uid = $_SESSION['suid'];
        $uname = $_SESSION['query']['data']['uname'];
        $crate = $_SESSION['query']['data']['rate'];
        $ccurency = $_SESSION['query']['data']['ccurrency'];

        $response = [];
        $response['active'] = 0;
        $response['inactive'] = 0;
        $response['total'] = 0;
        $response['Earned'] = 0;
        $response['Currency'] = $ccurency;
        $response['data'] = [];

        
        $belowl1 = false;
        
        if($level == 1){
            $where = "l1 = '$uname'";
            $l = 'fl1';
            $response['Level'] = 'Level 1';
            
        // }elseif($level == 2){
        //     $where = "l2 = '$uname'";
        //     $l = 'fl2';
        //     $belowl1 = true;
        //     $response['Level'] = 'Level 2';
            
        // }elseif($level == 3){
        //     $where = "l3 = '$uname'";
        //     $l = 'fl3';
        //     $belowl1 = true;
        //     $response['Level'] = 'Level 3';
        }else{
            return sendJsonResponse(422,false,"Missing Routes");
        }

        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, e.active AS feeactive, u.l1 AS upline FROM users u 
        INNER JOIN balances b 
        ON u.uid = b.buid 
        INNER JOIN countrys c 
        ON u.ucountryid = c.cid 
        LEFT JOIN affiliatefee e
        ON u.default_currency = e.cid 
        WHERE $where AND u.sysdev = true";
    // AND u.active = true
        $dataquery = mysqli_query($conn,  $dataq);

        if($dataquery)
        {
            $num = mysqli_num_rows($dataquery);
            if ($num > 0) {

                    $i = 1;
                    while($grab = mysqli_fetch_array($dataquery))
                    {
                        if($grab['useractive'] == 0){
                            $state = "Suspended";
                            $status = 3;
                            
                        }elseif($grab['ustatus'] == 2){
                            $state = "Active";
                            $response['Earned'] += $grab[$l];
                            $status = 2;
                            
                        }elseif($grab['ustatus'] == 1){
                            $state = "Dormant";
                            $status = 1;
                            
                        }elseif($grab['ustatus'] == 0){
                            $state = "Pending";
                            $status = 0;
                        }else{
                            $state = "Dormant";
                            $status = 1;
                        }

                        if($status == 2){
                            $response['active'] += 1;
                        }else{
                            $response['inactive'] += 1;
                        }

                        // $earned = $status == 2 ? conv($crate,$grab[$l],true) : 0;
                        
                        $dataEntry = [
                            "RandId" => $grab['randid'],
                            "Name" => $grab['uname'],
                            "Phone" => $grab['uphone'],
                            "Email" => $grab['uemail'],
                            "Status" => $status,
                            // "Activated" => $grab['accactive'],
                            "Country" => $grab['cname'],
                            
                            // "Earned" => $ccurency . " " . $earned, 
                            // "L1-Fixed" => $grab[$l], 
                            // "L1-converted" => conv($crate,$grab[$l],true), 
                            // "Upline" => $grab['l1'],
                            "Deposited" => $ccurency . " ". conv($crate,$grab['deposit'],true),
                            "Whatsapp" => $ccurency . " ". conv($crate,$grab['whatsapp'],true),
                            "Joined" => $grab['ujoin'],
                        ]; 
                            // if($belowl1){
                            //     $dataEntry["Upline"] = $grab['l1'];
                            // }
                        $response['data'][] = $dataEntry;
                    }

                }
                $response['Earned'] = conv($crate,$response['Earned'],true);
                $response['money'] = $response['Earned'];
                $response['total']  = $num;
                return  sendJsonResponse(200,true,null,$response);
        }else{
            $array['qry']['data'] = mysqli_error($conn);
            notify(1,"Hi Admin Sorry We had an An Issue Collecting Your Records Try Again Later" . mysqli_error($conn),400,3);
            notify(1,"Hi $uname An Error Occured Please Try Again Later Kind Regards",500,1);
            return sendJsonResponse(404);
        }
    }else{
        return sendJsonResponse(422,false,"Missing Routes");
    }
}


function alluser(){
    
    
    if(sessioned()){
        $response = [];

        // $tstatus = jDecode()['tstatus'] ?? 2;
    
        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        
    
        $uid = $_SESSION['suid'];
    
        $uname = $data['uname'];
        $crate = $data['rate'];
        $accccurrency = $data['ccurrency'];
    
        $isAdmin = $data['isadmin'];
    
        if(!$isAdmin){
            notify(1,"You Are Not Authorized To Access This Feature",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.",400,2);
            return sendJsonResponse(statusCode: 401);
        }
    

        $dataq = "SELECT u.*, u.active AS useractive,b.*, c.*, e.*, c.cid AS CID, e.active AS feeactive, u.l1 AS upline FROM users u 
        INNER JOIN balances b 
        ON u.uid = b.buid 
        INNER JOIN countrys c 
        ON u.ucountryid = c.cid 
        LEFT JOIN affiliatefee e
        ON u.default_currency = e.cid AND e.active = true
        WHERE u.sysdev = true
        ";
    
        $dataquery = comboselects($dataq,1);  
        
        if($dataquery['res']){

            $i = 1;
            foreach ($dataquery['qry'] as $data){

                $status = $data['ustatus'] == 2 ? 2 : ($data['ustatus'] == 1 ? 1 : 0);

                if ($data['useractive'] != 1) {
                    $status = 3;
                }

                $userdata = [
                    'No' => $i++,
                    'Name' => $data['uname'],
                    'Email' => $data['uemail'],
                    'Phone' => $data['uphone'],
                    'Status' => $status,
                    'upline' => $data['upline'],
                    'active' => $data['useractive'],
                    'Country' => $data['cname'],
                    'rate' => $data['crate'],
                    'isAgent' => $data['isagent'] == 1 ? true : false,
                    'Balance' => floatval($data['balance']),
                    'Deposited' => floatval($data['deposit']),
                    'Whatsapp' => floatval($data['whatsapp']),
                    'Joined' => $data['ujoin'],
                    'cid' => $data['CID'],
                    'RandId' => $data['randid'],
                    'uid' => $data['uid'],
                ];

                $response[] = $userdata;
            }

        } else {
            sendJsonResponse(404);
        }
   
    
    return sendJsonResponse(200, true, null, $response);

        }
}


function autoLogin(){
    if(sessioned()){

        $inputs = jDecode(['reusername']);

        $reusername = $inputs['reusername'];


        $confirmdownline = others($reusername);

        if(!$confirmdownline['res']){
            notify(0,"Access Not Available",500,1);
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
        $downlineIsAdmin = $newData['isadmin'];
      

        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        if($downlineIsAdmin && !$isAdmin){
            notify(1,"Action could Not Be Completed Please Contact Your CEO",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Login to $downlineUname Account.",400,2);
            return sendJsonResponse(statusCode: 401);
        }


        if($isAgent || $isAdmin){

            $stoken = generatetoken(102);
            $ssid = gencheck("ses");
    
            $thirtyMinutes = date("Y-m-d H:i:s", strtotime("+1 days"));
    
            $session = inserts("ses","sid,suid,stoken,sexpiry",['ssss',$ssid,$downlineId,$stoken,$thirtyMinutes]);
            if($session){
                $msg = "You Have Now Access to $downlineUname";
                notify(2,$msg,200, 1);
                $newmsg = "One Momemt As we Grab All The Information";
                notify(2,$newmsg,200, 1);
                $tosend = [
                 'access_token' => $stoken,   
                 'is_admin' => $isAdmin,   
                ];

                return sendJsonResponse(200,true, null,$tosend);
            }
        } else {
            notify(1,"You Are Not Authorized To Access This Page",1,1);
            return sendJsonResponse(statusCode: 401);
        }
        
    }
}


function upgradeAccount(){
    if(sessioned()){

        $inputs = jDecode(['reusername']);

        $reusername = $inputs['reusername'];


        $confirmdownline = others($reusername);

        if(!$confirmdownline['res']){
            notify(0,"Access Not Available",500,1);
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
        $downlineIsAdmin = $newData['isadmin'];
      
        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        if($downlineIsAdmin && !$isAdmin){
            notify(1,"Action could Not Be Completed Please Contact Your CEO",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Upgrade $downlineUname .",400,2);
            return sendJsonResponse(statusCode: 401);
        }


        if($isAgent || $isAdmin){

            $upgradeAccount = updates("use","l1 = 'CEO'","uid = '$downlineId'");
            
            if($upgradeAccount){
                $newmsg = "Successfully $downlineUname Upgraded Account to CEO";
                notify(2,$newmsg,200, 1);

                return sendJsonResponse(200);
            }
        } else {
            notify(1,"You Are Not Authorized To Access This Feature",1,1);
            return sendJsonResponse(statusCode: 401);
        }
        
    }
}




function freezeAccount(){
    if(sessioned()){

        $inputs = jDecode(['reusername']);

        $reusername = $inputs['reusername'];


        $confirmdownline = selects("*","use","uname = '$reusername' LIMIT 1",1);

        if(!$confirmdownline['res']){
            notify(0,"Access Not Available",500,1);
            return sendJsonResponse(404);
        }

        $newData = $confirmdownline['qry'][0];
        $downlineId = $confirmdownline['qry'][0]['uid'];
        
        $downlineUname = $newData['uname'];
        $downlineIsAdmin = $newData['isadmin'] == 1 ? true : false;
      
        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        if($downlineIsAdmin && !$isAdmin){
            notify(1,"Action could Not Be Completed Please Contact Your CEO",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Freeze $downlineUname .",400,2);
            return sendJsonResponse(statusCode: 401);
        }

        if(!$isAdmin){
            notify(1,"You Are Not Authorized To Access This Page",1,1);
            return sendJsonResponse(statusCode: 401);
        }



        if($isAdmin){

            $upgradeAccount = updates("use","active = !active","uid = '$downlineId'");
            
            if($upgradeAccount){
                $newmsg = "Changes Made Successfully";
                notify(2,$newmsg,200, 1);

                return sendJsonResponse(200);
            }
        } else {
            notify(1,"You Are Not Authorized To Access This Feature",1,1);
            return sendJsonResponse(statusCode: 401);
        }
        
    }
}



function deleteAccount(){
    if(sessioned()){

        $inputs = jDecode(['reusername']);

        $reusername = $inputs['reusername'];


        $confirmdownline = selects("*","use","uname = '$reusername' LIMIT 1",1);

        if(!$confirmdownline['res']){
            notify(0,"Access Not Available",500,1);
            return sendJsonResponse(404);
        }

        $newData = $confirmdownline['qry'][0];
        $downlineId = $confirmdownline['qry'][0]['uid'];
        
        $downlineUname = $newData['uname'];
        $downlineIsAdmin = $newData['isadmin'] == 1 ? true : false;
      
        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $uemail = $data['email'];
        $phone = $data['phone'];
        $isAgent = $data['isagent'];

        $isAdmin = $data['isadmin'];

        if($downlineIsAdmin && !$isAdmin){
            notify(1,"Action could Not Be Completed Please Contact Your CEO",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Freeze $downlineUname .",400,2);
            return sendJsonResponse(statusCode: 401);
        }



        if($isAdmin){
            $ran =generatetoken(2,true);
            $dormant = "-Dormant-$ran";
            $upgradeAccount = updates("use","uname = CONCAT(uname, '$dormant'), uemail = CONCAT(uemail, '$dormant'), uphone = CONCAT(uphone, '$dormant'), l1 = 'SYSTEMID', sysdev = false , active = false","uid = '$downlineId'");
            
            if($upgradeAccount){
                $newmsg = "Account Deleted Successfully";
                notify(2,$newmsg,200, 1);

                return sendJsonResponse(200);
            }
        } else {
            notify(1,"You Are Not Authorized To Access This Feature",1,1);
            return sendJsonResponse(statusCode: 401);
        }
        
    }
}

function adminSummary(){
    if(sessioned()){

        $curdate = date("Y-m-d");

        $response = [
            "today_deposit" => 0,
            "today_withdrawal" => 0,

            "weekly_deposit" => 0,
            "weekly_withdrawal" => 0,

            "today_users" => 0,

            "total_users" => 0,
            "active_users" => 0,

            "deposit_records" => [
            ["date" => date("Y-m-d", strtotime("0 days")),"Deposit" => 0],
            ["date" => date("Y-m-d", strtotime("-1 day")),"Deposit" => 0],
            ["date" => date("Y-m-d", strtotime("-2 days")),"Deposit" => 0],
        ],

        ];

        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        

        $uid = $_SESSION['suid'];

        $uname = $data['uname'];
        $crate = $data['rate'];

        $isAdmin = $data['isadmin'];

        if(!$isAdmin){
            notify(1,"Please Contact Your Upline You Are Not Authorized To Access This Feature",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Acces Your Admin Panel.",400,2);
            return sendJsonResponse(statusCode: 401);
        }

        $seveddays = date("Y-m-d", strtotime("- 6 days"));

        $grabRecords = comboselects("
                        SELECT DATE(tdate) AS date, SUM(tamount) AS Deposit
                        FROM transactions
                        WHERE tdate > '$seveddays' AND tcat = 7 AND tstatus = 2
                        GROUP BY DATE(tdate)
                        ORDER BY tdate DESC;
                        ",1);


        

        $response['deposit_records'] = $grabRecords['res'] ? $grabRecords['qry'] : $response['deposit_records'];
        $response['today_deposit'] = conv($crate,selects("SUM(tamount)","tra","tcat = '7' AND tstatus = '2' AND tdate like '%$curdate%'",1)['qry'][0][0] ?? 1, true, true);
        $response['today_withdrawal'] = conv($crate,selects("SUM(tamount)","tra","tcat = '3' AND tdate like '%$curdate%'",1)['qry'][0][0] ?? 1, true, true);
        
        $monday = date('Y-m-d', strtotime('Monday this week'));
        $sunday = date('Y-m-d', strtotime('Sunday this week'));


        $response['weekly_deposit'] = conv($crate,selects("SUM(tamount)","tra","tcat = '7' AND tstatus = '2' AND tdate >= '$monday' AND tdate <= '$sunday'",2)['qry'][0][0] ?? "1",true, true);
        $response['weekly_withdrawal'] = conv($crate,selects("SUM(tamount)","tra","tcat = '3' AND tdate >= '$monday' AND tdate <= '$sunday'",2)['qry'][0][0] ?? "1",true, true);
        

        $response['today_users'] = selects("COUNT(*)","use","ujoin like '%$curdate%'",1)['qry'][0][0] ?? 0;
        $response['total_users'] = floatval(selects("COUNT(*)","use","sysdev = true",1)['qry'][0][0] ?? 0);
        $response['active_users'] = floatval(selects("COUNT(*)","use"," ustatus = 2 AND sysdev = true",1)['qry'][0][0] ?? 0);
        sendJsonResponse(200,true,null,$response);
    }
}

function adminwithdrawals(){

    if(sessioned()){

    // $tstatus = jDecode()['tstatus'] ?? 2;

    $data = $_SESSION['query']['data']; 
    $bal = $_SESSION['query']['bal']; 
    

    $uid = $_SESSION['suid'];

    $uname = $data['uname'];
    $crate = $data['rate'];
    $accccurrency = $data['ccurrency'];

    $isAdmin = $data['isadmin'];

    if(!$isAdmin){
        notify(1,"You Are Not Authorized To Access This Feature",1,1);
        updates("use","active = false ", "uid IN ('$uid')");
        notify(2,"Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.",400,2);
        return sendJsonResponse(statusCode: 401);
    }

    $fourdays = date("Y-m-d", strtotime("- 4 days"));

    $response = [];

    $query = "SELECT t.*, u.*, c.* FROM transactions t
    LEFT JOIN users u
    ON t.tuid = u.uid
    LEFT JOIN countrys c
    ON u.ucountryid = c.cid
    WHERE tstatus = '2' AND tdate > '$fourdays' AND tcat = '3' ORDER BY tdate DESC";

$dataquery = comboselects($query,1);  
        
if($dataquery['res']){
    $i = 1;
    foreach ($dataquery['qry'] as $data){
        
        $userdata = [
            'No' => $i++,
            'Name' => $data['uname'],
            'Amount' =>  "KES  " . conv($crate,$data['tamount'], false, true),
            'Foreign' => $data['ccurrency'] . " " . conv($data['crate'],$data['tamount'],false, true),
            'Phone' => $data['ccall']."-".$data['uphone'],
            'Date' =>  date("d-M-y H:i:s A", timestamp: strtotime($data['tdate'])),
            'Status' => $data['tstatus'],
            'Country' => $data['cname'],
            'Pre-Balance' =>  "KES  " . conv($crate,$data['tprebalance'], false, true),
            'Current-Balance' =>  "KES  " . conv($crate,$data['tbalance'], false, true),
            'tid' => $data['tid'],
        ];

        $response[] = $userdata;
    }

} else {
    sendJsonResponse(404);
}



return sendJsonResponse(200, true, null, $response);

    }
}



function admindeposit(){

    if(sessioned()){

        // $tstatus = jDecode()['tstatus'] ?? 2;
    
        $data = $_SESSION['query']['data']; 
        
    
        $uid = $_SESSION['suid'];
    
        $uname = $data['uname'];
        $crate = $data['rate'];
    
        $isAdmin = $data['isadmin'];

        

        if(!$isAdmin){
            notify(1,"You Are Not Authorized To Access This Feature",1,1);
            updates("use","active = false ", "uid IN ('$uid')");
            notify(2,"Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.",400,2);
            return sendJsonResponse(statusCode: 401);
        }


    // $tstatus = jDecode()['tstatus'] ?? 2;

    $fourdays = date("Y-m-d", strtotime("- 4 days"));

    $response = [];

    $query = "SELECT t.*, u.*, c.* FROM transactions t
    LEFT JOIN users u
    ON t.tuid = u.uid
    LEFT JOIN countrys c
    ON u.ucountryid = c.cid
    WHERE tstatus IN  ('2','0') AND tdate > '$fourdays' AND tcat = '7' ORDER BY tdate DESC";

$dataquery = comboselects($query,1);  
        
if($dataquery['res']){
    $i = 1;
    foreach ($dataquery['qry'] as $data){

        $userdata = [
            'No' => $i++,
            'Name' => $data['uname'],
            'Amount' =>  "KES  " . conv($crate,$data['tamount'], false, true),
            'Foreign' => $data['ccurrency'] . " " . conv($data['crate'],$data['tamount'],true),
            'Phone' => $data['ccall']."-".$data['uphone'],
            'Date' => date("d-M-y H:i:s A", timestamp: strtotime($data['tdate'])),
            'Country' => $data['cname'],
            'Status' => $data['tstatus'],
            'Pre-Balance' =>  "KES  " . conv($crate,$data['tprebalance'], false, true),
            'Current-Balance' =>  "KES  " . conv($crate,$data['tbalance'], false, true),
            'tid' => $data['tid'],
        ];

        $response[] = $userdata;
    }

} else {
    sendJsonResponse(404);
}



return sendJsonResponse(200, true, null, $response);

    }
}

function adminTopEarners(){

    if(sessioned()){

        // $tstatus = jDecode()['tstatus'] ?? 2;
    
        $data = $_SESSION['query']['data']; 
        $bal = $_SESSION['query']['bal']; 
        
    
        $uid = $_SESSION['suid'];
    
        $uname = $data['uname'];
        $crate = $data['rate'];
    
        $isAdmin = $data['isadmin'];

        
    if(!$isAdmin){
        notify(1,"You Are Not Authorized To Access This Feature",1,1);
        updates("use","active = false ", "uid IN ('$uid')");
        notify(2,"Agent => $uname Has Been Suspended Because He Tried To Acces One Of The Admin Account.",400,2);
        return sendJsonResponse(statusCode: 401);
    }


    $query = "SELECT u.uname AS Username, u.randid, b.balance, SUM(t.tamount) AS totalWithdrawal
            FROM users u
            INNER JOIN balances b
            ON b.buid = u.uid
            RIGHT JOIN transactions t
            ON u.uid = t.tuid
            WHERE u.isagent = true AND t.tcat = 3 AND t.tamount > 1000
            GROUP BY u.uid
            ORDER BY totalWithdrawal DESC
            ";

        $dataquery = comboselects($query,1);  

        if(!$dataquery['res']){
            sendJsonResponse(404);
        }

        foreach ($dataquery['qry'] as $row) {
            $question = [
                'Id' => $row['randid'],
                'Name' => $row['Username'],
                'Balance' =>  "KES  " .conv($crate,$row['balance'], false, true),
                'TotalWithdrawal' =>  "KES  " .conv($crate,$row['totalWithdrawal'], false, true),
            ];
            $response[] = $question;
        }


        sendJsonResponse(200,true,null,$response);

    }
}


