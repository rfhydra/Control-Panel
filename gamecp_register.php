<?php
include_once('./includes/main/validation/exclude/validation_account_registration.php');
include_once('./includes/main/function/register/func_account_registration.php');

# Security setting
const IN_GAMECP_SALT58585 = true;

# Include main files
include './gamecp_common.php';

# Redirect logged in users
if ($isuser) {
    header("Location: index.php");
    exit;
}

# and append it to the $out variable
ob_start();

$licenseTime = strtotime(date("d-M-Y", strtotime($config['gamecp_license_days'])));
$currentTime = strtotime(date("d-M-Y", strtotime("now")));

# Set page title
$leftTitle = _l('register_title');
$title = $program_name . ' - ' . $leftTitle;

$showTerms = true;
$showRegistrationForm = true;

$page = (isset($_GET['page']) || isset($_POST['page'])) ? (isset($_GET['page'])) ? $_GET['page'] : $_POST['page'] : "";

if ($page == 'terms' && !isset($_COOKIE['sessionReg'])) {
    setCookieTerms($page, $ip, $config['security_salt']);
    $showTerms = false;
} else {
    $cookieData = $_COOKIE['sessionReg'];
    $isValid = isTermsCookieValid("terms", $ip, $config['security_salt'], $cookieData);
    if ($isValid)  $showTerms = false;
}

if (count($_GET) > 0) {
    header("location: " . $this_script);
} else if ($page == 'register') {
    $username = (isset($_POST['username'])) ? antiject($_POST['username']) : '';
    $password = (isset($_POST['password'])) ? antiject($_POST['password']) : '';
    $confirmPassword = (isset($_POST['confirmPassword'])) ? antiject($_POST['confirmPassword']) : '';
    $email = (isset($_POST['email'])) ? antiject($_POST['email']) : '';
    $confirmEmail = (isset($_POST['confirmEmail'])) ? antiject($_POST['confirmEmail']) : '';
    $pin = (isset($_POST['pin'])) ? antiject($_POST['pin']) : '';

    if (isset($config['security_recaptcha_enable']) && $config['security_recaptcha_enable'] == 1 && empty($_POST['g-recaptcha-response'])) {
        displaySingleError("Registration Failed", "Please Enter Captcha");
    } else {
        validateRegistrationField($username, $password, $confirmPassword, $email, $confirmEmail, $pin);
        executeRegistrationUser($username, $password, $email, $pin);

        if (!empty($message)) {
            displayError($message);
            $username = $isValidUsername ? $username : "";
            $password = $isValidPassword ? $password : "";
            $confirmPassword = $isValidPassword ? $confirmPassword : "";
            $email = $isValidEmail ? $email : "";
            $confirmEmail = $isValidEmail ? $confirmEmail : "";
            $pin = $isValidPin ? $pin : "";
        } else {
            $showRegistrationForm = false;
            $message = _l('successfully_registered_account_short');
            displaySuccess(_l('registration_success_title'), $message);

            setcookie("sessionReg", "", time() - 3600, '/');
            if (isset($_COOKIE["sessionReg"])) {
                unset($_COOKIE["sessionReg"]);
            }

            header("Refresh: 2; url=index.php ");
        }
    }
}

if ($showTerms) {
    $leftTitle = 'Terms and Agreement';
    $title = $program_name;
    include_once('./termAndAgreement.php');
} else if ($showRegistrationForm) {
    $out .= '<style>table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {border-top : 0px;}</style>';
    include_once('./registration_form.php');
}

# Append data to the $out variable
$out .= ob_get_contents();
ob_end_clean();

# Display the navigation
gamecp_nav();

# Display the template
eval('print_outputs("' . gamecpTempalte('gamecp') . '");');

