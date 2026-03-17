<?php

if (!defined("IN_GAMECP_SALT58585")) {
    die("Hacking Attempt");
}

# Set content header
header('Content-Type: text/html; charset=utf-8');

# Define common initaited
const COMMON_INITIATED = true;

# Required globally
$base_path = dirname(__FILE__);

#set timezone
date_default_timezone_set("Asia/Jakarta");

# Fast, set this up now!
function quick_msg($message, $type = 'error')
{
?>

    <head>
        <title>ERROR</title>
        <style type="text/css">
            /* Default styles for all quick messages */
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #1a1a1a;
                color: #e0e0e0;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }

            a {
                color: #4CAF50;
                text-decoration: none;
                transition: color 0.3s ease;
            }

            a:hover {
                color: #81C784;
            }

            .message-container {
                border-radius: 12px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
                max-width: 600px;
                width: 90%;
            }

            h2 {
                font-size: 2.5em;
                margin-top: 0;
                margin-bottom: 20px;
                font-weight: bold;
            }

            p {
                font-size: 1.1em;
                line-height: 1.6;
            }

            /* Specific styles for different message types */
            .info {
                background-color: #2c3e50;
                border: 2px solid #3498db;
            }

            .success {
                background-color: #1d401e;
                border: 2px solid #2ecc71;
            }

            .warning {
                background-color: #4c3f15;
                border: 2px solid #f1c40f;
            }

            .error {
                background-color: #4d2424;
                border: 2px solid #e74c3c;
            }

            /* Modern gaming style for license expired */
            .license-expired-container {
                background: #111;
                border: 2px solid;
                border-image: linear-gradient(45deg, #FF00FF, #00FFFF, #FF00FF) 1;
                box-shadow: 0 0 15px #FF00FF, 0 0 20px #00FFFF, 0 0 30px #FF00FF;
                animation: flicker 1.5s infinite alternate;
                color: #FFF;
                font-family: 'Courier New', Courier, monospace;
            }

            .license-expired-container h2 {
                text-transform: uppercase;
                letter-spacing: 3px;
                text-shadow: 0 0 5px #FF00FF, 0 0 10px #00FFFF;
                animation: pulse 2s infinite;
            }

            .license-expired-container .message-text {
                font-size: 1.2em;
                color: #d1d1d1;
                text-shadow: 0 0 2px #FF00FF;
            }

            .license-expired-container .contact-info {
                margin-top: 30px;
                font-size: 1em;
                color: #a0a0a0;
            }

            @keyframes flicker {
                0%, 19%, 21%, 23%, 25%, 54%, 56%, 100% {
                    box-shadow: 0 0 15px #FF00FF, 0 0 20px #00FFFF, 0 0 30px #FF00FF;
                }
                20%, 24%, 55% {
                    box-shadow: none;
                }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        </style>
    </head>

    <body>
        <?php
        if ($type === 'license-expired') {
            echo '<div class="message-container license-expired-container">';
            echo '<h2>LICENSE EXPIRED</h2>';
            echo '<p class="message-text">Your license has expired. Access to the Game Control Panel will be restricted.</p>';
            echo '<p class="contact-info">To re-activate the service, please contact the administrator.</p>';
            echo '<a href="https://t.me/zeezoia" target="_blank">CONTACT ADMIN</a>';
            echo '</div>';
        } else {
            // Original logic for other message types, but with updated styling
            echo '<div class="message-container ' . $type . '">';
            echo '<h2>RF ONLINE GAME CP</h2>';
            echo '<p>' . $message . '</p>';
            echo '</div>';
        }
        ?>
    </body>
<?php exit(1);

}

# Check to see if we have setup our stuff?
if (!file_exists('./includes/main/config.php')) {
    quick_msg("Please setup your Game Control Panel config.php (re-name config.php.edit to config.php)");
}

# Check to see if we our definition file
if (!file_exists('./includes/main/definitions.php')) {
    quick_msg("Please go into your includes/main/ and rename definitions.php.edit to definitions.php. Edit its contents only if needed!", 'warning');
}

# Well, we really cannot do anything is MSSQL is not installed :(
if (@phpversion() >= '5.3.0' && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if (extension_loaded('sqlsrv')) {
        include "./includes/main/mssql_to_sqlsrv.php";
    } elseif (!extension_loaded('mssql')) {
        quick_msg("Your server, running PHP 5.3+ does not have the SQLSRV module loaded OR the MSSQL module loaded");
    }
} else {
    if (!function_exists('mssql_connect')) {
        quick_msg("Your server does not have the MSSQL module loaded with PHP");
    }
}

# Make sure we can read/write to our cache directory
if (!is_dir('./includes/cache/')) {
    quick_msg("Woops! Please create the cache folder");
}

# Make sure
if (!is_writable('./includes/cache')) {
    quick_msg("Woops! It looks like I cannot read/write to the /includes/cache/ folder. Make sure I have the right permissions");
}

# I'm going to do some variable checking and fixing
# Seems that some web servers don't provide soem key varaibles! REQUEST_URI and DOCUMENT_ROOT? Seriously
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/' . substr($_SERVER['PHP_SELF'], 1);

    if (isset($_SERVER['QUERY_STRING']) and $_SERVER['QUERY_STRING'] != "") {
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
    }
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
    }
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
    if (isset($_SERVER['PATH_TRANSLATED'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
    }
}

# Begin session
@session_start();

# Include Main Files
include('./includes/main/function/global_functions.php');
include('./includes/main/config.php');
include('./includes/main/definitions.php');
include_once('./includes/util/Aes256.php');

// --- Blok lisensi dipindahkan ke bawah, setelah $config di-load ---

if (is_array($_GET)) {
    while (list($k, $v) = each($_GET)) {
        if (is_array($_GET[$k])) {
            while (list($k2, $v2) = each($_GET[$k])) {
                $_GET[$k][$k2] = antiject($v2);
            }
            @reset($_GET[$k]);
        } else {
            $_GET[$k] = antiject($v);
        }
    }
}

if (is_array($_POST)) {
    while (list($k, $v) = each($_POST)) {
        if (is_array($_POST[$k])) {
            while (list($k2, $v2) = each($_POST[$k])) {
                $_POST[$k][$k2] = antiject($v2);
            }
            @reset($_POST[$k]);
        } else {
            $_POST[$k] = antiject($v);
        }
    }
}

if (is_array($_COOKIE)) {
    while (list($k, $v) = each($_COOKIE)) {
        if (is_array($_COOKIE[$k])) {
            while (list($k2, $v2) = each($_COOKIE[$k])) {
                $_COOKIE[$k][$k2] = antiject($v2);
            }
            @reset($_COOKIE[$k]);
        } else {
            $_COOKIE[$k] = antiject($v);
        }
    }
    @reset($_COOKIE);
}

function validIp($ip): bool
{
    if (!empty($ip) && ip2long($ip) != -1) {
        $reserved_ips = array(
            array('0.0.0.0', '2.255.255.255'),
            array('10.0.0.0', '10.255.255.255'),
            array('127.0.0.0', '127.255.255.255'),
            array('169.254.0.0', '169.254.255.255'),
            array('172.16.0.0', '172.31.255.255'),
            array('192.0.2.0', '192.0.2.255'),
            array('192.168.0.0', '192.168.255.255'),
            array('255.255.255.0', '255.255.255.255')
        );

        foreach ($reserved_ips as $r) {
            $min = ip2long($r[0]);
            $max = ip2long($r[1]);
            if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
        }
        return true;
    } else {
        return false;
    }
}

function getip()
{
    if (isset($_SERVER["HTTP_CLIENT_IP"]) && validIp($_SERVER["HTTP_CLIENT_IP"])) {
        return $_SERVER["HTTP_CLIENT_IP"];
    }
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        foreach (explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]) as $ip) {
            if (validIp(trim($ip))) {
                return $ip;
            }
        }
    }
    if (isset($_SERVER["HTTP_X_FORWARDED"]) && validIp($_SERVER["HTTP_X_FORWARDED"])) {
        return $_SERVER["HTTP_X_FORWARDED"];
    } elseif (isset($_SERVER["HTTP_FORWARDED_FOR"]) && validIp($_SERVER["HTTP_FORWARDED_FOR"])) {
        return $_SERVER["HTTP_FORWARDED_FOR"];
    } elseif (isset($_SERVER["HTTP_FORWARDED"]) && validIp($_SERVER["HTTP_FORWARDED"])) {
        return $_SERVER["HTTP_FORWARDED"];
    } elseif (isset($_SERVER["HTTP_X_FORWARDED"]) && validIp($_SERVER["HTTP_X_FORWARDED"])) {
        return $_SERVER["HTTP_X_FORWARDED"];
    } else {
        return $_SERVER["REMOTE_ADDR"];
    }
}

