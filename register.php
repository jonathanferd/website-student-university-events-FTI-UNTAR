<?php
session_start();
include 'koneksi.php';
include 'fakultas.php'; 

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $NIM = trim($_POST['NIM']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan Konfirmasi Password tidak sama!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif (empty($NIM) || strlen($NIM) !== 9) {
        $error = "NIM wajib diisi 9 digit!";
    } elseif (!ctype_digit($NIM)) {
        $error = "NIM harus berupa angka!";
    } else {
        $fakultasID = detectFakultasFromNIM($NIM);
        
        if (!$fakultasID) {
            $error = "NIM tidak valid! Format NIM tidak dikenali.";
        } else {
            $stmt = $conn->prepare("SELECT email FROM user WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email sudah terdaftar!";
            } else {
                $stmt_nim = $conn->prepare("SELECT NIM FROM user WHERE NIM = ?");
                $stmt_nim->bind_param("s", $NIM);
                $stmt_nim->execute();
                $result_nim = $stmt_nim->get_result();

                if ($result_nim->num_rows > 0) {
                    $error = "NIM sudah terdaftar!";
                    $stmt_nim->close();
                } else {
                    $stmt_nim->close();

                    $result_user = $conn->query("SELECT userID FROM user ORDER BY userID DESC LIMIT 1");
                    if ($result_user->num_rows > 0) {
                        $last = $result_user->fetch_assoc();
                        $num = intval(substr($last['userID'], 3)) + 1;
                        $userID = 'USR' . str_pad($num, 6, '0', STR_PAD_LEFT);
                    } else {
                        $userID = 'USR000001';
                    }

                    $role_result = $conn->query("SELECT rolesID FROM roles WHERE nama_role = 'peserta' LIMIT 1");
                    if ($role_result->num_rows > 0) {
                        $role_row = $role_result->fetch_assoc();
                        $rolesID = $role_row['rolesID'];
                    } else {
                        $role_check = $conn->query("SELECT rolesID FROM roles ORDER BY rolesID DESC LIMIT 1");
                        if ($role_check->num_rows > 0) {
                            $last_role = $role_check->fetch_assoc();
                            $num_role = intval(substr($last_role['rolesID'], 3)) + 1;
                            $rolesID = 'ROL' . str_pad($num_role, 2, '0', STR_PAD_LEFT);
                        } else {
                            $rolesID = 'ROL01';
                        }

                        $stmt_role = $conn->prepare("INSERT INTO roles (rolesID, nama_role, deskripsi_role) VALUES (?, 'peserta', 'User Peserta')");
                        $stmt_role->bind_param("s", $rolesID);
                        $stmt_role->execute();
                        $stmt_role->close();
                    }

                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt_insert = $conn->prepare("INSERT INTO user (userID, rolesID, nama, email, NIM, password, fakultasID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sssssss", $userID, $rolesID, $nama, $email, $NIM, $hashed_password, $fakultasID);

                    if ($stmt_insert->execute()) {
                        $success = "Registrasi berhasil! Silakan login.";
                        header("refresh:2;url=login.php");
                    } else {
                        $error = "Gagal melakukan registrasi: " . $conn->error;
                    }
                    $stmt_insert->close();
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mahasiswa UNTAR</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
<div class="container">
    <div class="menu">
        <div class="logo">
            <img src="images/logo.png" alt="UNTAR Logo">
        </div>
    </div>
    <img src="images/untar.jpeg" class="background-img" alt="Background">
    <div class="register-box">
        <h2>Register Mahasiswa UNTAR</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="nama" maxlength="50" placeholder="Nama Lengkap" required>
            </div>
            <div class="input-group">
                <input type="email" name="email" maxlength="50" placeholder="Email" required>
            </div>
            <div class="input-group">
                <input type="text" name="NIM" maxlength="9" pattern="\d{9}" placeholder="Masukkan NIM (9 digit)" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" maxlength="20" placeholder="Password" required>
            </div>
            <div class="input-group">
                <input type="password" name="confirm_password" maxlength="20" placeholder="Konfirmasi Password" required>
            </div>
            <p class="login-text">Sudah punya akun? <a href="login.php" class="login-link">Login di sini</a></p>
            <button type="submit" class="btn-signup">Sign Up</button>
        </form>
    </div>
</div>
</body>
</html>