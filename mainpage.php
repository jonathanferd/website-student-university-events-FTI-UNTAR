<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

// Fetch user data dengan role
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

$acara_terbaru = $conn->query("SELECT a.*, l.ruang, k.nama_kategori FROM acara a 
                               JOIN lokasi l ON a.lokasiID = l.lokasiID 
                               JOIN kategori k ON a.kategoriID = k.kategoriID 
                               WHERE a.tanggal_mulai >= CURDATE() 
                               AND a.completion_status = 'NO'
                               ORDER BY a.tanggal_mulai ASC 
                               LIMIT 3");

$total_acara = $conn->query("SELECT COUNT(*) as total FROM acara WHERE tanggal_mulai >= CURDATE() AND completion_status = 'NO'")->fetch_assoc()['total'];
$acara_bulan_ini = $conn->query("SELECT COUNT(*) as total FROM acara WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) AND YEAR(tanggal_mulai) = YEAR(CURDATE()) AND completion_status = 'NO'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTI Untar - Sistem Manajemen Acara</title>
    <link rel="stylesheet" href="css/mainpage.css">
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

        <div class="main-content">
            <aside class="sidebar">
                <ul>
                    <li><a href="mainpage.php" class="active">Home</a></li>
                    <li><a href="acara.php">Acara</a></li>
                    <li><a href="sertifikat.php">Sertifikat</a></li>
                    <?php if($user_data['nama_role'] === 'admin'): ?>
                    <li><a href="dashboard.php">Admin Panel</a></li>
                    <?php endif; ?>
                </ul>
            </aside>

            <section class="content">
                <div class="welcome-section">
                    <h1>Selamat Datang di Sistem Manajemen Acara FTI Untar</h1>
                    <p>Platform terpadu untuk pendaftaran dan pengelolaan acara mahasiswa Fakultas Teknologi Informasi</p>
                </div>

                <div class="stats-container">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $total_acara; ?></div>
                        <div class="stat-label">Acara Mendatang</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $acara_bulan_ini; ?></div>
                        <div class="stat-label">Acara Bulan Ini</div>
                    </div>
                </div>

                <div class="section-header">
                    <h2>Acara Terbaru</h2>
                    <a href="acara.php" class="view-all">Lihat Semua →</a>
                </div>

                <div class="acara-grid">
                    <?php if($acara_terbaru->num_rows > 0): ?>
                        <?php while($acara = $acara_terbaru->fetch_assoc()): ?>
                        <div class="acara-card" onclick="window.location.href='detailAcara.php?id=<?php echo $acara['acaraID']; ?>'">
                            <div class="acara-badge"><?php echo $acara['nama_kategori']; ?></div>
                            <h3><?php echo htmlspecialchars($acara['nama_acara']); ?></h3>
                            <div class="acara-info">
                                <div class="info-item">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M12.6667 2.66667H3.33333C2.59695 2.66667 2 3.26362 2 4V13.3333C2 14.0697 2.59695 14.6667 3.33333 14.6667H12.6667C13.403 14.6667 14 14.0697 14 13.3333V4C14 3.26362 13.403 2.66667 12.6667 2.66667Z" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10.6667 1.33333V4" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M5.33333 1.33333V4" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M2 6.66667H14" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php echo date('d M Y', strtotime($acara['tanggal_mulai'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 14C11.3137 14 14 11.3137 14 8C14 4.68629 11.3137 2 8 2C4.68629 2 2 4.68629 2 8C2 11.3137 4.68629 14 8 14Z" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M8 4V8L10.6667 9.33333" stroke="#662D91" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php echo $acara['ruang']; ?></span>
                                </div>
                            </div>
                            <div class="acara-quota">
                                <span><?php echo $acara['total_peserta']; ?>/<?php echo $acara['maksimal_peserta']; ?> Peserta</span>
                                <div class="quota-bar">
                                    <div class="quota-fill" style="width: <?php echo ($acara['total_peserta']/$acara['maksimal_peserta'])*100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-acara">
                            <p>Belum ada acara yang tersedia saat ini.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <script>
        function confirmLogout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>