$_SERVER["REMOTE_ADDR"] = getip();

/* Get GameCP Configuration */
connectgamecpdb();
$config_query = "SELECT config_name, config_value FROM gamecp_config";
if (!($config_result = mssql_query($config_query))) {
    echo "Unable to obtain data from the configuration database";
    exit;
}
while ($row = mssql_fetch_array($config_result)) {
    $config[$row['config_name']] = $row['config_value'];
}
mssql_free_result($config_result);
mssql_close($gamecp_dbconnect);
# End Config

// --- START DYNAMIC LICENSE FETCH & CHECK (MODIFIED) ---
// Blok ini sengaja diletakkan di sini SETELAH $config di-load

// 1. Ambil nama program dari config
$programName = isset($config['gamecp_programname']) ? $config['gamecp_programname'] : 'gamecp'; // Fallback 'gamecp'

// 2. Ubah nama program menjadi nama file: lowercase, hapus spasi
$licenseFileName = str_replace(' ', '', strtolower($programName)) . '_license.php';

// 3. Bangun URL lengkap (HTTP dan folder LICENSE yang benar)
$licenseUrl = 'http://localhost/license/' . $licenseFileName;

// 4. Siapkan konteks stream (timeout + ignore SSL)
$contextOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
    'http' => [
        'timeout' => 5 // Timeout dalam 5 detik
    ]
];
$streamContext = stream_context_create($contextOptions);

