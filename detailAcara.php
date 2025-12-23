<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION['userID'];
$success = '';
$error = '';

if (!isset($_GET['id'])) {
    header("Location: acara.php");
    exit;
}

$acaraID = $_GET['id'];

$user_query = $conn->query("SELECT u.*, r.nama_role, f.nama_fakultas FROM user u JOIN roles r ON u.rolesID = r.rolesID LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID WHERE u.userID = '$userID'");
$user_data = $user_query->fetch_assoc();

if ($user_data['nama_role'] === 'admin') {
    $displayRole = 'Admin';
} else {
    $fakultas_singkat = [
        'Fakultas Teknik' => 'FT',
        'Fakultas Seni Rupa dan Desain' => 'FSRD',
        'Fakultas Ekonomi dan Bisnis' => 'FEB',
        'Fakultas Ilmu Komunikasi' => 'FIKOM',
        'Fakultas Kedokteran' => 'FK',
        'Fakultas Hukum' => 'FH',
        'Fakultas Psikologi' => 'FPsi',
        'Fakultas Teknologi Informasi' => 'FTI'
    ];
    
    $singkatan = isset($fakultas_singkat[$user_data['nama_fakultas']]) 
                 ? $fakultas_singkat[$user_data['nama_fakultas']] 
                 : 'Mahasiswa';
    
    $displayRole = 'Mahasiswa (' . $singkatan . ')';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar'])) {
    if ($user_data['nama_role'] === 'admin') {
        $error = "Admin tidak dapat mendaftar acara. Fitur ini hanya untuk peserta.";
    } else {
        $stmt_check = $conn->prepare("SELECT registrasiID FROM registrasi WHERE userID = ? AND acaraID = ?");
        $stmt_check->bind_param("ss", $userID, $acaraID);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "Anda sudah terdaftar di acara ini!";
        } else {
            $stmt_kuota = $conn->prepare("SELECT total_peserta, maksimal_peserta, open_status FROM acara WHERE acaraID = ?");
            $stmt_kuota->bind_param("s", $acaraID);
            $stmt_kuota->execute();
            $result_kuota = $stmt_kuota->get_result();
            $acara_kuota = $result_kuota->fetch_assoc();

            if ($acara_kuota['open_status'] == 'FTI' && $user_data['fakultas'] != 'Fakultas Teknologi Informasi') {
                $error = "Maaf, acara ini hanya untuk mahasiswa Fakultas Teknologi Informasi!";
            } elseif ($acara_kuota['total_peserta'] >= $acara_kuota['maksimal_peserta']) {
                $error = "Maaf, kuota acara sudah penuh!";
            } else {
                $result_reg = $conn->query("SELECT registrasiID FROM registrasi ORDER BY registrasiID DESC LIMIT 1");
                if ($result_reg->num_rows > 0) {
                    $last = $result_reg->fetch_assoc();
                    $num = intval(substr($last['registrasiID'], 3)) + 1;
                    $registrasiID = 'REG' . str_pad($num, 5, '0', STR_PAD_LEFT);
                } else {
                    $registrasiID = 'REG00001';
                }
                
                $tanggal_daftar = date('Y-m-d H:i:s');

                $stmt_insert = $conn->prepare("INSERT INTO registrasi (registrasiID, userID, acaraID, tanggal_daftar) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("ssss", $registrasiID, $userID, $acaraID, $tanggal_daftar);
                
                if ($stmt_insert->execute()) {
                    $stmt_update = $conn->prepare("UPDATE acara SET total_peserta = total_peserta + 1 WHERE acaraID = ?");
                    $stmt_update->bind_param("s", $acaraID);
                    $stmt_update->execute();
                    $stmt_update->close();
                    
                    $success = "Pendaftaran berhasil! Anda telah terdaftar di acara ini.";
                } else {
                    $error = "Gagal mendaftar: " . $conn->error;
                }
                $stmt_insert->close();
            }
            $stmt_kuota->close();
        }
        $stmt_check->close();
    }
}

