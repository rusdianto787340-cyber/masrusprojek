<?php
include 'koneksi.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($konek, $_GET['id']);
    $result = mysqli_query($konek, "SELECT * FROM tbdokumen WHERE Id = '$id'");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $file_path = $row['file_path'];
        
        if (file_exists($file_path)) {
            // Tampilkan file berdasarkan tipe
            header('Content-Type: ' . mime_content_type($file_path));
            header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
            readfile($file_path);
            exit;
        }
    }
}

// Jika gagal, redirect ke index
header("Location: index.php");
exit;
?>