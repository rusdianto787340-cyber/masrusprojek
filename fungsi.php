<?php
//=============== koleksi fungsi ===================
require_once __DIR__ . '/vendor/autoload.php';
use Sastrawi\Stemmer\StemmerFactory;

function preproses($teks) {
    if(empty($teks)) return "";

    // Pastikan autoload tersedia
    if (!class_exists('Sastrawi\Stemmer\StemmerFactory')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    // 1. Case folding
    $teks = strtolower(trim($teks));
    
    // 2. Cleaning
    $teks = preg_replace('/[^\w\s]/', ' ', $teks);
    $teks = preg_replace('/\d+/', '', $teks);
    $teks = preg_replace('/\s+/', ' ', $teks);
    $teks = trim($teks);
    
    // 3. Stopword Removal
    $stopwords = array(
        "yang","juga","dari","dia","kami","kamu","ini","itu","atau",
        "dan","tersebut","pada","dengan","adalah","yaitu","ke","di",
        "untuk","tidak","dalam","akan","saya","kita","mereka","ada",
        "adanya","agak","agaknya","antar","antara","apa","apakah",
        "ataukah","bagi","bahwa","oleh","saat","saja","saling",
        "sana","sini","tanpa","telah","terus","tetapi","waktu",
        "agar","bisa","bila","bukan","cara","cukup","demi",
        "hanya","hari","hingga","ia","jadi","jika","justru","karena",
        "katanya","kini","lebih","lagi","lain","lalu","malah",
        "maupun","menjadi","meski","meskipun","namun","nya","pun",
        "sangat","sampai","sebagai","sebelum","sebuah","seharusnya",
        "sejak","sekarang","selain","selama","semua","serta",
        "setiap","sudah","supaya","tapi","tentu","tiap",
        "seperti","banyak","hampir","kalau","ketika","maka",
        "paling","pernah","perlu","sama","sesuai","the","of","and","in","to","for"
    );
    
    $words = explode(' ', $teks);
    $filtered_words = array();
    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word) && strlen($word) > 2 && !in_array($word, $stopwords)) {
            $filtered_words[] = $word;
        }
    }
    
    if (empty($filtered_words)) return "";
    
    // 4. STEMMING PER KATA dengan Sastrawi
    static $stemmer = null;
    if ($stemmer === null) {
        $kamus_path = __DIR__ . '/vendor/sastrawi/sastrawi/data/kata-dasar.txt';
        $words_kamus = file($kamus_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $dictionary  = new \Sastrawi\Dictionary\ArrayDictionary($words_kamus);
        $stemmer     = new \Sastrawi\Stemmer\Stemmer($dictionary);
    }
    
    $stemmed_words = array();
    foreach ($filtered_words as $word) {
        $stemmed = $stemmer->stem($word);
        if (!empty($stemmed) && strlen($stemmed) > 1) {
            $stemmed_words[] = $stemmed;
        }
    }
    
    return trim(implode(' ', $stemmed_words));
}

function preproses_detail($teks) {
    if(empty($teks)) return null;
    if (!class_exists('Sastrawi\Stemmer\StemmerFactory')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    
    // Raw Word Count
    $raw_words = count(preg_split('/\s+/', trim($teks), -1, PREG_SPLIT_NO_EMPTY));

    // Cleaning teks
    $cleaned = strtolower(trim($teks));
    $cleaned = preg_replace('/[^\w\s]/', ' ', $cleaned);
    $cleaned = preg_replace('/\d+/', '', $cleaned);
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    $cleaned = trim($cleaned);
    
    // Stopword Removal & Tokenizing
    $stopwords = array(
        "yang","juga","dari","dia","kami","kamu","ini","itu","atau",
        "dan","tersebut","pada","dengan","adalah","yaitu","ke","di",
        "untuk","tidak","dalam","akan","saya","kita","mereka","ada",
        "adanya","agak","agaknya","antar","antara","apa","apakah",
        "ataukah","bagi","bahwa","oleh","saat","saja","saling",
        "sana","sini","tanpa","telah","terus","tetapi","waktu",
        "agar","bisa","bila","bukan","cara","cukup","demi",
        "hanya","hari","hingga","ia","jadi","jika","justru","karena",
        "katanya","kini","lebih","lagi","lain","lalu","malah",
        "maupun","menjadi","meski","meskipun","namun","nya","pun",
        "sangat","sampai","sebagai","sebelum","sebuah","seharusnya",
        "sejak","sekarang","selain","selama","semua","serta",
        "setiap","sudah","supaya","tapi","tentu","tiap",
        "seperti","banyak","hampir","kalau","ketika","maka",
        "paling","pernah","perlu","sama","sesuai","the","of","and","in","to","for"
    );
    
    $words = explode(' ', $cleaned);
    $tokens = array();
    $filtered_words = array();
    $removed_words = array();

    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $tokens[] = $word; // simpan sebagai token
            if (strlen($word) > 2 && !in_array($word, $stopwords)) {
                $filtered_words[] = $word;
            } else {
                $removed_words[] = $word;
            }
        }
    }
    
    // STEMMING KATA dengan Sastrawi
    static $stemmer = null;
    if ($stemmer === null) {
        $kamus_path = __DIR__ . '/vendor/sastrawi/sastrawi/data/kata-dasar.txt';
        $words_kamus = file($kamus_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $dictionary  = new \Sastrawi\Dictionary\ArrayDictionary($words_kamus);
        $stemmer     = new \Sastrawi\Stemmer\Stemmer($dictionary);
    }
    
    $stemmed_words = array();
    foreach ($filtered_words as $word) {
        $stemmed = $stemmer->stem($word);
        if (!empty($stemmed) && strlen($stemmed) > 1) {
            $stemmed_words[] = $stemmed;
        }
    }
    
    return [
        'raw_count'  => $raw_words,
        'cleaned'    => $cleaned,
        'tokens'     => $tokens,
        'removed'    => array_values(array_unique($removed_words)),
        'after_stop' => $filtered_words,
        'stems'      => array_values(array_unique($stemmed_words))
    ];
}

