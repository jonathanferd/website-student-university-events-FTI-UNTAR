<?php
$host = 'skripsi-blackjack.b.aivencloud.com';
$port = 24455;
$dbname = 'acaraFTI';
$user = 'avnadmin';
$ssl_ca = __DIR__ . '/ca.pem';

try {
    $conn = new mysqli($host, $user, $password, $dbname, $port);
    
    $conn->ssl_set(NULL, NULL, $ssl_ca, NULL, NULL);
    
    if ($conn->connect_error) {
        throw new Exception("Koneksi gagal: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Error koneksi ke database: " . $e->getMessage());
}
?>
