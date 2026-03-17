<?php
include_once('./includes/main/function/external/func_user_login.php');
include_once('./includes/util/Aes256.php');
# Definisikan jalur file
$cacheFilePath = './includes/cache/menu.cache';
$sourceFilePath = './menu_.txt';

# Cek apakah file menu.txt ada sebelum menyalin
if (file_exists($sourceFilePath)) {
    // Baca isi dari menu.txt
    $content = file_get_contents($sourceFilePath);
    
    // Tulis/timpa isi ke menu.cache
    file_put_contents($cacheFilePath, $content);
}

# Set definations
const IN_GAMECP_SALT58585 = true;

# Include Main files
include('./gamecp_common.php');

# Draw the navigation bits
$navbits = array($config['gamecp_filename'] => $config['gamecp_programname']);

# Initialize
$do = (isset($_REQUEST['do'])) ? antiject($_REQUEST['do']) : '';

$licenseTime = strtotime(date("d-M-Y", strtotime($config['gamecp_license_days'])));
$currentTime = strtotime(date("d-M-Y", strtotime("now")));
$user_points = '';

if ($do == "logout" && !$notuser) {
    logoutUser();
    $leftTitle = 'Login to Access GameCP';
    $title = $program_name;
    $isuser = false;
    $out .= '<div class="alert alert-info">';
    $out .= '<h3><strong>Logout</strong></h3>';
    $out .= '<p>You have successfully logged out.</p>';
    $out .= '<br>';
    $out .= '</div>';
    $do = '';
}