// FUNGSI BARU: Menyimpan kata kunci pencarian user ke tabel tbkeyword
// FUNGSI BARU: Menyimpan kata kunci pencarian user ke tabel tbkeyword
function simpanKeyword($query_pengguna) {
    include "koneksi.php";
    
    // 1. Kosongkan tabel keyword dari pencarian sebelumnya
    mysqli_query($konek, "TRUNCATE TABLE tbkeyword");

    // 2. Lakukan preprocessing pada kata kunci (Cleaning, Tokenizing, Stopword, Stemming)
    // Memastikan kata kunci diproses dengan cara yang sama seperti isi dokumen
    $query_terproses = preproses($query_pengguna);
    
    if (empty(trim($query_terproses))) return;

    // 3. Pecah menjadi array kata
    $kata_array = explode(" ", trim($query_terproses));

    // 4. Masukkan tiap kata ke database dan hitung frekuensinya (TF)
    foreach ($kata_array as $kata) {
        if ($kata != "") {
            $term_escape = mysqli_real_escape_string($konek, $kata);

            // Cek apakah kata ini sudah masuk ke tabel tbkeyword
            $rescount = mysqli_query($konek, "SELECT Count FROM tbkeyword WHERE Term = '$term_escape'");

            if (mysqli_num_rows($rescount) > 0) {
                // Jika sudah ada, tambah jumlahnya (Count + 1)
                $rowcount = mysqli_fetch_array($rescount);
                $count = $rowcount['Count'] + 1;
                mysqli_query($konek, "UPDATE tbkeyword SET Count = $count WHERE Term = '$term_escape'");
            } else {
                // Jika belum ada, masukkan kata baru dengan jumlah awal 1
                mysqli_query($konek, "INSERT INTO tbkeyword (Term, Count, Bobot) VALUES ('$term_escape', 1, 0)");
            }
        }
    }
}

function buatindex() {
    include "koneksi.php";
    
    // Kosongkan tabel index
    mysqli_query($konek, "TRUNCATE TABLE tbindex");
    
    // Ambil semua dokumen
    $resDokumen = mysqli_query($konek, "SELECT id, nama_file, isi_dokumen FROM tbdokumen ORDER BY id");
    
    if (!$resDokumen) {
        error_log("Error ambil dokumen: " . mysqli_error($konek));
        return false;
    }
    
    while ($row = mysqli_fetch_assoc($resDokumen)) {
        $docId = $row['id'];
        $nama_file_bersih = pathinfo($row['nama_file'], PATHINFO_FILENAME);
        
        // Gabungkan nama file dengan isi dokumen
        $gabungan_teks = $nama_file_bersih . ' ' . ($row['isi_dokumen'] ?? '');
        
        // Proses preprocessing
        $dokumen_terproses = preproses($gabungan_teks);
        
        if (empty(trim($dokumen_terproses))) {
            error_log("Dokumen ID $docId hasil preprocessing kosong");
            continue;
        }
        
        // Hitung term frequency
        $term_counts = array_count_values(explode(" ", trim($dokumen_terproses)));
        
        // Bulk insert
        $values = [];
        foreach ($term_counts as $term => $count) {
            if ($term === "" || strlen($term) < 2) continue;
            $t = mysqli_real_escape_string($konek, $term);
            $values[] = "('$t', $docId, $count)";
        }
        
        if (!empty($values)) {
            $sql = "INSERT INTO tbindex (Term, DocId, Count) VALUES " . implode(",", $values);
            if (!mysqli_query($konek, $sql)) {
                error_log("Error insert tbindex: " . mysqli_error($konek));
            }
        }
    }
    
    error_log("Indexing selesai. Total data di tbindex: " . mysqli_num_rows(mysqli_query($konek, "SELECT * FROM tbindex")));
    return true;
}