$stmt = $conn->prepare("
    SELECT 
        a.acaraID,
        a.nama_acara,
        a.deskripsi,
        a.tanggal_mulai,
        a.tanggal_selesai,
        a.jam_mulai,
        a.jam_selesai,
        a.organisasi,
        a.total_peserta,
        a.maksimal_peserta,
        a.open_status,
        a.thumbnail,
        k.nama_kategori,
        sk.nama_subKategori,
        l.ruang,
        l.alamat
    FROM acara a
    JOIN kategori k ON a.kategoriID = k.kategoriID
    JOIN subKategori sk ON a.subKategoriID = sk.subKategoriID
    JOIN lokasi l ON a.lokasiID = l.lokasiID
    WHERE a.acaraID = ?
");
$stmt->bind_param("s", $acaraID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: acara.php");
    exit;
}

$acara = $result->fetch_assoc();
$stmt->close();

$can_register = true;
$register_message = '';

if ($user_data['nama_role'] === 'admin') {
    $can_register = false;
    $register_message = 'Admin tidak dapat mendaftar acara';
}
elseif ($acara['open_status'] == 'FTI' && $user_data['fakultas'] != 'Fakultas Teknologi Informasi') {
    $can_register = false;
    $register_message = 'Acara ini hanya untuk mahasiswa Fakultas Teknologi Informasi';
}

$is_registered = false;
if ($user_data['nama_role'] !== 'admin') {
    $stmt_registered = $conn->prepare("SELECT registrasiID FROM registrasi WHERE userID = ? AND acaraID = ?");
    $stmt_registered->bind_param("ss", $userID, $acaraID);
    $stmt_registered->execute();
    $is_registered = $stmt_registered->get_result()->num_rows > 0;
    $stmt_registered->close();
}

function formatTanggalIndo($date) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $split = explode('-', $date);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

$kuota_tersisa = $acara['maksimal_peserta'] - $acara['total_peserta'];
$thumbnail = !empty($acara['thumbnail']) ? 'images/thumbnail/' . $acara['thumbnail'] : 'images/acara-placeholder.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Acara - <?php echo htmlspecialchars($acara['nama_acara']); ?></title>
    <link rel="stylesheet" href="css/detailAcara.css">
</head>
<body>
<div class="container">
    <div class="menu">
        <div class="logo">
            <img src="images/logo.png" alt="FTI Untar Logo">
        </div>
        <nav>
            <article class="button-container">
                <div class="tombol1">
                    <button onclick="confirmLogout()">Log Out</button>
                </div>
                <div class="tombol2">
                    <div class="icon-text">
                        <img src="images/pp.png" alt="User Icon">
                        <div class="text">
                            <span class="top-text"><?php echo htmlspecialchars($user_data['nama']); ?></span>
                            <span class="bottom-text"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>
            </article>
        </nav>
    </div>

    <div class="body">
        <div class="backbutton">
            <h2><a href="acara.php">← Kembali ke Daftar Acara</a></h2>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile">
            <div class="profilekiri">
                <div style="display: inline-block; margin-bottom: 15px;">
                    <span class="badge-status <?php echo strtolower($acara['open_status']); ?>">
                        <?php 
                        if ($acara['open_status'] == 'FTI') {
                            echo 'Khusus FTI';
                        } else {
                            echo 'Semua Fakultas';
                        }
                        ?>
                    </span>
                </div>
                <h2><?php echo htmlspecialchars($acara['nama_kategori']) . ' - ' . htmlspecialchars($acara['nama_subKategori']); ?></h2>
                <h1><?php echo htmlspecialchars($acara['nama_acara']); ?></h1>
                <div class="info-detail">
                    <div class="info-item">
                        <span class="label">Tanggal Mulai:</span>
                        <span class="value"><?php echo formatTanggalIndo($acara['tanggal_mulai']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Tanggal Selesai:</span>
                        <span class="value"><?php echo formatTanggalIndo($acara['tanggal_selesai']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Lokasi:</span>
                        <span class="value"><?php echo htmlspecialchars($acara['ruang']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Alamat:</span>
                        <span class="value"><?php echo htmlspecialchars($acara['alamat']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Kuota:</span>
                        <span class="value"><?php echo $acara['total_peserta'] . ' / ' . $acara['maksimal_peserta'] . ' Peserta'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Sisa Kuota:</span>
                        <span class="value <?php echo $kuota_tersisa <= 5 ? 'text-warning' : ''; ?>">
                            <?php echo $kuota_tersisa . ' Peserta'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label">Jam Acara:</span>
                        <span class="value"><?php echo date('H:i', strtotime($acara['jam_mulai'])) . ' - ' . date('H:i', strtotime($acara['jam_selesai'])); ?> WIB</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Organisasi:</span>
                        <span class="value"><?php echo htmlspecialchars($acara['organisasi']); ?></span>
                    </div>
                </div>
                <p class="description">
                    <?php echo nl2br(htmlspecialchars($acara['deskripsi'])); ?>
                </p>
                
                <?php 
                if ($user_data['nama_role'] === 'admin'): 
                ?>
                    <button class="btn-admin-view" disabled>
                        Admin tidak dapat mendaftar
                    </button>
                    <p class="info-text">Anda login sebagai Admin. Gunakan akun peserta untuk mendaftar acara.</p>
                
                <?php elseif (!$can_register && $acara['open_status'] == 'FTI' && $user_data['fakultas'] != 'Fakultas Teknologi Informasi'): ?>
                    <button class="btn-access-denied" disabled>
                       <?php echo $register_message; ?>
                    </button>
                    <p class="info-text">Acara ini hanya dapat diikuti oleh mahasiswa Fakultas Teknologi Informasi.</p>
                
                <?php elseif ($is_registered): ?>
                    <button class="btn-registered" disabled>✓ Sudah Terdaftar</button>
                    <p class="info-text">Anda sudah terdaftar di acara ini.</p>
                
                <?php elseif ($kuota_tersisa <= 0): ?>
                    <button class="btn-full" disabled>✖ Kuota Penuh</button>
                    <p class="info-text">Maaf, kuota peserta sudah terpenuhi.</p>
                
                <?php else: ?>
                    <button class="btn-daftar" onclick="showConfirmation()">Daftar Sekarang</button>
                
                <?php endif; ?>
            </div>
            <div class="profilekanan">
                <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($acara['nama_acara']); ?>">
            </div>
        </div>
    </div>

    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <h2>Konfirmasi Pendaftaran</h2>
            <p>Apakah Anda yakin ingin mendaftar acara ini?</p>
            <p style="font-size: 14px; color: #666; margin-top: 10px;">
                <strong><?php echo htmlspecialchars($acara['nama_acara']); ?></strong><br>
                <?php echo formatTanggalIndo($acara['tanggal_mulai']); ?>
            </p>
            <form method="POST" action="">
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeConfirmation()">Batal</button>
                    <button type="submit" name="daftar" class="btn-confirm">Ya, Daftar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmLogout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        }

        function showConfirmation() {
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmation() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeConfirmation();
            }
        }
    </script>
</div>
</body>
</html>