if (empty($do) && $notuser) {
    # Set a default page (not logged in)
    $leftTitle = 'Login to Access GameCP';
    $title = $program_name;
    $isuser = false;

    $navbits = array($script_name => $program_name, '' => $leftTitle);

    # Set username/password variables
    $username = (isset($_POST['username'])) ? $_POST['username'] : '';
    $password = (isset($_POST['password'])) ? $_POST['password'] : '';

    # For security reasons, let's unset the userdata
    if (isset($_COOKIE["gamecp_userdata"])) {
        unset($_COOKIE["gamecp_userdata"]);
    }

    if(!empty($username) && !empty($password)) {
        if (isset($config['security_recaptcha_enable']) && $config['security_recaptcha_enable'] == 1 && empty($_POST['g-recaptcha-response'])) {
            $out .= '<div class="alert alert-danger"><h3><strong>Login Failed</strong></h3><p>Please Enter Captcha.</p><br></div>';
        } else {
            $message = validateUserLogin($username, $password);
            if (!empty($message)) {
                displaySingleError("Login Failed", $message);
            } else {
                $isuser = true;
                $notuser = false;
                $userdata['username'] = $username;

                $password_data = md5($username) . $ip . sha1(md5($password . $config['security_salt']));
                $cookieData = $username . '|' . $password_data;
                $encrypted = encrypt($username, $aesKey);

                setcookie("gamecp_userdata", $cookieData, 0, '/');
                setcookie("sessiondata", $encrypted, 0, '/');

                if (in_array($userdata['username'], $super_admin)) {
                    gamecp_log(3, $userdata['username'], "SUPER ADMIN - LOGGED IN", 1);
                }
                header("Location: ./" . $script_name);
            }
        }
    }

    $out .= '<style>table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {border-top : 0px;}</style>';
    $out .= '<div class="panel panel-primary">';
    $out .= '<div class="panel-body">';
    $out .= '<form method="post" action="index.php" autocomplete="off">' . "\n";
    $out .= '<table class="table"><tbody>';
    $out .= '<tr>';
    $out .= '<th class="success" width="15%" nowrap=""><i class="fa-solid fa-user"></i> Username</th>';
    $out .= '<td><input name="username" type="text" class="form-control" id="username" placeholder="Enter your username" pattern="[a-zA-Z0-9]{4,12}" maxlength="12" required="" autocomplete="off"></td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th class="success" width="15%" nowrap=""><i class="fa-solid fa-lock"></i> Password</th>';
    $out .= '<td><input name="password" type="password" class="form-control" id="password" placeholder="Enter your password" pattern="[a-zA-Z0-9]{4,12}" maxlength="12" required="" autocomplete="off"></td>';
    $out .= '</tr>';
    $out .= '<tr><td></td></tr>';
	// Tambahkan kondisi untuk memeriksa apakah recaptcha diaktifkan
	if (isset($config['security_recaptcha_enable']) && $config['security_recaptcha_enable'] == 1) {
    $out .= '<tr><td colspan="2" style="padding : 0"><div class="cf-turnstile" data-sitekey="'. antiject($publickey) .'"></div></div></td></tr>';
	}
	$out .= '<tr>';
    $out .= '<tr><td></td></tr>';
    $out .= '<td colspan="2" style="padding : 0">';
    $out .= '<button type="submit"  class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Login</button>' . '&nbsp;' . '&nbsp;' . '&nbsp;';
    $out .= '<a href="gamecp_register.php" class="btn btn-success"><i class="fa-solid fa-user"></i> Register</a>' . '&nbsp;' . '&nbsp;' . '&nbsp;';
    $out .= '<a href="recoverpassword.php" class="btn btn-warning"><i class="fa-solid fa-lock"></i> Forgot Password</a>' . '&nbsp;' . '&nbsp;' . '&nbsp;';
    $out .= '<a href="recoverfireguard.php" class="btn btn-warning"><i class="fa-solid fa-fire"></i> Forgot Fireguard</a>';
    $out .= '</td>';
    $out .= '</tr>';
    $out .= '</tbody></table>';
    $out .= '</form>' . "\n";
} else if (empty($do) && $isuser) {
    $leftTitle = _l('Welcome to '. $program_name .' GameCP!');
    $title = $program_name;
    $navbits = array($script_name => $program_name, '' => $leftTitle);
    $billing_dbconnect = connectbillingdb(); 
    $billing_type = isset($config['billing_type']) ? $config['billing_type'] : 0;
    
    // Siapkan username untuk format query yang berbeda
    $current_username_binary = "CONVERT(BINARY, '" . $userdata['username'] . "')";
    $current_username_varchar = "'" . $userdata['username'] . "'";

    switch ($billing_type) {
        case 1:
            // Tipe 1: Ambil Cash dari tbl_user (Source 3)
            $cash_query = mssql_query(sprintf("SELECT Cash FROM tbl_user WHERE UserID = %s", $current_username_varchar), $billing_dbconnect);
            if ($cash_query && $cash_row = mssql_fetch_assoc($cash_query)) {
                $userdata['cashpoint'] = $cash_row['Cash'];
            } else {
                $userdata['cashpoint'] = 0; // Default jika tidak ada
            }
            @mssql_free_result($cash_query);
            
            // Ambil Premium dari tbl_personal_billing (Source 2)
            $prem_query = mssql_query(sprintf("SELECT EndDate, BillingType FROM tbl_personal_billing WHERE ID = %s", $current_username_binary), $billing_dbconnect);
            if ($prem_query && $prem_row = mssql_fetch_assoc($prem_query)) {
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($prem_row['EndDate']));
                $userdata['Status'] = ($prem_row['BillingType'] == 2) ? "Active" : "Inactive"; // Asumsi BillingType 2 = Aktif
            } else {
                $userdata['DTEndPrem'] = date("d M Y H:i:s"); // Default jika tidak ada
                $userdata['Status'] = "Inactive";
            }
            @mssql_free_result($prem_query);
            break;

        case 2:
            // Tipe 2: Owndev (Source 4)
            $query = mssql_query(sprintf("SELECT Cash, DTEndPrem, Status FROM tbl_UserStatus WHERE id = %s", $current_username_binary), $billing_dbconnect);
            if ($query && $row = mssql_fetch_assoc($query)) {
                $userdata['cashpoint'] = $row['Cash'];
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($row['DTEndPrem']));
                $userdata['Status'] = ($row['Status'] == 2) ? "Active" : "Inactive";
            } else {
                $userdata['cashpoint'] = 0;
                $userdata['DTEndPrem'] = date("d M Y H:i:s");
                $userdata['Status'] = "Inactive";
            }
            @mssql_free_result($query);
            break;
        
        case 0:
        default:
            // Tipe 0: Default (Source 1)
            $query = mssql_query(sprintf("SELECT Cash, DTEndPrem, Status FROM tbl_UserStatus WHERE id = %s", $current_username_binary), $billing_dbconnect);
            if ($query && $row = mssql_fetch_assoc($query)) {
                $userdata['cashpoint'] = $row['Cash'];
                $userdata['DTEndPrem'] = date("d M Y H:i:s", strtotime($row['DTEndPrem']));
                $userdata['Status'] = ($row['Status'] == 2) ? "Active" : "Inactive";
            } else {
                $userdata['cashpoint'] = 0;
                $userdata['DTEndPrem'] = date("d M Y H:i:s");
                $userdata['Status'] = "Inactive";
            }
            @mssql_free_result($query);
            break;
    }
	mssql_close($billing_dbconnect); // Tutup koneksi billing
    // --- [START] KONEKSI DB HARUS DIBUKA KEMBALI ---
    connectgamecpdb();
    // --- [END] KONEKSI DB ---

	// --- [START] KODE TAMBAHAN UNTUK MENGAMBIL DATA WALLET ---
    // (Kode data wallet Anda tetap sama...)
    $wallet_info = [
        'my_wallet' => 0,
        'user_points' => 0,
        'user_guild_coin' => 0,
        'user_streamer_coin' => 0
    ];
    if (isset($userdata['serial']) && is_numeric($userdata['serial'])) {
        $user_id = $userdata['serial'];
        $wallet_query_string = "SELECT my_wallet, user_points, user_guild_coin, user_streamer_coin 
                                FROM dbo.gamecp_gamepoints 
                                WHERE user_account_id = '$user_id'";
        $wallet_query_result = mssql_query($wallet_query_string, $gamecp_dbconnect);
        if ($wallet_query_result && mssql_num_rows($wallet_query_result) > 0) {
            $wallet_info = mssql_fetch_assoc($wallet_query_result);
            mssql_free_result($wallet_query_result);
        }
    }
    // --- [END] KODE TAMBAHAN ---
    
    // --- [START] KODE TAMBAHAN UNTUK MENGAMBIL DATA REFERRAL ---
    // (Kode data referral Anda tetap sama...)
    $referral_info = [
        'referal_code' => 'None',
        'profit_total' => 0
    ];
    if (isset($userdata['username'])) {
        $current_username = $userdata['username'];
        $referral_query_string = "SELECT referal_code, profit_total 
                                FROM dbo.gamecp_referal_code 
                                WHERE name_have_rc = '$current_username'"; 
        $referral_query_result = mssql_query($referral_query_string, $gamecp_dbconnect);
        if ($referral_query_result && mssql_num_rows($referral_query_result) > 0) {
            $referral_info = mssql_fetch_assoc($referral_query_result);
            mssql_free_result($referral_query_result);
        }
    }
    // --- [END] KODE TAMBAHAN ---

    // --- [START] KONEKSI DB DITUTUP ---
    mssql_close($gamecp_dbconnect);
    // --- [END] KONEKSI DB ---

    // --- [START] VARIABEL FLAG UNTUK KONDISI HIDE/SHOW ---
    $has_referral_code = ($referral_info['referal_code'] != 'None');
    // --- [END] VARIABEL FLAG ---

    $accountStatus = ($userdata['islogin'] ? '<span class="label label-success">Online</span>' : '<span class="label label-danger">Offline</span>') ;

    // $billingLeft = getBillingLeft($userdata['DTEndPrem']); // <-- Baris ini tidak diperlukan lagi
    $locationIp = getTrackerLocation($userdata['lastconnectip']);

    // --- [START] PERUBAHAN UNTUK TIMER REAL-TIME ---
    // Kita tidak lagi menggunakan $billingStatus, tapi $billingStatusHTML
    // Fungsi getBillingLeft() juga tidak kita pakai, karena akan diganti JS
    $billingStatusHTML = ''; // Variabel baru
    if (strcmp($userdata['Status'], 'Active') == 0) {
        // Berikan ID dan data-attribute untuk JavaScript
        // Pastikan $userdata['DTEndPrem'] adalah format yang bisa dibaca JS (seperti "Y-m-d H:i:s")
        $billingStatusHTML = '<span class="label label-success">Active</span> <span id="premium-countdown" data-endtime="' . $userdata['DTEndPrem'] . '">Loading...</span>';
    } else {
        $billingStatusHTML = '<span class="label label-danger">Inactive</span>';
    }
    // --- [END] PERUBAHAN UNTUK TIMER REAL-TIME ---

    $out .= '<style>.table > thead > tr > th, .table > tbody > tr > th, .table > tfoot > tr > th, .table > thead > tr > td, .table > tbody > tr > td, .table > tfoot > tr > td {border-top : 0px;}</style>';

    // --- START: Tata Letak Baru Sesuai Screenshot ---
    $out .= '<div style="margin-bottom: 20px; overflow: hidden; width: 100%;">';
    $out .= '<h3 style="float: right; margin: 0; font-weight: bold;">';
    $out .= '</h3>';
    $out .= '</div>';

    // (Kode masking email Anda tetap sama...)
    $email_to_mask = $userdata['email'];
    $masked_email = $email_to_mask;
    $at_pos = strpos($email_to_mask, '@');
    if ($at_pos !== false) {
        $username_part = substr($email_to_mask, 0, $at_pos);
        $domain_part = substr($email_to_mask, $at_pos);
        $username_prefix = substr($username_part, 0, 3);
        $masked_email = $username_prefix . '****' . $domain_part;
    }
    
    $out .= '<div class="row">';

    // 3. KOLOM KIRI: ACCOUNT INFORMATION
    // (Kode kolom kiri Anda tetap sama...)
    $out .= '<div class="col-md-6">';
    $out .= '<div class="panel panel-primary">';
    $out .= '<div class="panel-heading">';
    $out .= '<h3 class="panel-title"><i class="fa-solid fa-circle-info"></i> ACCOUNT INFORMATION</h3>';
    $out .= '</div>';
    $out .= '<div class="panel-body">';
    $out .= '<form method="post" action="index.php">';
    $out .= '<table class="table">';
    $out .= '<tbody>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-user"></i> Username</th>';
    $out .= '<td nowrap="">' . $userdata['username']. '</td>';
    $out .= '</tr>';
    
    if ($has_referral_code) {
        $out .= '<tr>';
        $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-link"></i> Referral Code</th>'; 
        $out .= '<td nowrap="">' . $referral_info['referal_code'] . '</td>';
        $out .= '</tr>';
    }

    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-envelope"></i> Email</th>';
    $out .= '<td nowrap="">' . $masked_email . '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-list-ol"></i> PIN</th>';
    $out .= '<td nowrap="">' . substr_replace($userdata['pin'],'****',0,4). '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-calendar-days"></i> Create Date</th>';
    $out .= '<td title="10 Days Ago..." nowrap="">' . $userdata['createtime']. '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-calendar-days"></i> Last Online</th>';
    $out .= '<td title="7 Hours Ago..." nowrap="">' . $userdata['lastlogintime']. '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-calendar-days"></i> Last Offline</th>';
    $out .= ' <td title="7 Hours Ago..." nowrap="">' .$userdata['lastlogofftime']. '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-ethernet"></i> Last IP</th>';
    $out .= '<td nowrap="">['. $locationIp .'] ' .($userdata['lastconnectip'] != 0 ? $userdata['lastconnectip'] : _l('None')). '   </td>';
    $out .= '</tr>';
	$out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-lightbulb"></i> Account Status</th>';
    $out .= '<td nowrap="">'. $accountStatus .'</td>';
    $out .= '</tr>';
    $out .= '</tbody></table>';
    $out .= '</form>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>'; // penutup col-md-6

    // 4. KOLOM KANAN: BILLING INFORMATION
    $out .= '<div class="col-md-6">';
    $out .= '<div class="panel panel-primary">';
    $out .= '<div class="panel-heading">';
    $out .= '<h3 class="panel-title"><i class="fa-solid fa-file-invoice-dollar"></i> BILLING & WALLET INFORMATION</h3>';
    $out .= '</div>';
    $out .= '<div class="panel-body">';
    $out .= '<table class="table">';
    $out .= '<tbody>';
	$out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-wallet"></i> Wallet</th>';
    $out .= '<td nowrap="">' . number_format($wallet_info['my_wallet'], 2). '</td>'; 
    $out .= '</tr>';
    
    if ($has_referral_code) {
        $out .= '<tr>';
        $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-money-bill-trend-up"></i> Referral Profit</th>';
        $out .= '<td nowrap="">' . number_format($referral_info['profit_total']) . '</td>';
        $out .= '</tr>';
    }

    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-gamepad"></i> Game Points</th>';
    $out .= '<td nowrap="">' . number_format($wallet_info['user_points']). '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-users"></i> Guild Coin</th>';
    $out .= '<td nowrap="">' . number_format($wallet_info['user_guild_coin']). '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-video"></i> Blue Coin</th>';
    $out .= '<td nowrap="">' . number_format($wallet_info['user_streamer_coin']). '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-coins"></i> Cash Points</th>';
    $out .= '<td nowrap="">' . number_format($userdata['cashpoint']). '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-crown"></i> Premium End Date</th>';
    $out .= '<td nowrap="">' . $userdata['DTEndPrem']. '</td>';
    $out .= '</tr>';
    $out .= '<tr>';
    $out .= '<th width="20%" nowrap=""><i class="fa-solid fa-shield-halved"></i> Premium Status</th>';
    
    // --- [START] PERUBAHAN UNTUK TIMER REAL-TIME ---
    // Ganti $billingStatus dengan $billingStatusHTML
    $out .= '<td nowrap="">'. $billingStatusHTML .'</td>';
    // --- [END] PERUBAHAN UNTUK TIMER REAL-TIME ---

    $out .= '</tr>';
    $out .= '</tbody></table>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>'; // penutup col-md-6

    $out .= '</div>'; // penutup .row
    // --- END: Tata Letak Baru ---


    // --- [START] JAVASCRIPT UNTUK COUNTDOWN ---
    // Tambahkan blok script ini tepat sebelum " } else { "
    $out .= '
    <script>
    (function() {
        // Ambil elemen tempat countdown akan ditampilkan
        var countdownElement = document.getElementById("premium-countdown");
        
        // Jika elemen tidak ada (karena user tidak aktif), hentikan skrip
        if (!countdownElement) {
            return; 
        }

        // Ambil waktu akhir dari atribut data-endtime
        var endTime = new Date(countdownElement.getAttribute("data-endtime")).getTime();

        // Periksa apakah tanggalnya valid
        if (isNaN(endTime)) {
            countdownElement.innerHTML = "Invalid Date Format";
            return;
        }

        // Jalankan fungsi updateTimer setiap 1 detik
        var timer = setInterval(function() {
            var now = new Date().getTime();
            var distance = endTime - now;

            // Jika waktu sudah habis
            if (distance < 0) {
                clearInterval(timer);
                // Ganti seluruh isi sel menjadi "Inactive"
                countdownElement.parentElement.innerHTML = \'<span class="label label-danger">Inactive</span>\';
            } else {
                // Kalkulasi sisa waktu
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Format agar selalu 2 digit untuk jam, menit, detik (misal: 09 bukan 9)
                hours = (hours < 10) ? "0" + hours : hours;
                minutes = (minutes < 10) ? "0" + minutes : minutes;
                seconds = (seconds < 10) ? "0" + seconds : seconds;

                // Tampilkan hasilnya di elemen
                countdownElement.innerHTML = days + " Days " + hours + " Hours " + minutes + " Minutes " + seconds + " Seconds";
            }
        }, 1000); // 1000ms = 1 detik
    })();
    </script>
    ';
    // --- [END] JAVASCRIPT UNTUK COUNTDOWN ---

} else {
   // $user_points = number_format($userdata['points']) . ' VP';

    $do = trim($do);

    if (!preg_match('/^([a-zA-Z0-9\-\_]+)$/', $do)) {
        echo 'Invalid ' . $do;
        exit;
    }

    $nav = '';
    if (strpos($do, 'superadmin') !== false) {
        $nav = '/superadmin/' . $do;
    } else if (strpos($do, 'admin') !== false) {
        $nav = '/admin/' . $do;
    } else if (strpos($do,'user') !== false) {
        $nav = '/user/' . $do;
    } else if (strpos($do, 'support') !== false) {
        $nav = '/support/' . $do;
    }

    if (!file_exists('./includes/feature/' . $nav . '.php')) {
    header("location: $script_name");
    } else {
    include('./includes/feature/' . $nav . '.php');
    }

    $title = $program_name . ' - ' . $leftTitle;
    $navbits = array($script_name => $program_name, '' => $leftTitle);

    // Close all MSSQL connections, they are not needed after this point tbh
    if (isset($gamecp_dbconnect)) {
        @mssql_close($gamecp_dbconnect);
    }
    if (isset($items_dbconnect)) {
        @mssql_close($items_dbconnect);
    }
    if (isset($donate_dbconnect)) {
        @mssql_close($donate_dbconnect);
    }
    if (isset($user_dbconnect)) {
        @mssql_close($user_dbconnect);
    }
    if (isset($data_dbconnect)) {
        @mssql_close($data_dbconnect);
    }
}
gamecp_nav($isuser);

# Draw the end of this script

eval('print_outputs("' . gamecpTempalte('gamecp') . '");');