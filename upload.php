<?php
require_once __DIR__ . '/vendor/autoload.php';
session_start();
include 'koneksi.php';
require_once 'fungsi.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ========== FUNGSI EKSTRAKSI TEKS ==========
function extractTextFromPDF($file_path) {
    $tesseract_path = 'C:\\laragon\\bin\\Tesseract-OCR\\tesseract.exe';
    $pdftoppm_path  = 'C:\\laragon\\bin\\poppler\\Library\\bin\\pdftoppm.exe';

    try {
        if (class_exists('Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($file_path);
                $text   = $pdf->getText();
                if (trim($text)) return $text;
            } catch (Exception $e) {}
        }

        $temp_dir = __DIR__ . '/temp_ocr/ocr_' . uniqid();
        $temp_dir = str_replace('/', '\\', $temp_dir);

        if (!mkdir($temp_dir, 0777, true)) {
            return "Error: Gagal membuat direktori temp";
        }

        $page_prefix = $temp_dir . '\\page';
        $command = '"' . $pdftoppm_path . '" -png -r 150 "' . $file_path . '" "' . $page_prefix . '" 2>&1';
        exec($command, $cmd_output, $return_code);

        $image_files = glob($temp_dir . '\\*.png');
        if (empty($image_files)) {
            foreach (glob($temp_dir . '\\*') as $f) { if (is_file($f)) unlink($f); }
            rmdir($temp_dir);
            return "Tidak ada teks yang diekstrak";
        }

        sort($image_files);
        $output_text = '';

        foreach ($image_files as $image_file) {
            $output_prefix = $temp_dir . '\\ocr_' . basename($image_file, '.png');
            $ocr_command = '"' . $tesseract_path . '" "' . $image_file . '" "' . $output_prefix . '" -l ind+eng 2>&1';
            exec($ocr_command, $ocr_out, $ocr_code);

            $txt_file = $output_prefix . '.txt';
            if (file_exists($txt_file)) {
                $output_text .= file_get_contents($txt_file) . "\n\n";
                unlink($txt_file);
            }
            unlink($image_file);
        }

        rmdir($temp_dir);
        return trim($output_text) ?: "Tidak ada teks yang diekstrak";

    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

function extractTextFromExcel($file_path, $file_ext) {
    try {
        if ($file_ext === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } elseif ($file_ext === 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } else {
            return "Format Excel tidak didukung";
        }
        
        $spreadsheet = $reader->load($file_path);
        $output_text = '';
        
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheet_name = $worksheet->getTitle();
            $output_text .= "=== Sheet: {$sheet_name} ===\n";
            
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);
                
                $row_data = [];
                foreach ($cellIterator as $cell) {
                    $row_data[] = $cell->getCalculatedValue();
                }
                
                if (!empty($row_data)) {
                    $output_text .= implode("\t", $row_data) . "\n";
                }
            }
            $output_text .= "\n";
        }
        
        return trim($output_text) ?: "Tidak ada data teks yang ditemukan dalam Excel";
        
    } catch (Exception $e) {
        return "Error membaca file Excel: " . $e->getMessage();
    }
}

function extractTextFromWord($file_path) {
    try {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_path);
        $output_text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $output_text .= $childElement->getText() . ' ';
                        }
                    }
                    $output_text .= "\n";
                } 
                elseif (method_exists($element, 'getText')) {
                    $output_text .= $element->getText() . "\n";
                }
            }
        }
        return trim($output_text) ?: "Tidak ada teks dalam dokumen Word";
    } catch (Exception $e) {
        return "Error membaca file Word: " . $e->getMessage();
    }
}

function extractDocumentText($file_path, $file_type) {
    $extracted_text = '';

    switch ($file_type) {
        case 'pdf':
            $extracted_text = extractTextFromPDF($file_path);
            break;
        case 'xlsx':
        case 'xls':
            $extracted_text = extractTextFromExcel($file_path, $file_type);
            break;
        case 'doc':
        case 'docx':
            $extracted_text = extractTextFromWord($file_path);
            break;
        default:
            $extracted_text = "Format tidak didukung";
    }

    if (strlen($extracted_text) > 50000) {
        $extracted_text = substr($extracted_text, 0, 50000) . "... [teks dipotong]";
    }

    return $extracted_text;
}

