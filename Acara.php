<?php 
session_start();
include 'koneksi.php'; 

if (!isset($_SESSION['userID'])) {
    header("Location: login.php");
    exit;
}

$userID = $_SESSION['userID'];

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

$limit = 9;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$kategoriFilter = "";
$selectedKategori = "";

if(isset($_GET['kategori']) && !empty($_GET['kategori'])){
    $selectedKategori = $conn->real_escape_string($_GET['kategori']);
    $kategoriFilter = "WHERE k.nama_kategori = '$selectedKategori'";
}

if ($user_data['nama_fakultas'] == 'Fakultas Teknologi Informasi') {
    if (!empty($kategoriFilter)) {
        $kategoriFilter .= " AND a.completion_status = 'NO' AND a.tanggal_selesai >= CURDATE()";
    } else {
        $kategoriFilter = "WHERE a.completion_status = 'NO' AND a.tanggal_selesai >= CURDATE()";
    }
    
    $query = "SELECT a.*, k.nama_kategori, l.ruang, l.alamat FROM acara a 
              JOIN kategori k ON a.kategoriID = k.kategoriID 
              JOIN lokasi l ON a.lokasiID = l.lokasiID
              $kategoriFilter
              ORDER BY a.tanggal_mulai ASC
              LIMIT $start, $limit";
} else {
    if (!empty($kategoriFilter)) {
        $kategoriFilter .= " AND a.open_status = 'SEMUA' AND a.completion_status = 'NO' AND a.tanggal_selesai >= CURDATE()";
    } else {
        $kategoriFilter = "WHERE a.open_status = 'SEMUA' AND a.completion_status = 'NO' AND a.tanggal_selesai >= CURDATE()";
    }
    
    $query = "SELECT a.*, k.nama_kategori, l.ruang, l.alamat FROM acara a 
              JOIN kategori k ON a.kategoriID = k.kategoriID 
              JOIN lokasi l ON a.lokasiID = l.lokasiID
              $kategoriFilter
              ORDER BY a.tanggal_mulai ASC
              LIMIT $start, $limit";
}

$result = $conn->query($query);
if ($user_data['nama_fakultas'] == 'Fakultas Teknologi Informasi') {
    $totalQuery = "SELECT COUNT(*) as total FROM acara a JOIN kategori k ON a.kategoriID = k.kategoriID $kategoriFilter";
} else {
    if (!empty($kategoriFilter)) {
        $totalFilterNonFTI = str_replace("WHERE k.nama_kategori = '$selectedKategori' AND a.open_status = 'SEMUA'", "WHERE k.nama_kategori = '$selectedKategori' AND a.open_status = 'SEMUA'", $kategoriFilter);
    } else {
        $totalFilterNonFTI = "WHERE a.open_status = 'SEMUA' AND a.completion_status = 'NO' AND a.tanggal_selesai >= CURDATE()";
    }
    $totalQuery = "SELECT COUNT(*) as total FROM acara a JOIN kategori k ON a.kategoriID = k.kategoriID $totalFilterNonFTI";
}

$totalResult = $conn->query($totalQuery);
$total = $totalResult->fetch_assoc()['total'];
$pages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Acara Untar</title>
<link rel="stylesheet" href="css/Acara.css">
</head>
<body>
<div class="container">
<div class="menu">
    <div class="logo"><img src="images/logo.png"></div>
    <nav>
        <article class="button-container">
            <div class="tombol1"><button onclick="confirmLogout()">Log Out</button></div>
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
<div class="backbutton"><h2><a href="mainpage.php">← home</a></h2></div>
<div class="title"><h1>Acara</h1></div>

<div class="acara-section">
    <div class="filter-panel">
        <h3>Kategori Acara</h3>
        <form method="get" id="filterForm">
            <?php 
            $kategoriRes = $conn->query("SELECT nama_kategori FROM kategori ORDER BY nama_kategori");
            while($row = $kategoriRes->fetch_assoc()){
                $checked = ($selectedKategori == $row['nama_kategori']) ? "checked" : "";
                echo '<label class="radio-label">';
                echo '<input type="radio" name="kategori" value="'.$row['nama_kategori'].'" '.$checked.' onchange="this.form.submit()"> ';
                echo $row['nama_kategori'];
                echo '</label>';
            }
            ?>
            <button type="button" class="clear-btn" onclick="clearFilter()">Reset Filter</button>
        </form>
    </div>

    <div class="event-grid">
        <?php 
        if($result->num_rows > 0){
            while($row = $result->fetch_assoc()){
                $thumbnail = !empty($row['thumbnail']) ? 'images/thumbnail/' . $row['thumbnail'] : 'images/event_placeholder.jpg';
                
                echo '<div class="event-card" onclick="window.location.href=\'detailAcara.php?id='.$row['acaraID'].'\'">';
                echo '<img src="'.$thumbnail.'" alt="'.htmlspecialchars($row['nama_acara']).'">';
                
                if ($row['open_status'] == 'FTI') {
                    echo '<span class="event-badge untar">Khusus FTI</span>';
                } else {
                    echo '<span class="event-badge semua">Semua Fakultas</span>';
                }
                
                echo '<h4>'.htmlspecialchars($row['nama_acara']).'</h4>';
                echo '<p>'.date("d M Y", strtotime($row['tanggal_mulai'])).' - '.$row['ruang'].'</p>';
                echo '<div class="event-footer">';
                echo '<span class="event-quota">'.$row['total_peserta'].'/'.$row['maksimal_peserta'].' peserta</span>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="no-event">';
            echo '<p>Tidak ada acara ditemukan.</p>';
            if (!empty($selectedKategori)) {
                echo '<button class="clear-btn" onclick="clearFilter()" style="margin-top: 15px;">Reset Filter</button>';
            }
            echo '</div>';
        }
        ?>
    </div>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
<?php 
$kategoriParam = !empty($selectedKategori) ? '&kategori=' . urlencode($selectedKategori) : '';

if($page > 1){
    echo '<a href="?page='.($page-1).$kategoriParam.'" class="page-btn">«</a>';
}
for($i = 1; $i <= $pages; $i++){
    $active = ($i == $page) ? 'active' : '';
    echo '<a href="?page='.$i.$kategoriParam.'" class="page-btn '.$active.'">'.$i.'</a>';
}
if($page < $pages){
    echo '<a href="?page='.($page+1).$kategoriParam.'" class="page-btn">»</a>';
}
?>
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

function clearFilter() {
    window.location.href = 'acara.php';
}
</script>
</body>
</html>