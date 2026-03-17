<?php
/**
 * HANDLER WEBHOOK / CALLBACK
 * File ini menerima notifikasi pembayaran otomatis dari Gateway.
 * [PERBAIKAN KEAMANAN DITERAPKAN]
 */

// 1. Hubungkan ke DB Anda (sesuaikan path jika perlu)
define("IN_GAMECP_SALT58585", true); // <-- TAMBAHKAN INI
define('COMMON_INITIATED', true);
@include './includes/main/mssql_to_sqlsrv.php';
@include './includes/main/config.php'; // Memuat kredensial DB
// @include 'includes/db.php';  // File ini mungkin tidak diperlukan lagi

// TAMBAHKAN INI: Memuat file yang berisi SEMUA fungsi koneksi
@include './includes/main/function/global_functions.php';

// --- Ambil Kunci Konfigurasi ---
$CONFIG_DATA = array();
$config_sql = "SELECT config_name, config_value FROM gamecp_config WHERE config_name IN (
    'tripay_private_key', 'tripay_mode',
    'paypal_client_id', 'paypal_secret', 'paypal_mode', 
    'plisio_api_key'
)";
$config_result = @mssql_query($config_sql, $gamecp_dbconnect);
if ($config_result) {
    while ($config_row = @mssql_fetch_assoc($config_result)) {
        $CONFIG_DATA[$config_row['config_name']] = $config_row['config_value'];
    }
    @mssql_free_result($config_result);
}


// 2. Tentukan Provider
$provider = $_GET['provider'] ?? '';
$log_id = 0;
$is_paid = false;

try {
    if ($provider == 'tripay') {
        // --- HANDLE TRIPAY ---
        // (Logika ini sudah aman)
        $privateKey = $CONFIG_DATA['tripay_private_key'] ?? '';
        if (empty($privateKey)) throw new Exception('Tripay Private Key not set.');

        $json = file_get_contents("php://input");
        $header_signature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';

        // VALIDASI SIGNATURE (WAJIB!)
        $signature = hash_hmac('sha256', $json, $privateKey);
        
        if (!hash_equals($signature, $header_signature)) {
            throw new Exception('Invalid Tripay Signature');
        }

        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
             throw new Exception('Invalid JSON payload');
        }

        if (isset($data['status']) && $data['status'] == 'PAID') {
            $log_id = (int)$data['merchant_ref']; // ID log kita
            $is_paid = true;
        }

    } elseif ($provider == 'paypal') {
        // --- [PERBAIKAN] HANDLE PAYPAL (METODE VERIFIKASI SERVER-TO-SERVER) ---
        
        $json = file_get_contents("php://input");
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE && !$data) {
             throw new Exception('Invalid PayPal JSON payload');
        }

        // 1. Kita hanya peduli pada event 'CAPTURE'
        if (isset($data['event_type']) && $data['event_type'] == 'CHECKOUT.ORDER.CAPTURED') {
            
            // 2. Ambil PayPal Order ID (Bukan log_id kita)
            $paypal_order_id = $data['resource']['id'] ?? null;
            
            // 3. Ambil Log ID kita (invoice_id) DARI WEBHOOK
            $log_id_from_webhook = (int)($data['resource']['purchase_units'][0]['invoice_id'] ?? 0);

            if (!$paypal_order_id || $log_id_from_webhook <= 0) {
                throw new Exception('PayPal Webhook missing required IDs.');
            }

            // 4. Dapatkan Access Token (Fungsi helper ada di bawah)
            $accessToken = get_paypal_access_token($CONFIG_DATA);

            // 5. Buat panggilan cURL AMAN kembali ke PayPal untuk VERIFIKASI
            $mode = $CONFIG_DATA['paypal_mode'] ?? 'sandbox';
            $apiUrl = ($mode == 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl . '/v2/checkout/orders/' . $paypal_order_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $verified_data = json_decode($response, true);
            
            // 6. Lakukan Validasi Ganda
            if ($http_code == 200 && isset($verified_data['status'])) {
                
                // Cek 1: Statusnya 'COMPLETED' (atau 'CAPTURED')
                // Cek 2: invoice_id dari server PayPal SAMA DENGAN log_id dari webhook
                // Cek 3: ID order SAMA DENGAN yang kita cek
                
                $server_invoice_id = (int)($verified_data['purchase_units'][0]['invoice_id'] ?? 0);
                
                if (
                    $verified_data['status'] == 'COMPLETED' && 
                    $verified_data['id'] == $paypal_order_id &&
                    $server_invoice_id == $log_id_from_webhook
                ) {
                    // --- AMAN ---
                    $log_id = $log_id_from_webhook;
                    $is_paid = true;
                } else {
                    throw new Exception('PayPal Verification Mismatch.');
                }
            } else {
                throw new Exception('PayPal Verification Failed. HTTP ' . $http_code);
            }
        }
        // Jika event_type bukan CAPTURED, kita abaikan (return 200 OK nanti)

    } elseif ($provider == 'plisio') {
         // --- [PERBAIKAN] HANDLE PLISIO (METODE VALIDASI HASH) ---
         $apiKey = $CONFIG_DATA['plisio_api_key'] ?? '';
         if (empty($apiKey)) throw new Exception('Plisio API Key not set.');

         // 1. Dapatkan data POST
         $post_data = $_POST ?? [];
         
         // 2. Dapatkan hash dari Plisio
         $verify_hash = $post_data['verify_hash'] ?? '';
         if (empty($verify_hash)) throw new Exception('Invalid Plisio request (no hash)');
         
         // 3. Hapus hash dari data untuk perbandingan
         unset($post_data['verify_hash']);
         
         // 4. Urutkan key
         ksort($post_data);
         
         // 5. Buat string data
         $post_data_str = serialize($post_data);
         
         // 6. Buat hash tandingan
         $check_hash = hash_hmac('sha1', $post_data_str, $apiKey);

         // 7. Bandingkan hash
         if (!hash_equals($check_hash, $verify_hash)) {
             throw new Exception('Invalid Plisio Hash');
         }
         
         // --- AMAN ---
         // Jika hash valid, baru kita percayai datanya
         if (isset($post_data['status']) && $post_data['status'] == 'completed') {
             $log_id = (int)$post_data['order_number'];
             $is_paid = true;
         }
    } else {
        throw new Exception('Invalid provider');
    }

    // 3. PROSES APPROVAL
    if ($is_paid && $log_id > 0) {
        // Panggil fungsi approval (fungsi ini sudah aman)
        $success = approve_wallet_topup($log_id, strtoupper($provider) . '_SYSTEM');
        if (!$success) {
            // Log sudah diproses atau tidak ditemukan
            // Kita tetap kirim sukses agar Tripay tidak retry
        }
    } else {
         // Status bukan 'PAID' atau 'completed', abaikan saja.
    }

    // --- [PERBAIKAN] Kirim Respon JSON yang VALID untuk Tripay ---
    // Tripay IPN GAGAL jika tidak mendapat respon JSON
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    // --- AKHIR PERBAIKAN ---

} catch (Exception $e) {
    // Catat error ini ke file log
    error_log("Wallet Callback Error: " ."[" . strtoupper($provider) . "] " . $e->getMessage());

    // --- [PERBAIKAN] Kirim Respon Error JSON ---
    http_response_code(400); // Bad Request
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    // --- AKHIR PERBAIKAN ---
}


