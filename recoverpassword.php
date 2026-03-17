<?php
include_once('./includes/main/validation/exclude/validation_recover_password.php');
include_once('./includes/main/function/external/func_user_recover_password.php');

# Security setting
const IN_GAMECP_SALT58585 = true;

include './gamecp_common.php';

# Redirect logged in users
if ($isuser) {
    header("Location: index.php");
    exit;
}

# and append it to the $out variable
ob_start();

$leftTitle = _l('Recover Password');
$title = $program_name . ' - ' . $leftTitle;

$page = (isset($_GET['page']) || isset($_POST['page'])) ? (isset($_GET['page'])) ? $_GET['page'] : $_POST['page'] : "";

if (count($_GET) > 0) {
    header("location: " . $this_script);
} else if ($page == 'recover') {
    $username = (isset($_POST['username'])) ? antiject($_POST['username']) : '';
    $email = (isset($_POST['email'])) ? antiject($_POST['email']) : '';
    $pin = (isset($_POST['pin'])) ? antiject($_POST['pin']) : '';

    if (isset($config['security_recaptcha_enable']) && $config['security_recaptcha_enable'] == 1 && empty($_POST['g-recaptcha-response'])) {
        displaySingleError("Login Failed", "Please Enter Captcha");
    } else {
        valdiateRecoverField($username, $email, $pin);
        executeRecoverPassword($username, $email, $pin);
        if (!empty($message)) {
            displayError($message);
        } else {
            $out .= '<div class="alert alert-success">';
            $out .= '<h4>Recover Password Success!</h4>';
            $out .= '<p>Username : '. trim($rowUser['id']) .'</p>';
            $out .= '<p>Password : '. trim($rowUser['password']) .'</p>';
            $out .= '</div>';
        }
    }

$out .= '<style>table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {border-top : 0px;}</style>';
$out .= '<form method="post" autocomplete="off">';
$out .= '<div class="panel panel-primary">';
$out .= '<div class="panel-heading"> ';
$out .= '<h3 class="panel-title">RECOVER YOUR PASSWORD HERE</h3>';
$out .= '</div>';
$out .= '<div class="panel-body">';
$out .= '<table class="table">';
$out .= '<tbody>';
$out .= '<tr>';
$out .= '<th class="success" width="15%" nowrap=""><i class="fa-solid fa-user"></i> Username</th>';
$out .= '<td><input type="text" autocomplete="off" class="form-control" name="username" placeholder="Username" maxlength="12" value="'. $username .'" pattern="[a-zA-Z0-9]{4,12}" required=""></td>';
$out .= '</tr>';
$out .= '<tr>';
$out .= '<th class="success" width="15%" nowrap=""><i class="fa-solid fa-envelope"></i> Email Address</th>';
$out .= '<td><input type="email" autocomplete="off" class="form-control" name="email" placeholder="Email" maxlength="50" value="'. $email .'" pattern=".{4,50}" required=""></td>';
$out .= '</tr>';
$out .= '<tr>';
$out .= '<th class="success" width="15%" nowrap=""><i class="fa-solid fa-list-ol"></i> PIN</th>';
$out .= '<td><input type="password" name="pin" autocomplete="off" class="form-control" placeholder="PIN" maxlength="6" pattern="[0-9]{6}" value="'. $pin .'"  required oninput="if (!window.__cfRLUnblockHandlers) return false; this.value=this.value.replace(/[^0-9]/g, ``);"></td>';
$out .= '</tr>';
$out .= '<tr><td></td></tr>';
$out .= '<tr><td colspan="2" style="padding : 0"><div class="cf-turnstile" data-sitekey="'. antiject($publickey) .'"></div></div></td></tr>';
$out .= '<tr><td></td></tr>';
$out .= '<tr>';
$out .= '<td colspan="2" style="padding : 0">';
$out .= '<a href="index.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back</a> &nbsp;&nbsp;&nbsp; ';
$out .= '<button type="submit" class="btn btn-success">Recover Password</button><input type="hidden" name="page" value="recover"></td>';
$out .= '</tr>';
$out .= '</tbody></table>';
$out .= '</div>';
$out .= '</div>';
$out .= '</form>';

# Append data to the $out variable
$out .= ob_get_contents();
ob_end_clean();

# Display the navigation
gamecp_nav();

# Display the template
eval('print_outputs("' . gamecpTempalte('gamecp') . '");');