<?php
require "subsaver/subsaver.php";




function unauthorized($action)
{
    switch ($action) {
        case 'purchase':

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
            // case 'adminupdate':
            // case 'updatetrans':
            case 'newProduct';


                fne($action);
        }
    }
}
