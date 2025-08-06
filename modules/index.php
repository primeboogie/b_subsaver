<?php
require "whatsapp/whatsapp.php";




function unauthorized($action)
{
    switch ($action) {
        case 'login':
        case 'auth':
        case 'freeuser':
        case 'freeemail':
        case 'freephone':
        case 'register':
        case 'newpasswords':
        case 'populateCountrys':
        case 'mustrequest':

            fne($action);
            break;
        default:
            authorized($action);
    }
}

function authorized($action)
{
    if (auths()['status']) {
        switch ($action) {
            case 'adminwithdrawals':
            case 'alluser':
            case 'versionupdate':
            case 'admindeposit':
            case 'adminSummary':
            case 'adminTopEarners':
                // case 'adminupdate':
                // case 'updatetrans':
            case 'newProduct';


            case 'buyPackage':
            case 'agentApproval':
            case 'populatepackage':
            case 'transferfunds':
            case 'submitProduct':
            case 'autoLogin':
            case 'userdata':
            case 'myDownlines':
            case 'editWhatsapp':
            case 'editagent':
            case 'updatecurrency':
            case 'deposithistory':
            case 'stkpush':
            case 'claimCashback':
            case 'updatepassword':
            case 'agentClaim':
            case 'systemwithdrawal':
            case 'withdrawalhistory':
            case 'allTransactions':
            case 'uploadsHistory':
            case 'upgradeAccount':
            case 'freezeAccount':
            case 'deleteAccount':
            case 'runloanactivation':
            case 'registerloan':
            case 'requestloanactivation':
            case 'requestLoan':
            case 'allloanrequest':
            case 'loanwithdraw':
            case 'grabloandetails':
            case 'cashbackads':
                fne($action);
        }
    }
}
