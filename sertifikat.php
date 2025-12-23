<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['userID'];

$user_query = $conn->query("SELECT u.*, r.nama_role, f.nama_fakultas 
                            FROM user u 
                            JOIN roles r ON u.rolesID = r.rolesID 
                            LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID 
                            WHERE u.userID = '$userID'");
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

$limit = 6; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$sertifikat_list = $conn->query("
    SELECT 
        r.registrasiID,
        a.acaraID,
        a.nama_acara,
        a.tanggal_mulai,
        a.tanggal_selesai,
        a.sertifikatID,
        s.template_sertifikat,
        ab.waktu_absen,
        ab.absensiID
    FROM registrasi r
    INNER JOIN acara a ON r.acaraID = a.acaraID
    LEFT JOIN sertifikat s ON a.sertifikatID = s.sertifikatID
    INNER JOIN absensi ab ON r.registrasiID = ab.registrasiID
    WHERE r.userID = '$userID'
    ORDER BY a.tanggal_selesai DESC
    LIMIT $start, $limit
");

$total_query = $conn->query("
    SELECT COUNT(*) as total 
    FROM registrasi r
    INNER JOIN absensi ab ON r.registrasiID = ab.registrasiID
    WHERE r.userID = '$userID'
");
$total = $total_query->fetch_assoc()['total'];
$pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Saya - FTI Untar</title>
    <link rel="stylesheet" href="css/sertifikat.css">
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
                <h2><a href="mainpage.php">← home</a></h2>
            </div>
            <div class="title">
                <h1>Sertifikat Saya</h1>
                <?php if ($total > 0): ?>
                <p class="subtitle">Total: <?php echo $total; ?> Sertifikat</p>
                <?php endif; ?>
            </div>

            <div class="certificate-list">
                <?php if($sertifikat_list->num_rows > 0): ?>
                    <?php while($cert = $sertifikat_list->fetch_assoc()): ?>
                    <div class="certificate-item">
                        <div class="certificate-info">
                            <h3><?php echo htmlspecialchars($cert['nama_acara']); ?></h3>
                            <p class="date">Tanggal: <?php echo date('d F Y', strtotime($cert['tanggal_mulai'])); ?> - <?php echo date('d F Y', strtotime($cert['tanggal_selesai'])); ?></p>
                            <p class="date">Kehadiran: <?php echo date('d F Y H:i', strtotime($cert['waktu_absen'])); ?></p>
                            <p class="status completed">Selesai</p>
                        </div>
                        <button class="btn-download" onclick="window.location.href='detailSertifikat.php?id=<?php echo $cert['registrasiID']; ?>&acara=<?php echo $cert['acaraID']; ?>'">
                            Lihat Sertifikat
                        </button>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-certificate">
                        <p>Belum ada sertifikat yang tersedia.</p>
                        <p style="font-size: 14px; color: #a5a4a4ff; margin-top: 10px;">Sertifikat akan muncul setelah Anda mengikuti acara dan melakukan absensi.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo ($page-1); ?>" class="page-btn">« Sebelumnya</a>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?page=1" class="page-btn">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $pages): ?>
                    <?php if ($end_page < $pages - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                    <a href="?page=<?php echo $pages; ?>" class="page-btn"><?php echo $pages; ?></a>
                <?php endif; ?>
                
                <?php if($page < $pages): ?>
                    <a href="?page=<?php echo ($page+1); ?>" class="page-btn">selanjutnya »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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