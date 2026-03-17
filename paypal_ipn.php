<?php

define('IN_GAMECP_SALT58585', true);

# Include Main Files
include('./gamecp_common.php');

# Set our res cehckeer
$res_status = true;

# Some variables needed for logging
$time = date('F j Y G:i');
$timestamp = time();

# Get requires 'post' command from PayPals systems
$req = 'cmd=_notify-validate';

# PayPal Security Check Variables
foreach ($_POST as $key => $value) {
    $value = urlencode(stripslashes($value));
    $req .= "&$key=$value";
}

# Enable/disable sandbox
$sandbox = false;

# Config paypal
$domain = ($sandbox) ? "www.sandbox.paypal.com" : "www.paypal.com";
$port = (isset($config['paypal_ssl_connection']) && $config['paypal_ssl_connection'] == 1) ? 443 : 80;
$sock_domain = (isset($config['paypal_ssl_connection']) && $config['paypal_ssl_connection'] == 1) ? 'ssl://' . $domain : $domain;

# Setup header
$header = "POST /cgi-bin/webscr HTTP/1.1" . "\r\n";
$header .= "Host: ${domain}:${port}\r\n";
$header .= "Content-type: application/x-www-form-urlencoded" . "\r\n";
$header .= "Content-length: " . strlen($req) . "\r\n";
$header .= "Connection: close" . "\r\n\r\n";

// Open a socket to paypal.com, main communication
$fp = fsockopen($sock_domain, $port, $errno, $errstr, 30);

# Gather variables needed
$txn_id = (isset($_POST['txn_id'])) ? antiject($_POST['txn_id']) : '';
$payer_email = (isset($_POST['payer_email'])) ? antiject($_POST['payer_email']) : '';
$payer_id = (isset($_POST['payer_id'])) ? antiject($_POST['payer_id']) : '';
$business_email = (isset($_POST['business'])) ? antiject($_POST['business']) : '';
$custom = (isset($_POST['custom'])) ? antiject($_POST['custom']) : '';

$payment_fee = (isset($_POST['mc_fee'])) ? $_POST['mc_fee'] : 0;
$payment_gross = (isset($_POST['mc_gross'])) ? $_POST['mc_gross'] : 0;

$payment_status = (isset($_POST['payment_status'])) ? antiject($_POST['payment_status']) : '';

// Get user name!
connectuserdb();
$user_info_sql = "SELECT convert(varchar,id) AS AccountName FROM tbl_UserAccount WHERE Serial = '" . $custom . "'";
if (!($user_info_result = mssql_query($user_info_sql))) {
    // Always log this in the admin logs!
    gamecp_log(5, $custom, "PAYPAL - ERROR - Unable to find or query this user id");
    exit;
}
$user = mssql_fetch_array($user_info_result);

if ($user['AccountName'] != '') {
    $user_name = antiject($user['AccountName']);
} else {
    $user_name = $custom;
    gamecp_log(5, $custom, "PAYPAL - ERROR - Could not look up account serial supplied by PayPal: $custom");
    gamecp_log(5, $custom, "PAYPAL - ERROR - Did not credit TXN ID: $txn_id | Payer ID: $payer_id | Payer Email: $payer_email");
    exit;
}

