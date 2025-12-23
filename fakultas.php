<?php
function detectFakultasFromNIM($nim) {
    if (strlen($nim) != 9 || !ctype_digit($nim)) {
        return false;
    }

    $prefix = substr($nim, 0, 2);
    
    $fakultas_map = [
        '82' => 'FAK01', //Sistem Informasi
        '53' => 'FAK01', //Teknik Informatika
        '91' => 'FAK02', //Ilmu Komunikasi
        '61' => 'FAK03', //Desain Interior
        '62' => 'FAK03', // Desain Komunikasi Visual
        '70' => 'FAK04', //Psikologi
        '40' => 'FAK05', //Kedokteran
        '54' => 'FAK06', //Teknik Industri
        '52' => 'FAK06', //Teknik Elektro
        '51' => 'FAK06', //Teknik Mesin
        '34' => 'FAK06', //Perencanaan Wilayah dan Kota
        '32' => 'FAK06', // Teknik Sipil
        '31' => 'FAK06', //Arsitektur
        '20' => 'FAK07', // Hukum
        '12' => 'FAK08', // Akuntansi Bisnis
        '11' => 'FAK08'  // Manajemen Bisnis
    ];
    
    return isset($fakultas_map[$prefix]) ? $fakultas_map[$prefix] : false;
}

function getFakultasName($fakultasID, $conn) {
    $stmt = $conn->prepare("SELECT nama_fakultas FROM fakultas WHERE fakultasID = ?");
    $stmt->bind_param("s", $fakultasID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['nama_fakultas'];
    }
    return null;
}
?>