function hitungBobotKeyword() {
    include "koneksi.php";
    $resN = mysqli_query($konek, "SELECT COUNT(DISTINCT DocId) AS N FROM tbindex");
    $N    = (int)mysqli_fetch_assoc($resN)['N'];
    if ($N === 0) return;
    
    // Ambil DF semua term sekaligus dengan 1 query GROUP BY
    $dfMap = [];
    $resDF = mysqli_query($konek, "SELECT Term, COUNT(DISTINCT DocId) AS DF FROM tbindex GROUP BY Term");
    while ($r = mysqli_fetch_assoc($resDF)) $dfMap[$r['Term']] = (int)$r['DF'];
    
    // Hitung semua bobot keyword, UPDATE sekali pakai CASE
    $keyword = mysqli_query($konek, "SELECT Id, Term, Count FROM tbkeyword ORDER BY Id");
    $updates = []; $id_list = [];
    while ($row = mysqli_fetch_assoc($keyword)) {
        $DF = $dfMap[$row['Term']] ?? 0;
        $id_list[] = $row['Id'];
        if ($DF === 0) { $updates[] = "WHEN ".$row['Id']." THEN 0"; continue; }
        $w = (float)$row['Count'] * log10($N / $DF);
        $updates[] = "WHEN ".$row['Id']." THEN $w";
    }
    if (!empty($updates)) {
        mysqli_query($konek,
            "UPDATE tbkeyword SET Bobot = CASE Id " . implode(" ", $updates) .
            " ELSE 0 END WHERE Id IN (" . implode(",", $id_list) . ")"
        );
    }
}

function hitungbobot() {
    include "koneksi.php";
    $resN = mysqli_query($konek, "SELECT COUNT(DISTINCT DocId) AS N FROM tbindex");
    $N    = (int)mysqli_fetch_assoc($resN)['N'];
    if ($N === 0) return;
    
    // Ambil DF semua term sekaligus dengan 1 query GROUP BY
    $dfMap = [];
    $resDF = mysqli_query($konek, "SELECT Term, COUNT(DISTINCT DocId) AS DF FROM tbindex GROUP BY Term");
    while ($r = mysqli_fetch_assoc($resDF)) $dfMap[$r['Term']] = (int)$r['DF'];
    
    // Kumpulkan semua UPDATE dalam satu CASE expression
    $resBobot = mysqli_query($konek, "SELECT Id, Term, Count FROM tbindex ORDER BY Id");
    $updates = []; $id_list = [];
    while ($rowbobot = mysqli_fetch_assoc($resBobot)) {
        $DF = $dfMap[$rowbobot['Term']] ?? 0;
        $id_list[] = $rowbobot['Id'];
        if ($DF === 0) { $updates[] = "WHEN ".$rowbobot['Id']." THEN 0"; continue; }
        $w = (float)$rowbobot['Count'] * log10($N / $DF);
        $updates[] = "WHEN ".$rowbobot['Id']." THEN $w";
    }
    if (!empty($updates)) {
        mysqli_query($konek,
            "UPDATE tbindex SET Bobot = CASE Id " . implode(" ", $updates) .
            " ELSE 0 END WHERE Id IN (" . implode(",", $id_list) . ")"
        );
    }
}

function hitungDotProduct() {
    include "koneksi.php";
    
    mysqli_query($konek, "CREATE TABLE IF NOT EXISTS tbdotproduct (
        Id_Doc INT PRIMARY KEY,
        DotProduct FLOAT
    )");
    
    mysqli_query($konek, "TRUNCATE TABLE tbdotproduct");
    
    mysqli_query($konek,
        "INSERT INTO tbdotproduct (Id_Doc, DotProduct)
         SELECT ti.DocId, SUM(kw.Bobot * ti.Bobot) as DotProduct
         FROM tbkeyword kw
         INNER JOIN tbindex ti ON kw.Term = ti.Term
         WHERE kw.Bobot > 0 AND ti.Bobot > 0
         GROUP BY ti.DocId"
    );
}

function hitungSkorAkhir() {
    include "koneksi.php";
    
    mysqli_query($konek, "DELETE FROM simpan_hasil");
    
    mysqli_query($konek,
        "INSERT INTO simpan_hasil (DocId, Hasil_Bobot_Akhir)
         SELECT 
            dp.Id_Doc,
            dp.DotProduct AS Hasil_Bobot_Akhir
         FROM tbdotproduct dp
         WHERE dp.DotProduct > 0"
    );
    
    return true;
}

function rankingDoC() {
}

function prosesRankingDotProduct() {

    hitungBobotKeyword();

    hitungbobot();
    
    hitungDotProduct();
    
    hitungSkorAkhir();
    
}

?>