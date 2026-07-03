<?php
include 'koneksi.php';
$id = $_GET['id']; 
$hapus = mysqli_query($konek, "DELETE FROM tbdokumen WHERE id = $id");
if($hapus){
    header('location:index.php?tab=docs&status=sukses');
} else {
    header('location:index.php?tab=docs&status=error');
}
?>