// 5. Ambil data lisensi dari URL dinamis
$fetchedData = @file_get_contents($licenseUrl, false, $streamContext);

// 6. Cek hasil
if ($fetchedData !== false) {
    // --- FILE DITEMUKAN ---
    $licenseExpirationDate = trim($fetchedData);
    
    // Definisikan konstanta agar bisa dibaca oleh halaman admin
    if (!defined('GAMECP_LICENSE_EXPIRATION_DATE')) {
        define('GAMECP_LICENSE_EXPIRATION_DATE', $licenseExpirationDate);
    }

    // Validasi tanggal
    $currentTime = strtotime(date("Y-m-d"));
    
    // Cek jika lisensinya 'Permanent'
    if ($licenseExpirationDate == 'Permanent') {
        // Lisensi valid, skrip boleh lanjut.
    } else {
        // Jika bukan 'Permanent', cek tanggalnya
        $licenseTime = strtotime($licenseExpirationDate);
        
        // Cek jika $licenseTime gagal (format tanggal salah) ATAU sudah lewat
        if ($licenseTime === false || $licenseTime < $currentTime) {
            quick_msg("LICENSE EXPIRED, PLEASE CONTACT THE ADMINISTRATOR", "license-expired");
        }
    }
    // Jika lolos semua cek, skrip lanjut

} else {
    // --- FILE TIDAK DITEMUKAN (LOGIKA BARU) ---
    // Jika gagal (file tidak ditemukan), langsung tampilkan error
    error_log("Failed to fetch dynamic license from URL: $licenseUrl. License file not found or inaccessible.");
    quick_msg("LICENSE EXPIRED, PLEASE CONTACT THE ADMINISTRATOR", "license-expired");
}
// --- END DYNAMIC LICENSE FETCH & CHECK ---


# Set error handler
if ($config['security_enable_debug'] == 1) {
    error_reporting(E_ALL ^ E_NOTICE);
} else {
    error_reporting(0);
}
set_error_handler('errorHandler');
# End Erro Handler

# Set some variables here
$script_name = (isset($config['gamecp_filename'])) ? $config['gamecp_filename'] : 'index.php';
$program_name = (isset($config['gamecp_programname'])) ? $config['gamecp_programname'] : 'Game CP';
$super_admin = explode(",", $admin['super_admin']);
$allowed_ips = ($admin['allowed_ips'] != '') ? explode(",", $admin['allowed_ips']) : array();

# Let's load up our language file, create a cache first...
$gamecp_lang = (isset($config['gamecp_lang'])) ? $config['gamecp_lang'] : 'en';
$lang_file = './includes/language/lang_' . $gamecp_lang . '.xml';
if (file_exists($lang_file)) {
    $xml = simplexml_load_file($lang_file);

    foreach ($xml->translations->string as $key => $string) {
        $lang_key = trim((string)$string->attributes()->key);
        $lang_value = (string)$string;
        $lang[$lang_key] = $lang_value;
    }
}

$onload = '';
$vbpath = '';
$index = '';
$out = '';
$mainincludes = '';

$isuser = false;
$notuser = true;
$exitLogin = false;

# Get the current script name for 'checking'
$scripts = $_SERVER['PHP_SELF'];
$scripts = explode(chr(47), $scripts);
$this_script = $scripts[count($scripts) - 1];

# Do login check (?)
$cookiedata = (isset($_COOKIE["gamecp_userdata"])) ? $_COOKIE["gamecp_userdata"] : '';
$cookieUserData = (isset($_COOKIE["sessiondata"])) ? $_COOKIE["sessiondata"] : '';

