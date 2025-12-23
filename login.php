<?php
session_start();
include 'koneksi.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input']);
    $password = $_POST['password'];

    if (empty($login_input) || empty($password)) {
        $error_message = "NIM/Email dan Password harus diisi!";
    } else {
        $stmt = $conn->prepare("
            SELECT u.*, r.nama_role 
            FROM user u 
            JOIN roles r ON u.rolesID = r.rolesID 
            WHERE u.NIM = ? OR u.email = ?
        ");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['userID'] = $user['userID'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['rolesID'] = $user['rolesID'];
                $_SESSION['roleName'] = $user['nama_role'];
                $_SESSION['institusi'] = $user['institusi'];
                $_SESSION['nim'] = $user['NIM'];

                // Redirect berdasarkan role
                if ($user['nama_role'] === 'admin') {
                    header("Location: mainpage.php");
                } else {
                    header("Location: mainpage.php");
                }
                exit;
            } else {
                $error_message = "Password salah!";
            }
        } else {
            $error_message = "NIM/Email tidak ditemukan!";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FTI Untar</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="container">
    <div class="menu">
        <div class="logo">
            <img src="images/logo.png" alt="LOGO FTI">
        </div>
    </div>

    <img src="images/untar.jpeg" class="background-img" alt="Background">

    <div class="login-box">
        <h2>Login Sistem Acara FTI Untar</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="login_input" placeholder="NIM atau Email" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <p class="forgot-text">
                Belum punya akun? <a href="register.php" class="forgot">Daftar di sini</a>
            </p>
            <button type="submit" class="btn-confirm">LOGIN</button>
        </form>
    </div>
</div>
</body>
</html>