# Check to see if we have a connection
# If so, retrieve data
if (!$fp) {

    $log_message = "PAYPAL - Unable to connect to www.paypal.com | Err #: $errno | Err: $errstr";
    gamecp_log(3, $user_name, $log_message);
    fclose($fp);

} else {

    $log_message = "PAYPAL - LOADED PAYPAL.COM";
    gamecp_log(0, $user_name, $log_message);

    // Get information from Paypal
    fputs($fp, $header . $req);
    $load_res = '';
    while (!feof($fp)) {
        $res = fgets($fp, 1024);
        $load_res = $load_res . $res;
        if (false != preg_match("/VERIFIED/", $res)) {
            $res_status = 'verified';
            break;
        } elseif (false != preg_match("/INVALID/", $res)) {
            $res_status = 'invalid';
            break;
        } else {
            $res_status = false;
        }
    }
    fclose($fp);

    if ($res_status == 'verified') {

        $credits = calculate_credits($config['donations_credit_muntiplier'], $config['donations_number_of_pay_options'], $config['donations_start_price'], $config['donations_start_credits'], $payment_gross);

        if (!isset($credits) or $credits == '') {
            $credits = 0;
        }

        // check the payment_status is Completed
        // check that txn_id has not been previously processed
        // check that receiver_email is your Primary PayPal email
        // check that payment_amount/payment_currency are correct
        // process payment

        if ($payment_status == 'Completed') {

            // Begin doing check with the database
            connectgamecpdb();

            // Check if any hacking attemp is taking place
            if ($business_email == $config['paypal_email']) {

                // Query for duplicate TXN_IDS
                $tnx_query = mssql_query('SELECT tranid FROM gamecp_paypal WHERE tranid="' . $txn_id . '"');

                // Now the check to see if we got results, if not-- success!
                if (mssql_num_rows($tnx_query) == 0) {

                    // Insert into PAYPAL for payment logs
                    $paypal_query = 'INSERT INTO gamecp_paypal (tranid, amount, fee, userid, name, credits, time, payerid, payeremail, verified) VALUES ("' . $txn_id . '", "' . $payment_gross . '", "' . $payment_fee . '", "' . $custom . '", "' . $custom . '", "' . $credits . '", "' . $time . '", "' . $payer_id . '", "' . $payer_email . '", "1")';
                    mssql_query($paypal_query);

                    // Always log this in the admin logs!
                    gamecp_log(0, $user_name, "PAYPAL - SUCCESSFUL PAYMENT - TXN ID: $txn_id | Amount: $" . $payment_gross);

                    // Okay, now we need to check to see if we do a 'insert' or an 'update'
                    $totalusers_query = mssql_query('SELECT user_points FROM gamecp_gamepoints WHERE user_account_id="' . trim($custom) . '"');

                    // 0 means insert, else update
                    if (mssql_num_rows($totalusers_query) == 0) {
                        // Create a new row, add credits
                        $credits_in = 'INSERT INTO gamecp_gamepoints (user_account_id, user_points) VALUES ("' . $custom . '", "' . $credits . '")';
                        mssql_query($credits_in);

                        // Always log this in the admin logs!
                        gamecp_log(0, $user_name, "PAYPAL - ADDED CREDITS - INSERT - TXN ID: $txn_id | Credits: $credits");
                    } else {
                        // Update the current row with the new credits :D
                        $credits_in = 'UPDATE gamecp_gamepoints SET user_points=user_points+' . $credits . ' WHERE user_account_id="' . $custom . '"';
                        mssql_query($credits_in);

                        // Always log this in the admin logs!
                        gamecp_log(0, $user_name, "PAYPAL - ADDED CREDITS - UPDATE - TXN ID: $txn_id | Credits: $credits");
                    }

                } else {

                    // Recommend this log as 'highly suspecious', level 5 log
                    $log_message = "PAYPAL - DUPLICATE TXN ID - TXN ID: $txn_id | PAYPAL EMAIL: $payer_email | STATUS: $payment_status";
                    gamecp_log(5, $user_name, $log_message);

                }

            } else {

                // Recommend this log as 'highly suspecious', level 5 log
                $log_message = "PAYPAL - INVALID BUSINESS - TXN ID: $txn_id | PAYPAL EMAIL: $payer_email | STATUS: $payment_status | Business: $business_email";
                gamecp_log(5, $user_name, $log_message);

            }

        } else {

            if ($payment_status == 'Reversed') {
                // Recommend this log as 'suspecious', level 5 log
                $log_message = "PAYPAL - <b>REVERSED</b> - TXN ID: $txn_id | PAYPAL EMAIL: $payer_email | Business: $business_email";
                gamecp_log(5, $user_name, $log_message);
                $ban_reason = "Auto - PayPal Reversal";
                $ban_period = 119988;

                connectuserdb();
                $insert_sql = "INSERT INTO tbl_UserBan (nAccountSerial, nPeriod, nKind, szReason, GMWriter) VALUES ('$custom', '$ban_period', '0', '$ban_reason','" . $user_name . "')";
                if (!($insert_result = @mssql_query($insert_sql))) {
                    #$out .= '<p style="text-align: center; font-weight: bold;">This account has already been banned</p>';
                } else {
                    gamecp_log(5, $user_name, "PAYPAL - <b>BANNED</b> - Automatic ban for payment reversal", 1);
                }
            } elseif ($payment_status == 'Canceled_Reversal') {
                // Recommend this log as 'suspecious', level 5 log
                $log_message = "PAYPAL - <b>CANCELED REVERSAL</b> - TXN ID: $txn_id | PAYPAL EMAIL: $payer_email | Business: $business_email";
                gamecp_log(5, $user_name, $log_message);
            } else {
                // Recommend this log as 'suspecious', level 4 log
                $log_message = "PAYPAL - <b>INCOMPLETE</b> - TXN ID: $txn_id | PAYPAL EMAIL: $payer_email | STATUS: $payment_status | Business: $business_email";
                gamecp_log(4, $user_name, $log_message);
            }

        }

    } elseif ($res_status == 'invalid') {

        // Log the unknown error
        $log_message = "PAYPAL - PAYMENT INVALID - PAYER ID: $payer_id | PAYPAL EMAIL: $payer_email - " . $res;
        gamecp_log(5, $user_name, $log_message);

    } else {

        // Log the unknown error
        $log_message = "PAYPAL - PAYMENT FAILED - Unknown Error - " . $load_res;
        gamecp_log(1, $user_name, $log_message);

    }


}
?>