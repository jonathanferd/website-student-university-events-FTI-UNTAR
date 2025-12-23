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

if ($user_data['nama_role'] !== 'admin') {
    header("Location: mainpage.php");
    exit;
}

$displayRole = 'Admin';

$success = '';
$error = '';
$show_qr = false;
$qr_data = null;

if (isset($_GET['generate_qr']) && !empty($_GET['generate_qr'])) {
    $acaraID = $_GET['generate_qr'];
    $acara_qr = $conn->query("SELECT * FROM acara WHERE acaraID = '$acaraID'")->fetch_assoc();
    
    if ($acara_qr) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $current_dir = dirname($_SERVER['PHP_SELF']);
        $absensi_url = $base_url . $current_dir . "/absensi.php?acara=" . $acaraID;
        
        $qr_data = [
            'acara' => $acara_qr,
            'qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($absensi_url)
        ];
        $show_qr = true;
    }
}

if (isset($_GET['delete_acara'])) {
    $acaraID = $_GET['delete_acara'];
    
    $conn->query("DELETE ab FROM absensi ab 
                  INNER JOIN registrasi r ON ab.registrasiID = r.registrasiID 
                  WHERE r.acaraID = '$acaraID'");
    
    $conn->query("DELETE FROM registrasi WHERE acaraID = '$acaraID'");
    
    $get_thumbnail = $conn->query("SELECT thumbnail FROM acara WHERE acaraID = '$acaraID'");
    if ($thumb_data = $get_thumbnail->fetch_assoc()) {
        if (!empty($thumb_data['thumbnail'])) {
            $thumb_path = 'images/thumbnail/' . $thumb_data['thumbnail'];
            if (file_exists($thumb_path)) {
                unlink($thumb_path);
            }
        }
    }
    $stmt = $conn->prepare("DELETE FROM acara WHERE acaraID = ?");
    $stmt->bind_param("s", $acaraID);
    if ($stmt->execute()) {
        $success = "Acara dan semua data terkait berhasil dihapus!";
        header("Location: dashboard.php?success=" . urlencode($success));
        exit;
    } else {
        $error = "Gagal menghapus acara: " . $conn->error;
    }
    $stmt->close();
}

if (isset($_GET['complete_acara'])) {
    $acaraID = $_GET['complete_acara'];
    
    $stmt = $conn->prepare("UPDATE acara SET completion_status = 'YES' WHERE acaraID = ?");
    $stmt->bind_param("s", $acaraID);
    if ($stmt->execute()) {
        $success = "Acara berhasil ditandai selesai!";
        header("Location: dashboard.php?success=" . urlencode($success));
        exit;
    } else {
        $error = "Gagal menandai acara selesai!";
    }
    $stmt->close();
}

if (isset($_GET['delete_lokasi'])) {
    $lokasiID = $_GET['delete_lokasi'];
    $check = $conn->query("SELECT COUNT(*) as count FROM acara WHERE lokasiID = '$lokasiID'");
    $result = $check->fetch_assoc();
    if ($result['count'] > 0) {
        $error = "Lokasi tidak bisa dihapus karena masih digunakan di acara!";
    } else {
        $stmt = $conn->prepare("DELETE FROM lokasi WHERE lokasiID = ?");
        $stmt->bind_param("s", $lokasiID);
        if ($stmt->execute()) {
            $success = "Lokasi berhasil dihapus!";
            header("Location: dashboard.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menghapus lokasi!";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_sertifikat'])) {
    $sertifikatID = $_GET['delete_sertifikat'];
    $check = $conn->query("SELECT COUNT(*) as count FROM acara WHERE sertifikatID = '$sertifikatID'");
    $result = $check->fetch_assoc();
    if ($result['count'] > 0) {
        $error = "Sertifikat tidak bisa dihapus karena masih digunakan di acara!";
    } else {
        $get_file = $conn->query("SELECT template_sertifikat FROM sertifikat WHERE sertifikatID = '$sertifikatID'");
        if ($file_data = $get_file->fetch_assoc()) {
            $file_path = 'images/sertifikat/' . $file_data['template_sertifikat'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM sertifikat WHERE sertifikatID = ?");
        $stmt->bind_param("s", $sertifikatID);
        if ($stmt->execute()) {
            $success = "Sertifikat berhasil dihapus!";
            header("Location: dashboard.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menghapus sertifikat!";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_kategori'])) {
    $kategoriID = $_GET['delete_kategori'];
    $check1 = $conn->query("SELECT COUNT(*) as count FROM acara WHERE kategoriID = '$kategoriID'");
    $check2 = $conn->query("SELECT COUNT(*) as count FROM subKategori WHERE kategoriID = '$kategoriID'");
    $result1 = $check1->fetch_assoc();
    $result2 = $check2->fetch_assoc();
    if ($result1['count'] > 0 || $result2['count'] > 0) {
        $error = "Kategori tidak bisa dihapus karena masih memiliki acara atau sub-kategori!";
    } else {
        $stmt = $conn->prepare("DELETE FROM kategori WHERE kategoriID = ?");
        $stmt->bind_param("s", $kategoriID);
        if ($stmt->execute()) {
            $success = "Kategori berhasil dihapus!";
            header("Location: dashboard.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menghapus kategori!";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_subkategori'])) {
    $subKategoriID = $_GET['delete_subkategori'];
    $check = $conn->query("SELECT COUNT(*) as count FROM acara WHERE subKategoriID = '$subKategoriID'");
    $result = $check->fetch_assoc();
    if ($result['count'] > 0) {
        $error = "Sub-kategori tidak bisa dihapus karena masih digunakan di acara!";
    } else {
        $stmt = $conn->prepare("DELETE FROM subKategori WHERE subKategoriID = ?");
        $stmt->bind_param("s", $subKategoriID);
        if ($stmt->execute()) {
            $success = "Sub-kategori berhasil dihapus!";
            header("Location: dashboard.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menghapus sub-kategori!";
        }
        $stmt->close();
    }
}

if (isset($_GET['delete_admin'])) {
    $adminID = $_GET['delete_admin'];
    
    if ($adminID === $_SESSION['userID']) {
        $error = "Tidak bisa menghapus akun Anda sendiri!";
    } else {
        $stmt = $conn->prepare("DELETE FROM user WHERE userID = ?");
        $stmt->bind_param("s", $adminID);
        if ($stmt->execute()) {
            $success = "Admin berhasil dihapus!";
            header("Location: dashboard.php?success=" . urlencode($success));
            exit;
        } else {
            $error = "Gagal menghapus admin!";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['tambah_acara'])) {
        $kategoriID = $_POST['kategoriID'];
        $subKategoriID = $_POST['subKategoriID'];
        $lokasiID = $_POST['lokasiID'];
        $sertifikatID = $_POST['sertifikatID'];
        $organisasi = $_POST['organisasi']; 
        $nama_acara = trim($_POST['nama_acara']);
        $deskripsi = trim($_POST['deskripsi']);
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $jam_mulai = $_POST['jam_mulai']; 
        $jam_selesai = $_POST['jam_selesai']; 
        $maksimal_peserta = $_POST['maksimal_peserta'];
        $open_status = $_POST['open_status'];
        
        $check = $conn->prepare("SELECT COUNT(*) as count FROM acara WHERE nama_acara = ? AND tanggal_mulai = ?");
        $check->bind_param("ss", $nama_acara, $tanggal_mulai);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = "Acara dengan nama dan tanggal yang sama sudah ada!";
        } else {

            $result = $conn->query("SELECT acaraID FROM acara ORDER BY acaraID DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $last = $result->fetch_assoc();
                $num = intval(substr($last['acaraID'], 3)) + 1;
                $acaraID = 'ACR' . str_pad($num, 2, '0', STR_PAD_LEFT);
            } else {
                $acaraID = 'ACR01';
            }
            
            $thumbnail_name = NULL;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
                $upload_dir = 'images/thumbnail/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $_FILES['thumbnail']['name'];
                $file_tmp = $_FILES['thumbnail']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                
                if (in_array($file_ext, $allowed)) {
                    $new_file_name = 'thumb_' . $acaraID . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $thumbnail_name = $new_file_name;
                    } else {
                        $error = "Gagal upload thumbnail!";
                    }
                } else {
                    $error = "Format thumbnail tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, WEBP.";
                }
            }
            
            if (empty($error)) {
$stmt = $conn->prepare("INSERT INTO acara (acaraID, kategoriID, lokasiID, subKategoriID, sertifikatID, organisasi, nama_acara, deskripsi, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai, total_peserta, maksimal_peserta, open_status, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)");
$stmt->bind_param("sssssssssssssss", $acaraID, $kategoriID, $lokasiID, $subKategoriID, $sertifikatID, $organisasi, $nama_acara, $deskripsi, $tanggal_mulai, $tanggal_selesai, $jam_mulai, $jam_selesai, $maksimal_peserta, $open_status, $thumbnail_name);
                
                if ($stmt->execute()) {
                    $success = "Acara berhasil ditambahkan!";
                    header("Location: dashboard.php?success=" . urlencode($success));
                    exit;
                } else {
                    $error = "Gagal menambahkan acara: " . $conn->error;
                    if ($thumbnail_name && file_exists('images/thumbnail/' . $thumbnail_name)) {
                        unlink('images/thumbnail/' . $thumbnail_name);
                    }
                }
                $stmt->close();
            }
        }
        $check->close();
    }

    if (isset($_POST['tambah_lokasi'])) {
        $kapasitas = $_POST['kapasitas'];
        $alamat = trim($_POST['alamat']);
        $ruang = trim($_POST['ruang']);
        
        $check = $conn->prepare("SELECT COUNT(*) as count FROM lokasi WHERE ruang = ?");
        $check->bind_param("s", $ruang);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = "Ruang dengan nama yang sama sudah ada!";
        } else {
            $result = $conn->query("SELECT lokasiID FROM lokasi ORDER BY lokasiID DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $last = $result->fetch_assoc();
                $num = intval(substr($last['lokasiID'], 3)) + 1;
                $lokasiID = 'LOK' . str_pad($num, 2, '0', STR_PAD_LEFT);
            } else {
                $lokasiID = 'LOK01';
            }
            
            $stmt = $conn->prepare("INSERT INTO lokasi (lokasiID, kapasitas, alamat, ruang) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $lokasiID, $kapasitas, $alamat, $ruang);
            
            if ($stmt->execute()) {
                $success = "Lokasi berhasil ditambahkan!";
                header("Location: dashboard.php?success=" . urlencode($success));
                exit;
            } else {
                $error = "Gagal menambahkan lokasi: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
    
    if (isset($_POST['tambah_sertifikat'])) {
        $template_sertifikat = trim($_POST['template_sertifikat']);
        
        if (strpos($template_sertifikat, ' ') !== false) {
            $error = "Nama template tidak boleh mengandung spasi! Gunakan underscore (_) atau tanpa spasi.";
        } else {
            $check = $conn->prepare("SELECT COUNT(*) as count FROM sertifikat WHERE template_sertifikat LIKE ?");
            $search_pattern = $template_sertifikat . '_%';
            $check->bind_param("s", $search_pattern);
            $check->execute();
            $result = $check->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                $error = "Nama template sertifikat sudah ada!";
            } else {
                $upload_dir = 'images/sertifikat/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (isset($_FILES['gambar_template']) && $_FILES['gambar_template']['error'] == 0) {
                    $file_name = $_FILES['gambar_template']['name'];
                    $file_tmp = $_FILES['gambar_template']['tmp_name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed = array('jpg', 'jpeg', 'png');
                    
                    if (in_array($file_ext, $allowed)) {
                        $result = $conn->query("SELECT sertifikatID FROM sertifikat ORDER BY sertifikatID DESC LIMIT 1");
                        if ($result->num_rows > 0) {
                            $last = $result->fetch_assoc();
                            $num = intval(substr($last['sertifikatID'], 3)) + 1;
                            $sertifikatID = 'SER' . str_pad($num, 2, '0', STR_PAD_LEFT);
                        } else {
                            $sertifikatID = 'SER01';
                        }
                        
                        $new_file_name = $template_sertifikat . '_' . $sertifikatID . '.' . $file_ext;
                        $file_path = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $stmt = $conn->prepare("INSERT INTO sertifikat (sertifikatID, template_sertifikat) VALUES (?, ?)");
                            $stmt->bind_param("ss", $sertifikatID, $new_file_name);
                            
                            if ($stmt->execute()) {
                                $success = "Template sertifikat berhasil ditambahkan!";
                            } else {
                                $error = "Gagal menambahkan sertifikat: " . $conn->error;
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            }
                            $stmt->close();
                        } else {
                            $error = "Gagal upload file!";
                        }
                    } else {
                        $error = "Format file tidak diizinkan. Hanya JPG, JPEG, PNG.";
                    }
                } else {
                    $error = "File tidak ditemukan atau error upload!";
                }
            }
            $check->close();
        }
    }
    
    if (isset($_POST['tambah_kategori'])) {
        $nama_kategori = trim($_POST['nama_kategori']);
        $deskripsi_kategori = trim($_POST['deskripsi_kategori']);
        
        $check = $conn->prepare("SELECT COUNT(*) as count FROM kategori WHERE nama_kategori = ?");
        $check->bind_param("s", $nama_kategori);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = "Kategori dengan nama yang sama sudah ada!";
        } else {
            $result = $conn->query("SELECT kategoriID FROM kategori ORDER BY kategoriID DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $last = $result->fetch_assoc();
                $num = intval(substr($last['kategoriID'], 3)) + 1;
                $kategoriID = 'KAT' . str_pad($num, 2, '0', STR_PAD_LEFT);
            } else {
                $kategoriID = 'KAT01';
            }
            
            $stmt = $conn->prepare("INSERT INTO kategori (kategoriID, nama_kategori, deskripsi_kategori) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $kategoriID, $nama_kategori, $deskripsi_kategori);
            
            if ($stmt->execute()) {
                $success = "Kategori berhasil ditambahkan!";
                header("Location: dashboard.php?success=" . urlencode($success));
                exit;
            } else {
                $error = "Gagal menambahkan kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
    
    if (isset($_POST['tambah_subkategori'])) {
        $kategoriID = $_POST['kategoriID_sub'];
        $nama_subKategori = trim($_POST['nama_subKategori']);
        $deskripsi_subKategori = trim($_POST['deskripsi_subKategori']);
        
        $check = $conn->prepare("SELECT COUNT(*) as count FROM subKategori WHERE nama_subKategori = ? AND kategoriID = ?");
        $check->bind_param("ss", $nama_subKategori, $kategoriID);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = "Sub-kategori dengan nama yang sama sudah ada di kategori ini!";
        } else {
            $result = $conn->query("SELECT subKategoriID FROM subKategori ORDER BY subKategoriID DESC LIMIT 1");
            if ($result->num_rows > 0) {
                $last = $result->fetch_assoc();
                $num = intval(substr($last['subKategoriID'], 3)) + 1;
                $subKategoriID = 'SUB' . str_pad($num, 2, '0', STR_PAD_LEFT);
            } else {
                $subKategoriID = 'SUB01';
            }
            
            $stmt = $conn->prepare("INSERT INTO subKategori (subKategoriID, kategoriID, nama_subKategori, deskripsi_subKategori) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $subKategoriID, $kategoriID, $nama_subKategori, $deskripsi_subKategori);
            
            if ($stmt->execute()) {
                $success = "Sub-kategori berhasil ditambahkan!";
                header("Location: dashboard.php?success=" . urlencode($success));
                exit;
            } else {
                $error = "Gagal menambahkan sub-kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check->close();
    }
    
    if (isset($_POST['tambah_admin'])) {
        include 'fakultas.php';
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $NIM = trim($_POST['NIM']); 
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "Password dan Konfirmasi Password tidak sama!";
        } elseif (strlen($password) < 6) {
            $error = "Password minimal 6 karakter!";
        } elseif (empty($NIM) || strlen($NIM) !== 9 || !ctype_digit($NIM)) {
            $error = "NIM harus 9 digit angka!";
        } else {
            $fakultasID = detectFakultasFromNIM($NIM);
            if (!$fakultasID) {
                 $error = "Format NIM tidak valid!";
          } else {
            $check_email = $conn->prepare("SELECT COUNT(*) as count FROM user WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $result_email = $check_email->get_result()->fetch_assoc();
            
            if ($result_email['count'] > 0) {
                $error = "Email sudah terdaftar!";
            } else {
                $result_user = $conn->query("SELECT userID FROM user ORDER BY userID DESC LIMIT 1");
                if ($result_user->num_rows > 0) {
                    $last = $result_user->fetch_assoc();
                    $num = intval(substr($last['userID'], 3)) + 1;
                    $userID_new = 'USR' . str_pad($num, 6, '0', STR_PAD_LEFT);
                } else {
                    $userID_new = 'USR000001';
                }
                
                $role_query = $conn->query("SELECT rolesID FROM roles WHERE nama_role = 'admin' LIMIT 1");
                if ($role_query->num_rows > 0) {
                    $role_data = $role_query->fetch_assoc();
                    $rolesID = $role_data['rolesID'];
                } else {
                    $role_check = $conn->query("SELECT rolesID FROM roles ORDER BY rolesID DESC LIMIT 1");
                    if ($role_check->num_rows > 0) {
                        $last_role = $role_check->fetch_assoc();
                        $num_role = intval(substr($last_role['rolesID'], 3)) + 1;
                        $rolesID = 'ROL' . str_pad($num_role, 2, '0', STR_PAD_LEFT);
                    } else {
                        $rolesID = 'ROL01';
                    }
                    
                    $stmt_role = $conn->prepare("INSERT INTO roles (rolesID, nama_role, deskripsi_role) VALUES (?, 'admin', 'Administrator')");
                    $stmt_role->bind_param("s", $rolesID);
                    $stmt_role->execute();
                    $stmt_role->close();
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt_admin = $conn->prepare("INSERT INTO user (userID, rolesID, nama, email, NIM, password, fakultasID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_admin->bind_param("sssssss", $userID_new, $rolesID, $nama, $email, $NIM, $hashed_password, $fakultasID);
                
                if ($stmt_admin->execute()) {
                    $success = "Akun admin berhasil ditambahkan!";
                    header("Location: dashboard.php?success=" . urlencode($success));
                    exit;
                } else {
                    $error = "Gagal menambahkan admin: " . $conn->error;
                }
                $stmt_admin->close();
            }
                $check_email->close();
        }
    }
}

}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

$kategori_list = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
$subkategori_list = $conn->query("SELECT sk.*, k.nama_kategori FROM subKategori sk JOIN kategori k ON sk.kategoriID = k.kategoriID ORDER BY sk.nama_subKategori");
$lokasi_list = $conn->query("SELECT * FROM lokasi ORDER BY ruang");
$sertifikat_list = $conn->query("SELECT * FROM sertifikat ORDER BY sertifikatID DESC");

$acara_list = $conn->query("SELECT a.*, l.ruang, k.nama_kategori, s.template_sertifikat FROM acara a JOIN lokasi l ON a.lokasiID = l.lokasiID JOIN kategori k ON a.kategoriID = k.kategoriID LEFT JOIN sertifikat s ON a.sertifikatID = s.sertifikatID WHERE a.completion_status = 'NO' ORDER BY a.tanggal_mulai DESC");
$admin_list = $conn->query("SELECT u.*, f.nama_fakultas FROM user u JOIN roles r ON u.rolesID = r.rolesID LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID WHERE r.nama_role = 'admin' ORDER BY u.userID DESC");
$acara_list_laporan = $conn->query("SELECT acaraID, nama_acara, tanggal_mulai FROM acara ORDER BY tanggal_mulai DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - FTI Untar</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .thumbnail-preview {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 8px;
            display: none;
        }
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="menu">
        <div class="logo">
            <img src="images/logo.png" alt="FTI Untar Logo">
        </div>
        <nav>
            <div class="button-container">
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
            </div>
        </nav>
    </div>

    <div class="body">
        <div class="backbutton">
            <h2><a href="mainpage.php">← home</a></h2>
        </div>

        <h2 class="page-title">Dashboard Admin</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="tab-container">
            <button class="tab-btn active" onclick="openTab(event, 'acara')">Acara</button>
            <button class="tab-btn" onclick="openTab(event, 'lokasi')">Lokasi</button>
            <button class="tab-btn" onclick="openTab(event, 'sertifikat')">Sertifikat</button>
            <button class="tab-btn" onclick="openTab(event, 'kategori')">Kategori</button>
            <button class="tab-btn" onclick="openTab(event, 'akun')">Akun</button>
            <button class="tab-btn" onclick="openTab(event, 'absensi')">Absensi</button>
            <button class="tab-btn" onclick="openTab(event, 'laporan')">Laporan</button>
        </div>

        <div id="acara" class="tab-content active">
            <div class="admin-panel">
                <div class="admin-box">
                    <h3>Tambah Acara Baru</h3>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <label>Kategori</label>
                        <select name="kategoriID" id="kategoriSelect" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $kategori_list->data_seek(0);
                            while($kat = $kategori_list->fetch_assoc()): ?>
                                <option value="<?php echo $kat['kategoriID']; ?>"><?php echo $kat['nama_kategori']; ?></option>
                            <?php endwhile; ?>
                        </select>

                        <label>Sub-Kategori</label>
                        <select name="subKategoriID" id="subKategoriSelect" required>
                            <option value="">Pilih Sub-Kategori</option>
                            <?php 
                            $subkategori_list->data_seek(0);
                            while($sub = $subkategori_list->fetch_assoc()): ?>
                                <option value="<?php echo $sub['subKategoriID']; ?>" data-kategori="<?php echo $sub['kategoriID']; ?>">
                                    <?php echo $sub['nama_subKategori']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <label>Lokasi</label>
                        <select name="lokasiID" required>
                            <option value="">Pilih Lokasi</option>
                            <?php 
                            $lokasi_list->data_seek(0);
                            while($lok = $lokasi_list->fetch_assoc()): ?>
                                <option value="<?php echo $lok['lokasiID']; ?>">
                                    <?php echo $lok['ruang'] . ' - ' . $lok['alamat']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <label>Template Sertifikat</label>
                        <select name="sertifikatID" required>
                            <option value="">Pilih Template</option>
                            <?php 
                            $sertifikat_list->data_seek(0);
                            while($ser = $sertifikat_list->fetch_assoc()): ?>
                                <option value="<?php echo $ser['sertifikatID']; ?>">
                                    <?php echo $ser['template_sertifikat']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <label>Nama Acara</label>
                        <input type="text" name="nama_acara" maxlength="50" placeholder="Nama acara mahasiswa" required>

                        <label>Deskripsi</label>
                        <textarea name="deskripsi" maxlength="500" placeholder="Deskripsi acara" required></textarea>

                        <label>Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" required>

                        <label>Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" required>

                        <label>Jam Mulai</label>
                        <input type="time" name="jam_mulai" required>

                        <label>Jam Selesai</label>
                        <input type="time" name="jam_selesai" required>

                        <label>Organisasi</label>
                        <select name="organisasi" required>
                            <option value="">Pilih Organisasi</option>
                            <option value="BEM">BEM</option>
                            <option value="DPM">DPM</option>
                            <option value="MAPALA">MAPALA</option>
                        </select>

                        <label>Maksimal Peserta</label>
                        <input type="number" name="maksimal_peserta" min="1" placeholder="100" required>

                        <label>Status Keterbukaan Acara</label>
                        <select name="open_status" required>
                            <option value="">Pilih Status</option>
                            <option value="FTI">Khusus FTI</option>
                            <option value="SEMUA">Terbuka Semua Fakultas</option>
                        </select>
                        <div class="file-info">FTI: Hanya Fakultas Teknologi Informasi | SEMUA: Seluruh Fakultas UNTAR</div>

                        <label>Upload Thumbnail (Opsional)</label>
                        <input type="file" name="thumbnail" id="thumbnailInput" accept="image/*" onchange="previewThumbnail(this)">
                        <div class="file-info">Format: JPG, JPEG, PNG, GIF, WEBP (Max 5MB)</div>
                        <img id="thumbnailPreview" class="thumbnail-preview" alt="Preview Thumbnail">

                        <button type="submit" name="tambah_acara" class="confirm-button">Tambah Acara</button>
                    </form>
                </div>

                <div class="admin-box">
                    <h3>Daftar Acara</h3>
                    <div class="data-list">
                        <?php while($acara = $acara_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($acara['nama_acara']); ?></strong>
                                <span><?php echo date('d M Y', strtotime($acara['tanggal_mulai'])) . ' - ' . date('d M Y', strtotime($acara['tanggal_selesai'])); ?></span>
                                <span><?php echo $acara['nama_kategori'] . ' | ' . $acara['ruang']; ?></span>
                                <span>Peserta: <?php echo $acara['total_peserta'] . '/' . $acara['maksimal_peserta']; ?></span>
                                <span style="color: <?php echo ($acara['open_status'] == 'FTI') ? '#662D91' : '#28a745'; ?>; font-weight: 600;">
                                    <?php echo ($acara['open_status'] == 'FTI') ? 'Khusus FTI' : 'Semua Fakultas'; ?>
                                </span>
                                <span style="color: #107ff7ff; font-weight: 600;">
                                Organisasi: <?php echo htmlspecialchars($acara['organisasi']); ?>
                                </span>
                                <span style="color: #df4343ff; font-weight: 600;">
                                Jam: <?php echo date('H:i', strtotime($acara['jam_mulai'])) . ' - ' . date('H:i', strtotime($acara['jam_selesai'])); ?>
                                </span>
                                <?php if (!empty($acara['thumbnail'])): ?>
                                <span style="color: #662D91;">Thumbnail: <?php echo htmlspecialchars($acara['thumbnail']); ?></span>
                                <?php endif; ?>
                            </div>
                                <div class="data-actions">
                                    <button class="btn-action complete" onclick="completeAcara('<?php echo $acara['acaraID']; ?>')">Selesai</button>
                                    <button class="btn-action delete" onclick="deleteAcara('<?php echo $acara['acaraID']; ?>')">Hapus</button>
                                </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="absensi" class="tab-content">
            <div class="admin-panel">
                <?php if ($show_qr): ?>
                <div class="admin-box qr-box">
                    <div style="text-align: right;">
                        <button onclick="window.location.href='dashboard.php'" class="btn-action delete" style="display: inline-block; margin-bottom: 20px;">✕ Tutup</button>
                    </div>
                    <h3 style="text-align: center; color: #662D91; margin-bottom: 10px;"><?php echo htmlspecialchars($qr_data['acara']['nama_acara']); ?></h3>
                    <p style="text-align: center; color: #666; margin-bottom: 30px;">
                        <strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($qr_data['acara']['tanggal_mulai'])); ?>
                    </p>
                    
                    <div style="text-align: center; padding: 30px; background: #f8f8f8; border-radius: 15px; margin-bottom: 20px;">
                        <img src="<?php echo $qr_data['qr_url']; ?>" alt="QR Code" style="max-width: 100%; height: auto; border-radius: 10px;">
                    </div>
                    
                    <div style="background: #662D911A; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h4 style="color: #662D91; font-size: 16px; margin-bottom: 10px;">Cara Menggunakan:</h4>
                        <p style="color: #666; font-size: 14px; line-height: 1.6;">
                            1. Scan kode QR dengan smartphone<br>
                            2. Peserta akan diarahkan ke halaman absensi<br>
                            3. Peserta mengisi NIM/Email dan password<br>
                            4. Absensi akan tersimpan
                        </p>
                    </div>
                    
                    <button onclick="window.print()" class="confirm-button" style="width: 100%;">Print QR Code</button>
                </div>
                <?php else: ?>
                <div class="admin-box">
                    <h3>Generate QR Code Absensi</h3>
                    <form method="GET" action="dashboard.php">
                        <label>Pilih Acara</label>
                        <select name="generate_qr" required>
                            <option value="">Pilih Acara</option>
                            <?php 
                            $acara_list->data_seek(0);
                            while($acara = $acara_list->fetch_assoc()): ?>
                                <option value="<?php echo $acara['acaraID']; ?>">
                                    <?php echo $acara['nama_acara'] . ' - ' . date('d M Y', strtotime($acara['tanggal_mulai'])); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <button type="submit" class="confirm-button">Generate QR Code</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="lokasi" class="tab-content">
            <div class="admin-panel">
                <div class="admin-box">
                    <h3>Tambah Lokasi Baru</h3>
                    <form method="POST" action="">
                        <label>Kapasitas</label>
                        <input type="number" name="kapasitas" min="1" placeholder="Kapasitas ruangan" required>

                        <label>Alamat</label>
                        <textarea name="alamat" maxlength="255" placeholder="Alamat lengkap lokasi" required></textarea>

                        <label>Ruang</label>
                        <input type="text" name="ruang" maxlength="20" placeholder="Contoh: A201, Auditorium" required>

                        <button type="submit" name="tambah_lokasi" class="confirm-button">Tambah Lokasi</button>
                    </form>
                </div>

                <div class="admin-box">
                    <h3>Daftar Lokasi</h3>
                    <div class="data-list">
                        <?php 
                        $lokasi_list->data_seek(0);
                        while($lok = $lokasi_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($lok['ruang']); ?></strong>
                                <span><?php echo htmlspecialchars($lok['alamat']) . ' - Kapasitas: ' . $lok['kapasitas']; ?></span>
                            </div>
                            <div class="data-actions">
                                <button class="btn-action delete" onclick="deleteLokasi('<?php echo $lok['lokasiID']; ?>')">Hapus</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="sertifikat" class="tab-content">
            <div class="admin-panel">
                <div class="admin-box">
                    <h3>Tambah Template Sertifikat</h3>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <label>Nama Template Sertifikat</label>
                        <input type="text" name="template_sertifikat" id="templateName" maxlength="50" placeholder="Contoh: SertifikatWorkshop (tanpa spasi)" required oninput="validateTemplateName(this)">
                        <div class="file-info" style="color: #dc3545; display: none;" id="spaceWarning">Nama template tidak boleh mengandung spasi!</div>
                        <div class="file-info">Format penamaan: (namatemplate)_SER01.jpg</div>

                        <label>Upload Gambar Template</label>
                        <input type="file" name="gambar_template" accept="image/*" required>

                        <button type="submit" name="tambah_sertifikat" class="confirm-button">Tambah Template</button>
                    </form>
                </div>

                <div class="admin-box">
                    <h3>Daftar Template Sertifikat</h3>
                    <div class="data-list">
                        <?php 
                        $sertifikat_list->data_seek(0);
                        while($ser = $sertifikat_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($ser['template_sertifikat']); ?></strong>
                                <span>ID: <?php echo htmlspecialchars($ser['sertifikatID']); ?></span>
                            </div>
                            <div class="data-actions">
                                <button class="btn-action delete" onclick="deleteSertifikat('<?php echo $ser['sertifikatID']; ?>')">Hapus</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="akun" class="tab-content">
            <div class="admin-panel">
                <div class="admin-box">
                    <h3>Tambah Akun Admin Baru</h3>
                    <form method="POST" action="">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" maxlength="50" placeholder="Nama admin" required>

                        <label>Email</label>
                        <input type="email" name="email" maxlength="50" placeholder="Email admin" required>

                        <label>NIM</label>
                        <input type="text" name="NIM" maxlength="9" pattern="\d{9}" placeholder="NIM 9 digit" required>
                        <small class="file-info">Fakultas akan otomatis terdeteksi dari NIM</small>

                        <label>Password</label>
                        <input type="password" name="password" maxlength="20" placeholder="Password minimal 6 karakter" required>

                        <label>Konfirmasi Password</label>
                        <input type="password" name="confirm_password" maxlength="20" placeholder="Ulangi password" required>

                        <button type="submit" name="tambah_admin" class="confirm-button">Tambah Admin</button>
                    </form>
                </div>

                <div class="admin-box">
                    <h3>Daftar Akun Admin</h3>
                    <div class="data-list">
                        <?php while($admin = $admin_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($admin['nama']); ?></strong>
                                <span>Email: <?php echo htmlspecialchars($admin['email']); ?></span>
                                <span>NIM: <?php echo htmlspecialchars($admin['NIM']); ?></span>
                                <span>Fakultas: <?php echo htmlspecialchars($admin['nama_fakultas']); ?></span>
                                <span>User ID: <?php echo htmlspecialchars($admin['userID']); ?></span>
                            </div>
                            <div class="data-actions">
                                <?php if($admin['userID'] !== $_SESSION['userID']): ?>
                                <button class="btn-action delete" onclick="deleteAdmin('<?php echo $admin['userID']; ?>')">Hapus</button>
                                <?php else: ?>
                                <span style="color: #999; font-size: 13px;">Akun Anda</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="kategori" class="tab-content">
            <div class="admin-panel">
                <div class="admin-box">
                    <h3>Tambah Kategori Baru</h3>
                    <form method="POST" action="">
                        <label>Nama Kategori</label>
                        <input type="text" name="nama_kategori" maxlength="20" placeholder="Contoh: Workshop, Seminar" required>

                        <label>Deskripsi Kategori</label>
                        <textarea name="deskripsi_kategori" maxlength="50" placeholder="Deskripsi singkat kategori"></textarea>

                        <button type="submit" name="tambah_kategori" class="confirm-button">Tambah Kategori</button>
                    </form>

                    <h3 style="margin-top: 40px;">Tambah Sub-Kategori</h3>
                    <form method="POST" action="">
                        <label>Kategori</label>
                        <select name="kategoriID_sub" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            $kategori_list->data_seek(0);
                            while($kat = $kategori_list->fetch_assoc()): ?>
                                <option value="<?php echo $kat['kategoriID']; ?>"><?php echo $kat['nama_kategori']; ?></option>
                            <?php endwhile; ?>
                        </select>

                        <label>Nama Sub-Kategori</label>
                        <input type="text" name="nama_subKategori" maxlength="20" placeholder="Contoh: Pemrograman Web" required>

                        <label>Deskripsi Sub-Kategori</label>
                        <textarea name="deskripsi_subKategori" maxlength="50" placeholder="Deskripsi singkat"></textarea>

                        <button type="submit" name="tambah_subkategori" class="confirm-button">Tambah Sub-Kategori</button>
                    </form>
                </div>

                <div class="admin-box">
                    <h3>Daftar Kategori</h3>
                    <div class="data-list">
                        <?php 
                        $kategori_list->data_seek(0);
                        while($kat = $kategori_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($kat['nama_kategori']); ?></strong>
                                <span><?php echo htmlspecialchars($kat['deskripsi_kategori']); ?></span>
                            </div>
                            <div class="data-actions">
                                <button class="btn-action delete" onclick="deleteKategori('<?php echo $kat['kategoriID']; ?>')">Hapus</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <h3 style="margin-top: 30px;">Daftar Sub-Kategori</h3>
                    <div class="data-list">
                        <?php 
                        $subkategori_list->data_seek(0);
                        while($sub = $subkategori_list->fetch_assoc()): ?>
                        <div class="data-item">
                            <div class="data-info">
                                <strong><?php echo htmlspecialchars($sub['nama_subKategori']); ?></strong>
                                <span>Kategori: <?php echo htmlspecialchars($sub['nama_kategori']); ?></span>
                            </div>
                            <div class="data-actions">
                                <button class="btn-action delete" onclick="deleteSubKategori('<?php echo $sub['subKategoriID']; ?>')">Hapus</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
        <div id="laporan" class="tab-content">
    <div class="admin-panel" style="display: block;">

        <div class="admin-box" style="max-width: 100%;">
            <h3>Laporan Absensi per Acara</h3>
            <form method="GET">
                <input type="hidden" name="laporan" value="absensi">
                <label>Pilih Acara</label>
                <select name="acara_id" required>
                    <option value="">-- Pilih Acara --</option>
                    <?php 
                    $acara_list_laporan->data_seek(0);
                    while($acara_lap = $acara_list_laporan->fetch_assoc()): 
                        $selected = (isset($_GET['acara_id']) && $_GET['acara_id'] == $acara_lap['acaraID']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $acara_lap['acaraID']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($acara_lap['nama_acara']) . ' - ' . date('d M Y', strtotime($acara_lap['tanggal_mulai'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="confirm-button">Tampilkan Laporan</button>
            </form>

            <?php 
            if(isset($_GET['laporan']) && $_GET['laporan'] == 'absensi' && isset($_GET['acara_id'])): 
                $acara_id = $_GET['acara_id'];
                $acara_info = $conn->query("SELECT a.*, k.nama_kategori, l.ruang FROM acara a 
                    JOIN kategori k ON a.kategoriID = k.kategoriID 
                    JOIN lokasi l ON a.lokasiID = l.lokasiID 
                    WHERE a.acaraID = '$acara_id'")->fetch_assoc();
                
                if($acara_info):
                    $total_reg = $conn->query("SELECT COUNT(*) as total FROM registrasi WHERE acaraID = '$acara_id'")->fetch_assoc()['total'];
                    $total_hadir = $conn->query("SELECT COUNT(*) as total FROM absensi ab 
                        JOIN registrasi r ON ab.registrasiID = r.registrasiID 
                        WHERE r.acaraID = '$acara_id'")->fetch_assoc()['total'];
                    $persen = $total_reg > 0 ? round(($total_hadir / $total_reg) * 100, 1) : 0;
            ?>
                <div class="summary-box">
                    <h4 style="color: #662D91; margin-bottom: 15px;">Ringkasan</h4>
                    <div class="summary-item">
                        <strong>Nama Acara:</strong>
                        <span><?php echo htmlspecialchars($acara_info['nama_acara']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Kategori:</strong>
                        <span><?php echo htmlspecialchars($acara_info['nama_kategori']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Tanggal:</strong>
                        <span><?php echo date('d M Y', strtotime($acara_info['tanggal_mulai'])); ?></span>
                    </div>
                    <div class="summary-item">
                    <strong>Jam Acara:</strong>
                    <span><?php echo date('H:i', strtotime($acara_info['jam_mulai'])) . ' - ' . date('H:i', strtotime($acara_info['jam_selesai'])); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Organisasi:</strong>
                        <span><?php echo htmlspecialchars($acara_info['organisasi']); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong>Total Pendaftar:</strong>
                        <span><?php echo $total_reg; ?> orang</span>
                    </div>
                    <div class="summary-item">
                        <strong>Total Hadir:</strong>
                        <span style="color: #28a745; font-weight: 600;"><?php echo $total_hadir; ?> orang</span>
                    </div>
                    <div class="summary-item">
                        <strong>Persentase:</strong>
                        <span style="color: #662D91; font-weight: 600;"><?php echo $persen; ?>%</span>
                    </div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th>Fakultas</th>
                            <th>Waktu Absen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT u.nama, u.NIM, f.nama_fakultas AS fakultas, ab.waktu_absen
                                  FROM registrasi r
                                  JOIN user u ON r.userID = u.userID
                                  LEFT JOIN fakultas f ON u.fakultasID = f.fakultasID
                                  LEFT JOIN absensi ab ON r.registrasiID = ab.registrasiID
                                  WHERE r.acaraID = '$acara_id'
                                  ORDER BY u.nama";
                        $result = $conn->query($query);
                        $no = 1;
                        while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['NIM']); ?></td>
                            <td><?php echo htmlspecialchars($row['fakultas']); ?></td>
                            <td><?php echo $row['waktu_absen'] ? date('d M Y H:i', strtotime($row['waktu_absen'])) : '-'; ?></td>
                            <td>
                                <?php if($row['waktu_absen']): ?>
                                    <span style="color: #28a745;"> Hadir</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Tidak Hadir</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <button class="btn-print" onclick="window.print()">Print</button>
            <?php 
                endif;
            endif; 
            ?>
        </div>

        <div class="admin-box" style="max-width: 100%; margin-top: 30px;">
            <h3>Laporan Manajemen per Kategori</h3>
            <form method="GET">
                <input type="hidden" name="laporan" value="kategori">
                <button type="submit" class="confirm-button">Tampilkan Laporan</button>
            </form>

            <?php if(isset($_GET['laporan']) && $_GET['laporan'] == 'kategori'): 
                $total_acara = $conn->query("SELECT COUNT(*) as total FROM acara")->fetch_assoc()['total'];
                $total_peserta = $conn->query("SELECT SUM(total_peserta) as total FROM acara")->fetch_assoc()['total'];
            ?>
                <div class="summary-box">
                    <h4 style="color: #662D91; margin-bottom: 15px;">Ringkasan Total</h4>
                    <div class="summary-item">
                        <strong>Total Acara:</strong>
                        <span style="color: #662D91; font-weight: 600;"><?php echo $total_acara; ?> acara</span>
                    </div>
                    <div class="summary-item">
                        <strong>Total Peserta:</strong>
                        <span style="color: #662D91; font-weight: 600;"><?php echo $total_peserta; ?> peserta</span>
                    </div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kategori</th>
                            <th>Jumlah Acara</th>
                            <th>Total Peserta</th>
                            <th>Rata-rata</th>
                            <th>Selesai</th>
                            <th>Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT k.nama_kategori, 
                                  COUNT(a.acaraID) as jml_acara,
                                  IFNULL(SUM(a.total_peserta), 0) as ttl_peserta,
                                  IFNULL(ROUND(AVG(a.total_peserta), 0), 0) as rata,
                                  SUM(CASE WHEN a.completion_status = 'YES' THEN 1 ELSE 0 END) as selesai,
                                  SUM(CASE WHEN a.completion_status = 'NO' THEN 1 ELSE 0 END) as aktif
                                  FROM kategori k
                                  LEFT JOIN acara a ON k.kategoriID = a.kategoriID
                                  GROUP BY k.kategoriID
                                  ORDER BY jml_acara DESC";
                        $result = $conn->query($query);
                        $no = 1;
                        while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama_kategori']); ?></strong></td>
                            <td><?php echo $row['jml_acara']; ?></td>
                            <td><?php echo $row['ttl_peserta']; ?></td>
                            <td><?php echo $row['rata']; ?></td>
                            <td><?php echo $row['selesai']; ?></td>
                            <td><?php echo $row['aktif']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <h4 style="margin-top: 30px; color: #662D91;">Detail Acara per Kategori</h4>
                <?php
                $query2 = "SELECT k.nama_kategori, a.nama_acara, a.tanggal_mulai, a.total_peserta, 
                           a.maksimal_peserta, a.completion_status
                           FROM kategori k
                           LEFT JOIN acara a ON k.kategoriID = a.kategoriID
                           WHERE a.acaraID IS NOT NULL
                           ORDER BY k.nama_kategori, a.tanggal_mulai DESC";
                $result2 = $conn->query($query2);
                
                $kat_now = '';
                while($row = $result2->fetch_assoc()):
                    if($kat_now != $row['nama_kategori']):
                        if($kat_now != '') echo '</tbody></table>';
                        $kat_now = $row['nama_kategori'];
                ?>
                    <h5 style="margin-top: 20px; color: #662D91;"> <?php echo htmlspecialchars($kat_now); ?></h5>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Nama Acara</th>
                                <th>Tanggal</th>
                                <th>Peserta</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php endif; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nama_acara']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['tanggal_mulai'])); ?></td>
                                <td><?php echo $row['total_peserta'] . '/' . $row['maksimal_peserta']; ?></td>
                                <td>
                                    <?php if($row['completion_status'] == 'YES'): ?>
                                        <span style="color: #28a745;">Selesai</span>
                                    <?php else: ?>
                                        <span style="color: #007bff;">Aktif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                <?php 
                endwhile;
                if($kat_now != '') echo '</tbody></table>';
                ?>
                
                <button class="btn-print" onclick="window.print()"> Print</button>
            <?php endif; ?>
        </div>
    </div>
</div>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove("active");
    }
    
    var tabBtns = document.getElementsByClassName("tab-btn");
    for (var i = 0; i < tabBtns.length; i++) {
        tabBtns[i].classList.remove("active");
    }
    
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

function confirmLogout() {
    if (confirm('Apakah Anda yakin ingin logout?')) {
        window.location.href = 'logout.php';
    }
}

window.addEventListener('load', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('generate_qr')) {
        var absensiTab = document.querySelector('[onclick*="absensi"]');
        if (absensiTab) {
            absensiTab.click();
        }
    }
});

document.getElementById('kategoriSelect').addEventListener('change', function() {
    var kategoriID = this.value;
    var subKategoriSelect = document.getElementById('subKategoriSelect');
    var options = subKategoriSelect.getElementsByTagName('option');
    
    for (var i = 1; i < options.length; i++) {
        if (options[i].getAttribute('data-kategori') == kategoriID) {
            options[i].style.display = 'block';
        } else {
            options[i].style.display = 'none';
        }
    }
    subKategoriSelect.value = '';
});

function previewThumbnail(input) {
    const preview = document.getElementById('thumbnailPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

function validateTemplateName(input) {
    const warning = document.getElementById('spaceWarning');
    if (input.value.includes(' ')) {
        warning.style.display = 'block';
        input.setCustomValidity('Nama template tidak boleh mengandung spasi');
    } else {
        warning.style.display = 'none';
        input.setCustomValidity('');
    }
}

function deleteAcara(id) {
    if(confirm('Yakin ingin menghapus acara ini? Thumbnail juga akan dihapus.')) {
        window.location.href = '?delete_acara=' + id;
    }
}

function completeAcara(id) {
    if(confirm('Tandai acara ini sebagai SELESAI?\n\nAcara yang sudah selesai akan hilang dari daftar dan peserta bisa mengunduh sertifikat.')) {
        window.location.href = '?complete_acara=' + id;
    }
}
function deleteLokasi(id) {
    if(confirm('Yakin ingin menghapus lokasi ini?')) {
        window.location.href = '?delete_lokasi=' + id;
    }
}

function deleteSertifikat(id) {
    if(confirm('Yakin ingin menghapus sertifikat ini?')) {
        window.location.href = '?delete_sertifikat=' + id;
    }
}

function deleteKategori(id) {
    if(confirm('Yakin ingin menghapus kategori ini?')) {
        window.location.href = '?delete_kategori=' + id;
    }
}

function deleteSubKategori(id) {
    if(confirm('Yakin ingin menghapus sub-kategori ini?')) {
        window.location.href = '?delete_subkategori=' + id;
    }
}

function deleteAdmin(id) {
    if(confirm('Yakin ingin menghapus admin ini?')) {
        window.location.href = '?delete_admin=' + id;
    }
}
</script>

</body>
</html>