<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "tfidf_search";

$konek = mysqli_connect($host, $user, $pass, $db);

if (!$konek) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>