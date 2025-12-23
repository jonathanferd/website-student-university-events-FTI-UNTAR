<?php
session_start();
include 'koneksi.php';

$error = '';
$success = '';

if (!isset($_GET['acara'])) {
    die("Acara tidak ditemukan!");
}

$acaraID = $_GET['acara'];
$acara = $conn->query("SELECT * FROM acara WHERE acaraID = '$acaraID'")->fetch_assoc();

if (!$acara) {
    die("Acara tidak ditemukan!");
}

date_default_timezone_set('Asia/Jakarta');

$tanggal_mulai = strtotime(date('Y-m-d', strtotime($acara['tanggal_mulai'])));
$tanggal_selesai = strtotime(date('Y-m-d', strtotime($acara['tanggal_selesai'])));
$tanggal_sekarang = strtotime(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT u.userID, u.nama, u.email, u.NIM, u.password, u.fakultasID, f.nama_fakultas 
                            FROM user u 
                            LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID 
                            WHERE u.email = ? OR u.NIM = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        

        if (password_verify($password, $user['password'])) {

            $stmt_reg = $conn->prepare("SELECT registrasiID FROM registrasi WHERE userID = ? AND acaraID = ?");
            $stmt_reg->bind_param("ss", $user['userID'], $acaraID);
            $stmt_reg->execute();
            $result_reg = $stmt_reg->get_result();
            
            if ($result_reg->num_rows == 0) {
                $error = "Anda belum terdaftar di acara ini! Silakan daftar terlebih dahulu.";
                $stmt_reg->close();
            } else {
                $registrasi_data = $result_reg->fetch_assoc();
                $registrasiID = $registrasi_data['registrasiID'];
                $stmt_reg->close();
                
                if ($tanggal_sekarang < $tanggal_mulai) {
                    $error = "Absensi belum dibuka. Acara dimulai pada: " . date('d F Y', strtotime($acara['tanggal_mulai']));
                } elseif ($tanggal_sekarang > $tanggal_selesai) {
                    $error = "Absensi sudah ditutup. Acara selesai pada: " . date('d F Y', strtotime($acara['tanggal_selesai']));
                } else {
                    $stmt_absen = $conn->prepare("SELECT absensiID FROM absensi WHERE registrasiID = ?");
                    $stmt_absen->bind_param("s", $registrasiID);
                    $stmt_absen->execute();
                    $result_absen = $stmt_absen->get_result();
                    
                    if ($result_absen->num_rows > 0) {
                        $error = "Anda sudah melakukan absensi untuk acara ini!";
                        $stmt_absen->close();
                    } else {
                        $stmt_absen->close();

                        $result_id = $conn->query("SELECT absensiID FROM absensi ORDER BY absensiID DESC LIMIT 1");
                        if ($result_id->num_rows > 0) {
                            $last = $result_id->fetch_assoc();
                            $num = intval(substr($last['absensiID'], 3)) + 1;
                            $absensiID = 'ABS' . str_pad($num, 2, '0', STR_PAD_LEFT);
                        } else {
                            $absensiID = 'ABS01';
                        }
                        
                        $waktu_absen = date('Y-m-d H:i:s');
                        
                        $insert = $conn->prepare("INSERT INTO absensi (absensiID, registrasiID, waktu_absen) VALUES (?, ?, ?)");
                        $insert->bind_param("sss", $absensiID, $registrasiID, $waktu_absen);
                        
                        if ($insert->execute()) {
                            $success = "✅ Absensi berhasil! Terima kasih telah hadir.";
                        } else {
                            $error = "Gagal menyimpan absensi: " . $conn->error;
                        }
                        $insert->close();
                    }
                }
            }
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "NIM/Email tidak ditemukan!";
    }
    $stmt->close();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - FTI Untar</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="container">
    <div class="menu">
        <div class="logo">
            <img src="images/logo.png" alt="FTI Untar Logo">
        </div>
    </div>

    <img src="images/untar.jpeg" class="background-img" alt="Background">
    
    <div class="login-box">
        <h2>Absensi Acara</h2>
        <div style="background: #662D911A; padding: 15px; border-radius: 10px; margin-bottom: 25px;">
            <p style="color: #662D91; font-size: 16px; font-weight: 600; margin-bottom: 5px;">
                <?php echo htmlspecialchars($acara['nama_acara']); ?>
            </p>
            <p style="color: #666; font-size: 14px;">
                 <?php echo date('d F Y', strtotime($acara['tanggal_mulai'])); ?>
                <?php if ($acara['tanggal_mulai'] != $acara['tanggal_selesai']): ?>
                    - <?php echo date('d F Y', strtotime($acara['tanggal_selesai'])); ?>
                <?php endif; ?>
            </p>
            <p style="color: #666; font-size: 13px; margin-top: 5px;">
                <?php echo htmlspecialchars($acara['open_status'] == 'FTI' ? 'Khusus FTI' : 'Terbuka Semua Fakultas'); ?>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="success-message" style="background: #d4edda; border: 2px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                <p style="margin: 0; font-weight: 600; font-size: 15px;"><?php echo $success; ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message" style="background: #f8d7da; border: 2px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                <p style="margin: 0; font-weight: 600; font-size: 15px;"><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="identifier" placeholder="NIM atau Email" required autofocus>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-confirm">ABSEN SEKARANG</button>
        </form>
        <p style="text-align: center; margin-top: 15px; font-size: 13px; color: #666;">
            Gunakan akun yang sudah terdaftar di acara ini
        </p>
        <?php else: ?>
        <button class="btn-confirm" onclick="window.close()" style="background: #28a745;">Tutup Halaman</button>
        <?php endif; ?>
    </div>
</div>
</body>
</html>