# Set/Get Variables required
$ip = GetHostByName(getip());

# Or check variables :D
$out = '';
$title = '';
$exitMessage = '';
$userdata = array();
$userdata['email'] = '';
$userdata['status'] = false;
$userdata['pin'] = '';
$userdata['username'] = 'Guest';
$userdata['serial'] = '-1';
$userdata['credits'] = '';
$userdata['createtime'] = '';
$userdata['lastconnectip'] = '';
$userdata['points'] = 0;
$isDisable = false;
$userdata['vote_points'] = 0;
$userdata['ip'] = getip();

# Okay, since we need this to be secure, lets kill the script if
# out salt is not set, okay?
if (!isset($config['security_salt']) or empty($config['security_salt'])) {
    quick_msg("Cannot run the script without the security_salt set to a value!");
}

# Check to see if user is logged in
if (!empty($cookiedata) && !empty($cookieUserData)) {
    $cookieex = explode('|', $cookiedata);
    $cookieUsername = trim($cookieex[0]);
    $cookiePassword = trim($cookieex[1]);
    $isuser = true;
    $notuser = false;

    $username = decrypt($cookieUserData, $aesKey);

    # No errors? Login then m8!
    if (!$exitLogin) {
        getDataUser($cookieUsername);
        getDataGameCp($rowUser['serial']);
        if (!empty($message)) {
            displayError($message);
            $exitLogin = true;
            logoutUser();
        } else {
            $userdata['username'] = antiject(trim($rowUser['id']));
            $userdata['password'] = antiject(trim($rowUser['password']));
            $passwordData = md5($userdata['username']) . $ip . sha1(md5(trim($userdata['password']) . $config['security_salt']));

            if ($cookieUsername == trim($rowUser['id']) && $cookiePassword == $passwordData) {
    $isuser = true;
    $notuser = false;

    $userdata['serial'] = $rowUser['serial'];
    $userdata['email'] = $rowUser['email'];
    $userdata['pin'] = trim($rowUser['pin']);
    $userdata['createtime'] = date("d M Y H:i:s", strtotime($rowUser['createtime']));
    $userdata['lastconnectip'] = $rowUser['lastconnectip'];
    $userdata['lastlogintime'] = date("d M Y H:i:s", strtotime($rowUser['lastlogintime']));
    $userdata['lastlogofftime'] = date("d M Y H:i:s", strtotime($rowUser['lastlogofftime']));
    
    // --- [START] DYNAMIC BILLING LOGIC ---
    // $config['billing_type'] sudah di-load dari config query di atas
    $billing_dbconnect = connectbillingdb(); // Buka koneksi ke BILLING DB
    $billing_type = isset($config['billing_type']) ? $config['billing_type'] : 0;
    
    $current_username_binary = "CONVERT(BINARY, '" . $userdata['username'] . "')";
    $current_username_varchar = "'" . $userdata['username'] . "'";

    // Set nilai default
    $userdata['cashpoint'] = 0;
    $userdata['DTEndPrem'] = date("d M Y H:i:s");
    $userdata['Status'] = "Inactive";

    switch ($billing_type) {
        case 1:
            // Tipe 1: Ambil Cash dari tbl_user
            $cash_query = @mssql_query(sprintf("SELECT Cash FROM tbl_user WHERE UserID = %s", $current_username_varchar), $billing_dbconnect);
            if ($cash_query && $cash_row = @mssql_fetch_assoc($cash_query)) {
                $userdata['cashpoint'] = $cash_row['Cash'];
            }
            @mssql_free_result($cash_query);
            
            // Ambil Premium dari tbl_personal_billing
            $prem_query = @mssql_query(sprintf("SELECT EndDate, BillingType FROM tbl_personal_billing WHERE ID = %s", $current_username_binary), $billing_dbconnect);
            if ($prem_query && $prem_row = @mssql_fetch_assoc($prem_query)) {
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($prem_row['EndDate']));
                $userdata['Status'] = ($prem_row['BillingType'] == 2) ? "Active" : "Inactive"; // Asumsi BillingType 2 = Aktif
            }
            @mssql_free_result($prem_query);
            break;

        case 2:
            // Tipe 2: Owndev (tbl_UserStatus dengan PremiHours)
            $query = @mssql_query(sprintf("SELECT Cash, DTEndPrem, Status FROM tbl_UserStatus WHERE id = %s", $current_username_binary), $billing_dbconnect);
            if ($query && $row = @mssql_fetch_assoc($query)) {
                $userdata['cashpoint'] = $row['Cash'];
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($row['DTEndPrem']));
                $userdata['Status'] = ($row['Status'] == 2) ? "Active" : "Inactive";
            }
            @mssql_free_result($query);
            break;
        
        case 0:
        default:
            // Tipe 0: Default (tbl_UserStatus dengan PremiDay)
            $query = @mssql_query(sprintf("SELECT Cash, DTEndPrem, Status FROM tbl_UserStatus WHERE id = %s", $current_username_binary), $billing_dbconnect);
            if ($query && $row = @mssql_fetch_assoc($query)) {
                $userdata['cashpoint'] = $row['Cash'];
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($row['DTEndPrem']));
                $userdata['Status'] = ($row['Status'] == 2) ? "Active" : "Inactive";
            }
            @mssql_free_result($query);
            break;
    }
    
    @mssql_close($billing_dbconnect); // Tutup koneksi BILLING DB
    // --- [END] DYNAMIC BILLING LOGIC ---

    $userdata['islogin'] = $rowUser['lastlogintime'] >= $rowUser['lastlogofftime'];
    $userdata['points'] = $rowGameCp['user_points'];
    $userdata['vote_points'] = $rowGameCp['user_vote_points'];

    if ($userdata['serial'] == '') {
        logoutUser();
    }

    connectgamecpdb(); // Buka koneksi lagi untuk cek permission
    $permission_query = mssql_query(sprintf("SELECT admin_permission FROM gamecp_permissions WHERE admin_serial = %u", $userdata['serial']));
    if (!($user_access = @mssql_fetch_array($permission_query))) {
        $user_access = false;
    } else {
        mssql_free_result($permission_query);
    }
    mssql_close($gamecp_dbconnect);
} else {
    $notuser = true;
    $isuser = false;
    logoutUser();
}
            unset($userdata['password']);
        }
    } else {
        $out .= $exit_message;
    }
} else {
    $isuser = false;
}

