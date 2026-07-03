<?php 
session_start();
include "koneksi.php";
include "fungsi.php";

// Validasi input
if(!isset($_GET['search']) || empty(trim($_GET['search']))) {
    header('location: index.php?error=empty_search');
    exit;
}

$keyword_user = trim($_GET['search']);

// Preprocessing keyword
$processed_keyword = preproses($keyword_user);

if(empty($processed_keyword)) {
    header('location: result.php?search=' . urlencode($keyword_user) . '&no_results=1');
    exit;
}

// Hapus data pencarian sebelumnya
mysqli_query($konek,"DELETE FROM tbkeyword");
mysqli_query($konek,"TRUNCATE TABLE tbindex");

// Simpan keyword yang sudah diproses ke tbkeyword
$words = explode(" ", $processed_keyword);
$words = array_count_values(array_filter($words)); // Filter empty strings dan hitung frekuensi

foreach ($words as $term => $frequency) {
    $term = mysqli_real_escape_string($konek, $term);
    $frequency = (int)$frequency;
    
    $query = "INSERT INTO tbkeyword (Term, Count) VALUES ('$term', $frequency)";
    mysqli_query($konek, $query);
}

// Proses TF-IDF dan ranking
hitungBobotKeyword();
rankingDoC();
hitungDotProduct();      // Menghitung dot product
hitungMagnitudeDokumen(); // Menghitung magnitude dokumen
hitungMagnitudeQuery();   // Menghitung magnitude query
hitungCosineSimilarity(); // Menghitung cosine similarity

// Redirect ke result page
header('location: result.php?search=' . urlencode($keyword_user));
exit;
?>