// ========== HANDLE UPLOAD ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Cek apakah ada file yang diupload
    if (!isset($_FILES['file_dokumen']) || empty($_FILES['file_dokumen']['name'][0])) {
        $_SESSION['message'] = "Error: Pilih file terlebih dahulu!";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php?tab=docs");
        exit();
    }
    
    $success_count = 0;
    $error_messages = [];
    $total_files = count($_FILES['file_dokumen']['name']);
    
    for ($i = 0; $i < $total_files; $i++) {
        
        // Skip jika error upload
        if ($_FILES['file_dokumen']['error'][$i] !== UPLOAD_ERR_OK) {
            if ($_FILES['file_dokumen']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $error_messages[] = "File " . ($i+1) . " error: kode " . $_FILES['file_dokumen']['error'][$i];
            }
            continue;
        }
        
        // Ambil data file
        $file_name = $_FILES['file_dokumen']['name'][$i];
        $file_tmp = $_FILES['file_dokumen']['tmp_name'][$i];
        $file_size = $_FILES['file_dokumen']['size'][$i];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validasi nama file - PASTIKAN TIDAK KOSONG
        if (empty($file_name)) {
            $file_name = 'unknown_' . time() . '_' . $i . '.' . $file_ext;
        }
        
        // Validasi ekstensi
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($file_ext, $allowed_ext)) {
            $error_messages[] = "'{$file_name}': format tidak didukung (hanya PDF, Word, Excel)";
            continue;
        }
        
        // Validasi ukuran (10MB)
        if ($file_size > 10 * 1024 * 1024) {
            $error_messages[] = "'{$file_name}': ukuran melebihi 10MB";
            continue;
        }
        
        // Buat folder uploads
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Simpan file
        $new_filename = uniqid() . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
        $file_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($file_tmp, $file_path)) {
            $error_messages[] = "'{$file_name}': gagal menyimpan file";
            continue;
        }
        
        // Ekstrak teks
        $isi_dokumen = extractDocumentText($file_path, $file_ext);
        
        // ESCAPE untuk keamanan
        $file_name_escaped = mysqli_real_escape_string($konek, $file_name);
        $isi_dokumen_escaped = mysqli_real_escape_string($konek, $isi_dokumen);
        $file_ext_escaped = mysqli_real_escape_string($konek, $file_ext);
        $file_path_escaped = mysqli_real_escape_string($konek, $file_path);
        
        $sql = "INSERT INTO tbdokumen (nama_file, isi_dokumen, file_type, file_size, file_path) 
                VALUES ('$file_name_escaped', '$isi_dokumen_escaped', '$file_ext_escaped', $file_size, '$file_path_escaped')";
        
        if (mysqli_query($konek, $sql)) {
            $success_count++;
        } else {
            $error_messages[] = "'{$file_name}': gagal simpan ke database - " . mysqli_error($konek);
            unlink($file_path);
        }
    }
    
    // REBUILD INDEX jika ada file yang berhasil diupload
    if ($success_count > 0) {
        if (function_exists('buatindex')) {
            buatindex();
            hitungbobot();
        }
    }
    
    // Set pesan hasil
    if ($success_count > 0 && empty($error_messages)) {
        $_SESSION['message'] = "✅ Sukses: {$success_count} dokumen berhasil diupload!";
        $_SESSION['message_type'] = "success";
    } elseif ($success_count > 0 && !empty($error_messages)) {
        $_SESSION['message'] = "⚠️ Berhasil {$success_count} file. Gagal: " . implode('; ', $error_messages);
        $_SESSION['message_type'] = "warning";
    } else {
        $_SESSION['message'] = "❌ Gagal upload: " . implode('; ', $error_messages);
        $_SESSION['message_type'] = "danger";
    }
    
} else {
    $_SESSION['message'] = "Error: Method tidak diizinkan!";
    $_SESSION['message_type'] = "danger";
}

header("Location: index.php?tab=docs");
exit();
?>