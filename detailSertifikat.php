<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION['userID'];

if (!isset($_GET['id']) || !isset($_GET['acara'])) {
    header("Location: sertifikat.php");
    exit;
}

$registrasiID = $_GET['id'];
$acaraID = $_GET['acara'];

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

$stmt = $conn->prepare("
    SELECT 
        u.nama,
        u.NIM,
        f.nama_fakultas as fakultas,
        a.acaraID,
        a.nama_acara,
        a.tanggal_mulai,
        a.tanggal_selesai,
        a.sertifikatID,
        s.sertifikatID as cert_id_check,
        s.template_sertifikat
    FROM registrasi r
    INNER JOIN user u ON r.userID = u.userID
    LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID
    INNER JOIN acara a ON r.acaraID = a.acaraID
    INNER JOIN absensi ab ON r.registrasiID = ab.registrasiID
    LEFT JOIN sertifikat s ON a.sertifikatID = s.sertifikatID
    WHERE r.registrasiID = ? 
    AND r.userID = ? 
    AND a.acaraID = ?
");
$stmt->bind_param("sss", $registrasiID, $userID, $acaraID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Akses ditolak! Anda tidak memiliki akses ke sertifikat ini.");
}

$data = $result->fetch_assoc();
$stmt->close();

$template_path = '';
$template_exists = false;
$error_message = '';

if (empty($data['sertifikatID'])) {
    $error_message = "Acara ini belum memiliki template sertifikat yang ditetapkan.";
} elseif (empty($data['template_sertifikat'])) {
    $error_message = "Template sertifikat belum di-upload untuk acara ini.";
} else {
    $template_path = 'images/sertifikat/' . $data['template_sertifikat'];
    
    if (file_exists($template_path)) {
        $template_exists = true;
    } else {
        $error_message = "File template sertifikat tidak ditemukan di server: " . htmlspecialchars($data['template_sertifikat']);
    }
}

function formatTanggalIndo($date) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $split = explode('-', $date);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

$debug_mode = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat - <?php echo htmlspecialchars($data['nama_acara']); ?></title>
    <link rel="stylesheet" href="css/detailSertifikat.css">
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
            <h2><a href="sertifikat.php">← Kembali ke Sertifikat Saya</a></h2>
        </div>

        <?php if ($debug_mode): ?>
        <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 10px; margin-bottom: 20px; font-family: monospace; font-size: 13px;">
            <strong style="font-size: 16px;">🔍 DEBUG INFO:</strong><br><br>
            
            <strong>Request Data:</strong><br>
            - Registration ID: <?php echo htmlspecialchars($registrasiID); ?><br>
            - Acara ID: <?php echo htmlspecialchars($acaraID); ?><br>
            - User ID: <?php echo htmlspecialchars($userID); ?><br>
            <br>
            
            <strong>Acara Data:</strong><br>
            - Acara ID (dari DB): <?php echo htmlspecialchars($data['acaraID']); ?><br>
            - Nama Acara: <?php echo htmlspecialchars($data['nama_acara']); ?><br>
            <br>
            
            <strong>Sertifikat Data:</strong><br>
            - Sertifikat ID (FK di Acara): <?php echo htmlspecialchars($data['sertifikatID'] ?: 'NULL'); ?><br>
            - Sertifikat ID (Check): <?php echo htmlspecialchars($data['cert_id_check'] ?: 'NULL'); ?><br>
            - Template Filename: <?php echo htmlspecialchars($data['template_sertifikat'] ?: 'NULL'); ?><br>
            - Template Path: <?php echo htmlspecialchars($template_path ?: 'N/A'); ?><br>
            - File Exists: <?php echo $template_exists ? ' YES' : ' NO'; ?><br>
            <?php if ($template_path): ?>
            - Full Path: <?php echo realpath($template_path) ?: 'File not found'; ?><br>
            <?php endif; ?>
            <br>
            
            <strong>👤 User Data:</strong><br>
            - Nama: <?php echo htmlspecialchars($data['nama']); ?><br>
            - NIM: <?php echo htmlspecialchars($data['NIM'] ?: 'N/A'); ?><br>
            - Fakultas: <?php echo htmlspecialchars($data['fakultas']); ?><br>
        </div>
        <?php endif; ?>

        <div class="certificate-wrapper">
            <h1 class="page-title">Sertifikat Kehadiran</h1>
            
            <?php if (!$template_exists): ?>
                <div class="alert alert-error">
                    <p> <?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="certificate-container">
                <div class="certificate-preview">
                    <div id="certificate">
                        <?php if ($template_exists): ?>
                            <img src="<?php echo $template_path; ?>?t=<?php echo time(); ?>" 
                                 alt="Sertifikat <?php echo htmlspecialchars($data['nama_acara']); ?>" 
                                 class="certificate-bg" 
                                 crossorigin="anonymous">
                            
                            <div class="certificate-overlay">
                                <div class="certificate-name">
                                    <?php echo strtoupper(htmlspecialchars($data['nama'])); ?>
                                </div>

                                <?php if (!empty($data['NIM'])): ?>
                                    <div class="certificate-nim">
                                        NIM: <?php echo htmlspecialchars($data['NIM']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="certificate-event">
                                    "<?php echo htmlspecialchars($data['nama_acara']); ?>"
                                </div>
                                
                                <div class="certificate-date">
                                    <?php echo formatTanggalIndo($data['tanggal_mulai']); ?>
                                    <?php if ($data['tanggal_mulai'] != $data['tanggal_selesai']): ?>
                                        - <?php echo formatTanggalIndo($data['tanggal_selesai']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="certificate-placeholder">
                                <p>⚠️ Template Tidak Tersedia</p>
                                <small><?php echo htmlspecialchars($data['template_sertifikat'] ?: 'Tidak ada template'); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($template_exists): ?>
                <div class="download-section">
                    <div class="download-buttons">
                        <button class="btn-download" onclick="downloadPDF()">
                            <span>📥</span> Download PDF
                        </button>
                        <button class="btn-download-jpg" onclick="downloadJPG()">
                            <span>🖼️</span> Download JPG
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
function confirmLogout() {
    if (confirm('Apakah Anda yakin ingin logout?')) {
        window.location.href = 'logout.php';
    }
}

function downloadJPG() {
    const certificate = document.getElementById('certificate');
    
    html2canvas(certificate, {
        scale: 2, 
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Sertifikat_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['nama']); ?>_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['nama_acara']); ?>.jpg';
        link.href = canvas.toDataURL('image/jpeg', 1.0);
        link.click();
    }).catch(error => {
        console.error('Error:', error);
        alert('Gagal membuat sertifikat JPG. Error: ' + error.message);
    });
}

function downloadPDF() {
    const certificate = document.getElementById('certificate');
    
    html2canvas(certificate, {
        scale: 2, 
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/jpeg', 1.0);
        const { jsPDF } = window.jspdf;

        const canvasWidth = canvas.width;
        const canvasHeight = canvas.height;
        const ratio = canvasWidth / canvasHeight;

        let pdfWidth, pdfHeight;
        
        if (ratio > 1) {
            pdfWidth = 297; 
            pdfHeight = 297 / ratio;
        } else {
            pdfHeight = 210; 
            pdfWidth = 210 * ratio;
        }
        
        const pdf = new jsPDF({
            orientation: ratio > 1 ? 'landscape' : 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        
        const xOffset = (pdf.internal.pageSize.getWidth() - pdfWidth) / 2;
        const yOffset = (pdf.internal.pageSize.getHeight() - pdfHeight) / 2;
        
        pdf.addImage(imgData, 'JPEG', xOffset, yOffset, pdfWidth, pdfHeight, '', 'FAST');
        
        pdf.save('Sertifikat_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['nama']); ?>_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['nama_acara']); ?>.pdf');
    }).catch(error => {
        console.error('Error:', error);
        alert('Gagal membuat PDF. Error: ' + error.message);
    });
}
</script>

</body>
</html>