# Are we a super-m--admin?
if ($isuser && in_array($userdata['username'], $super_admin)) {
    if (!empty($allowed_ips)) {
        if (checkIP($userdata['ip'], $allowed_ips)) {
            $is_superadmin = true;
        } else {
            $out .= '<p style="text-align: center; font-weight: bold;">You do not have the necessary permissions log into this account. This has been logged.</p>';
            gamecp_log(5, $userdata['username'], "GAMECP - LOGIN - FAILED TO LOG INTO SUPER ADMIN ACCOUNT. IP RESTRICTED", 1);

            $_SESSION = array(); // destroy all $_SESSION data
            setcookie("gamecp_userdata", "", time() - 3600);
            if (isset($_COOKIE["gamecp_userdata"])) {
                unset($_COOKIE["gamecp_userdata"]);
            }
            $notuser = true;
            $isuser = false;
            session_destroy();
            if (isset($userdata['password'])) {
                unset($userdata['password']);
            }
            $is_superadmin = false;
        }
    } else {
        $is_superadmin = true;
    }
} else {
    $is_superadmin = false;
    error_reporting(0);
}

# Security token, will be used for sessions mearly
$securitytoken_raw = sha1($userdata['serial'] . sha1($config['security_salt']) . sha1($config['security_salt']));
$securitytoken = time() . '-' . sha1(time() . $securitytoken_raw);

# Check to see if we are using recaptcha
if (isset($config['security_recaptcha_enable']) && $config['security_recaptcha_enable'] == 1) {
    require_once('./includes/main/recaptchalib.php');

    // Get a key from http://recaptcha.net/api/getkey
    $publickey = (isset($config['security_recaptcha_public_key'])) ? $config['security_recaptcha_public_key'] : '';
    $privatekey = (isset($config['security_recaptcha_private_key'])) ? $config['security_recaptcha_private_key'] : '';

    # the response from reCAPTCHA
    $resp = null;
    # the error code from reCAPTCHA, if any
    $error = null;
}

# Special userdata stuff to be used in templates
$user_vote_points = number_format($userdata['vote_points']);

connectgamecpdb();
$vote = array();
$vote_sql = "SELECT vote_id, vote_site_name, vote_site_url, vote_site_image, vote_reset_time FROM gamecp_vote_sites";
if (!($vote_result = mssql_query($vote_sql))) {
    $exit_stage_0 = true;
    $show_form = false;
    $page_info .= '<p style="text-align: center; font-weight: bold;">SQL Error while trying to obtain vote sites data</p>';
}
while ($row = @mssql_fetch_array($vote_result)) {
    $vote[] = $row;
}
mssql_free_result($vote_result);
mssql_close($gamecp_dbconnect);