/**
 * FUNGSI APPROVAL OTOMATIS
 * (Fungsi ini tidak berubah, sudah aman)
 */
function approve_wallet_topup($log_id, $admin_id = 'SYSTEM') {
    global $gamecp_dbconnect;
    if ($log_id <= 0) return false;

    // 1. Cek apakah transaksi valid dan masih 'Pending'
    $log_result = @mssql_query("SELECT TOP 1 * FROM gamecp_wallet_topup_log WHERE id = '$log_id' AND status = 'Pending'", $gamecp_dbconnect);
    
    if ($log_result && $log_data = @mssql_fetch_array($log_result)) {
        $wallet_value = (float)$log_data['wallet_value'];
        $user_serial = (int)$log_data['account_id'];

        // 2. Cari wallet pengguna
        $check_wallet = @mssql_query("SELECT user_account_id FROM gamecp_gamepoints WHERE user_account_id = '$user_serial'", $gamecp_dbconnect);
        if (@mssql_num_rows($check_wallet) > 0) {
            $wallet_sql = "UPDATE gamecp_gamepoints SET my_wallet = ISNULL(my_wallet, 0) + $wallet_value WHERE user_account_id = '$user_serial'";
        } else {
            $wallet_sql = "INSERT INTO gamecp_gamepoints (user_account_id, my_wallet) VALUES ('$user_serial', $wallet_value)";
        }
        @mssql_free_result($check_wallet);

        // 3. Update wallet
        if(@mssql_query($wallet_sql, $gamecp_dbconnect)) {
            // 4. Update log topup
            @mssql_query("UPDATE gamecp_wallet_topup_log SET status = 'Accepted', admin_id = '$admin_id', process_date = GETDATE() WHERE id = '$log_id'", $gamecp_dbconnect);
            return true;
        }
    }
    return false;
}


/**
 * [HELPER BARU] Mendapatkan PayPal Access Token
 * Diperlukan untuk verifikasi server-to-server.
 */
function get_paypal_access_token($CONFIG_DATA) {
    $clientID = $CONFIG_DATA['paypal_client_id'] ?? '';
    $secret = $CONFIG_DATA['paypal_secret'] ?? '';
    $mode = $CONFIG_DATA['paypal_mode'] ?? 'sandbox';
    $apiUrl = ($mode == 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

    if (empty($clientID) || empty($secret)) {
        throw new Exception('PayPal Auth Keys not set.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $clientID . ':' . $secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
    
    $auth_response = curl_exec($ch);
    $auth_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $auth_data = json_decode($auth_response, true);

    if ($auth_http_code != 200 || empty($auth_data['access_token'])) {
        throw new Exception("PayPal Auth Error (" . $auth_http_code . "): " . ($auth_data['error_description'] ?? 'Failed to get token'));
    }
    
    return $auth_data['access_token'];
}

?>