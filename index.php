<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once 'fungsi.php';
include 'koneksi.php';

  // 1. TANGANI UPLOAD DOKUMEN (LANGSUNG)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = mysqli_real_escape_string($konek, $file['name']);
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileTmp  = $file['tmp_name'];

        $teks = "";
        if ($fileType == 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            try {
                $pdf = $parser->parseFile($fileTmp);
                $teks = $pdf->getText();
            } catch (Exception $e) { $teks = ""; }
        } elseif (in_array($fileType, ['docx', 'doc'])) {
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($fileTmp);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $teks .= $element->getText() . " ";
                        }
                    }
                }
            } catch (Exception $e) { $teks = ""; }
        } else {
            $teks = file_get_contents($fileTmp);
        }
        $teks = mysqli_real_escape_string($konek, $teks);
        
        mysqli_query($konek, "INSERT INTO tbdokumen (nama_file, file_type, isi_dokumen) VALUES ('$fileName', '$fileType', '$teks')");
        
        header("Location: ?tab=docs");
        exit;
    }
}

$get_keyword_user = isset($_GET['search']) ? trim($_GET['search']) : '';
$has_search = !empty($get_keyword_user);

if ($has_search) {
    simpanKeyword($get_keyword_user);
    $idx_count = (int)mysqli_fetch_assoc(mysqli_query($konek, "SELECT COUNT(DISTINCT DocId) AS n FROM tbindex"))['n'];
    $doc_count = (int)mysqli_fetch_assoc(mysqli_query($konek, "SELECT COUNT(*) AS n FROM tbdokumen"))['n'];
    if ($idx_count !== $doc_count) {
        buatindex();
    }
    hitungbobot();             
    hitungBobotKeyword();      
    hitungDotProduct();        
    hitungSkorAkhir();      
    rankingDoC();              

}

$docs_query           = mysqli_query($konek, "SELECT id, nama_file, isi_dokumen, file_type FROM tbdokumen ORDER BY id ASC");
$prep_data            = [];
$stat_total_docs      = 0;
$stat_total_raw_words = 0;
$stat_total_tokens    = 0;
$stat_total_after_stop= 0;
$stat_total_stems     = 0;

$tab_param = isset($_GET['tab']) ? $_GET['tab'] : '';
$run_preproses = true;

if ($docs_query) {
    while ($doc = mysqli_fetch_assoc($docs_query)) {
        $stat_total_docs++;
        if ($run_preproses) {
            $nama_tanpa_ekstensi = pathinfo($doc['nama_file'], PATHINFO_FILENAME);
            $raw = $nama_tanpa_ekstensi . " " . $doc['isi_dokumen'];
            $detail_prep = preproses_detail($raw);
            if ($detail_prep) {
                $stat_total_raw_words += $detail_prep['raw_count'];
                $stat_total_tokens    += count($detail_prep['tokens']);
                $stat_total_after_stop+= count($detail_prep['after_stop']);
                $stat_total_stems     += count($detail_prep['stems']);
                $prep_data[] = [
                    'doc'        => $doc,
                    'raw_count'  => $detail_prep['raw_count'],
                    'cleaned'    => $detail_prep['cleaned'],
                    'tokens'     => $detail_prep['tokens'],
                    'removed'    => $detail_prep['removed'],
                    'after_stop' => $detail_prep['after_stop'],
                    'stems'      => $detail_prep['stems'],
                ];
            }
        } else {
            $prep_data[] = ['doc' => $doc, 'raw_count' => 0, 'cleaned' => '', 'tokens' => [], 'removed' => [], 'after_stop' => [], 'stems' => []];
        }
    }
}

$search_results     = [];
$total_hasil_result = 0;
if ($has_search) {
    $q_search = mysqli_query($konek,
        "SELECT sh.DocId, sh.Hasil_Bobot_Akhir, td.id, td.nama_file, td.isi_dokumen, td.file_type
         FROM simpan_hasil sh
         INNER JOIN tbdokumen td ON sh.DocId = td.id
         WHERE sh.Hasil_Bobot_Akhir > 0
         ORDER BY sh.Hasil_Bobot_Akhir DESC, td.nama_file ASC"
    );
    if ($q_search) {
        while ($r = mysqli_fetch_assoc($q_search)) {
            $search_results[] = $r;
        }
        $total_hasil_result = count($search_results);
    }
}

$res_n   = mysqli_query($konek, "SELECT COUNT(*) AS n FROM tbdokumen");
$N_docs  = ($res_n) ? (int)mysqli_fetch_assoc($res_n)['n'] : 1;
$df_map  = [];
$res_df  = mysqli_query($konek, "SELECT Term, COUNT(DISTINCT DocId) AS df FROM tbindex GROUP BY Term");
if ($res_df) {
    while ($r = mysqli_fetch_assoc($res_df)) $df_map[$r['Term']] = (int)$r['df'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin">
    <title>SISTEM IR — TF-IDF</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
<style>

:root {
    --bg:        #f4f6fb;
    --surface:   #ffffff;
    --surface2:  #f1f4f9;
    --border:    #e2e6f0;
    --accent:    #3b82f6;
    --accent2:   #6366f1;
    --accent3:   #10b981;
    --warn:      #d97706;
    --danger:    #dc2626;
    --text:      #1e2432;
    --text-muted:#8892a6;
    --text-dim:  #566073;
    --sidebar-w: 240px;
    --font-mono: 'Space Mono', monospace;
    --font-body: 'DM Sans', sans-serif;
    --radius:    10px;
    --transition: 0.22s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body { background: var(--bg); color: var(--text); font-family: var(--font-body); font-size: 14px; line-height: 1.6; }
a { color: var(--accent); text-decoration: none; }
a:hover { color: #2563eb; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

#app { display: flex; height: 100vh; overflow: hidden; }
#sidebar { width: var(--sidebar-w); min-width: var(--sidebar-w); background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: relative; z-index: 10; box-shadow: 1px 0 3px rgba(0,0,0,.03); }
.sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.sidebar-logo .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(59,130,246,.28); }
.sidebar-logo h1 { font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--text); letter-spacing: 0.08em; line-height: 1.4; text-transform: uppercase; }
.sidebar-logo p { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
.sidebar-nav { flex: 1; overflow-y: auto; padding: 12px 0; }
.nav-section-label { font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 8px 20px 4px; }
.nav-item { display: flex; align-items: center; gap: 11px; padding: 10px 20px; cursor: pointer; color: var(--text-dim); font-size: 13px; font-weight: 500; border: none; background: none; width: 100%; text-align: left; transition: var(--transition); position: relative; }
.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: rgba(59,130,246,.10); color: var(--accent); }
.nav-item.active::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--accent); border-radius: 0 3px 3px 0; }
.nav-item .nav-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: var(--surface2); }
.nav-item.active .nav-icon { background: rgba(59,130,246,.16); }
.nav-item .nav-label { flex: 1; }
.nav-item .nav-badge { font-size: 10px; font-family: var(--font-mono); background: var(--accent); color: #fff; padding: 1px 6px; border-radius: 10px; font-weight: 700; }
.sidebar-footer { padding: 14px 20px; border-top: 1px solid var(--border); flex-shrink: 0; }
.sidebar-footer small { font-size: 10px; color: var(--text-muted); font-family: var(--font-mono); }

#main { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.page-header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; position: sticky; top: 0; z-index: 5; }
.page-header h2 { font-family: var(--font-mono); font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: 0.06em; }
.page-header .breadcrumb-path { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 2px; }
.page-content { padding: 28px; flex: 1; }
.page-view { display: none; }
.page-view.active { display: block; animation: fadeSlideIn .2s ease; }

.card-ir { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.card-ir-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
.card-ir-header h3 { font-size: 13px; font-weight: 600; font-family: var(--font-mono); color: var(--text); margin: 0; }
.card-ir-body { padding: 18px; }

.search-wrapper { display: flex; align-items: center; background: var(--surface2); border: 1px solid var(--border); border-radius: 50px; padding: 6px 6px 6px 20px; transition: var(--transition); }
.search-wrapper:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
.search-wrapper input { flex: 1; background: transparent; border: none; outline: none; color: var(--text); font-size: 14px; font-family: var(--font-body); }
.search-wrapper input::placeholder { color: var(--text-muted); }
.search-wrapper button { background: var(--accent); border: none; color: #fff; border-radius: 50px; padding: 8px 20px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.search-wrapper button:hover { background: #2563eb; transform: scale(1.02); }

/* Tombol Silang Custom di Search */
.btn-clear-search { color: var(--text-muted); font-size: 16px; margin-right: 15px; cursor: pointer; transition: color 0.2s; }
.btn-clear-search:hover { color: var(--danger); }

.stat-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.stat-pill { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 10px 16px; min-width: 110px; flex: 1; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.stat-pill .sp-num { font-family: var(--font-mono); font-size: 22px; font-weight: 700; line-height: 1; }
.stat-pill .sp-lbl { font-size: 10px; color: var(--text-muted); margin-top: 3px; text-transform: uppercase; }
.c-blue .sp-num { color: var(--accent); } .c-green .sp-num { color: var(--accent3); } .c-warn .sp-num { color: var(--warn); } .c-purple .sp-num { color: #7c3aed; } .c-red .sp-num { color: var(--danger); }

.ir-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ir-table thead tr { background: var(--surface2); }
.ir-table th { padding: 10px 12px; text-align: left; color: var(--text-dim); font-weight: 600; border-bottom: 1px solid var(--border); font-size: 11px; text-transform: uppercase; white-space: nowrap; }
.ir-table th.text-center, .ir-table td.text-center { text-align: center; }
.ir-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); color: var(--text-dim); vertical-align: middle; }
.ir-table tbody tr:hover { background: rgba(59,130,246,.03); }
.ir-table .num-cell { font-family: var(--font-mono); font-size: 12px; color: var(--text); }

.result-item { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 12px; position: relative; transition: var(--transition); box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.result-item:hover { border-color: rgba(59,130,246,.35); box-shadow: 0 4px 16px rgba(16,24,40,.08); }
.result-item .rank-badge { position: absolute; top: 12px; right: 14px; font-family: var(--font-mono); font-size: 10px; font-weight: 700; background: rgba(59,130,246,.1); color: var(--accent); padding: 3px 9px; border-radius: 20px; border: 1px solid rgba(59,130,246,.25); }
.result-item h5 { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.result-item .doc-snippet { font-size: 12px; color: var(--text-muted); line-height: 1.6; margin-bottom: 10px; }
.result-item .result-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.meta-tag { font-size: 10px; font-family: var(--font-mono); padding: 2px 8px; border-radius: 4px; background: var(--surface2); border: 1px solid var(--border); color: var(--text-dim); }
.type-pdf { color: #dc2626; border-color: rgba(220,38,38,.25); background: rgba(220,38,38,.06); }
.type-xlsx { color: #059669; border-color: rgba(5,150,105,.25); background: rgba(5,150,105,.06); }
.type-docx { color: #2563eb; border-color: rgba(37,99,235,.25); background: rgba(37,99,235,.06); }

.btn-ir { font-size: 11px; font-weight: 600; padding: 5px 12px; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; transition: var(--transition); }
.btn-ir:hover { opacity: .85; transform: translateY(-1px); }
.btn-ir-blue { background: rgba(59,130,246,.12); color: var(--accent); border: 1px solid rgba(59,130,246,.25); }
.btn-ir-green { background: rgba(16,185,129,.12); color: var(--accent3); border: 1px solid rgba(16,185,129,.22); }
.btn-ir-warn { background: rgba(217,119,6,.12); color: var(--warn); border: 1px solid rgba(217,119,6,.22); }
.btn-ir-danger { background: rgba(220,38,38,.1); color: var(--danger); border: 1px solid rgba(220,38,38,.2); }

.result-detail-panel { display: none; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; margin-top: 12px; overflow: hidden; }
.result-detail-panel.open { display: block; }
.rdp-header { background: rgba(59,130,246,.06); padding: 10px 14px; border-bottom: 1px solid var(--border); font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--accent); text-transform: uppercase; }
.rdp-body { padding: 14px; }
.rdp-section { margin-bottom: 16px; }
.rdp-section-title { font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.rdp-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.tbadge { display: inline-block; border-radius: 4px; padding: 1px 6px; font-size: 10px; font-weight: 500; margin: 1px 2px 1px 0; font-family: var(--font-mono); border: 1px solid transparent; }
.tbadge-token { background: rgba(16,185,129,.10); color: #047857; border-color: rgba(16,185,129,.22); }
.tbadge-stop { background: rgba(217,119,6,.08); color: #b45309; border-color: rgba(217,119,6,.18); text-decoration: line-through; opacity:.75; }
.tbadge-keep { background: rgba(99,102,241,.10); color: #4338ca; border-color: rgba(99,102,241,.22); }
.tbadge-stem { background: rgba(124,58,237,.10); color: #6d28d9; border-color: rgba(124,58,237,.22); font-weight: 700; }

.pipeline-header { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; font-size: 11px; }
.pstep { background: var(--surface2); border: 1px solid var(--border); border-radius: 20px; padding: 4px 12px; font-weight: 700; color: var(--text-dim); font-family: var(--font-mono); font-size: 10px; }
.parrow { color: var(--text-muted); font-weight: 700; }

.upload-zone { border: 2px dashed var(--border); border-radius: var(--radius); padding: 40px 20px; text-align: center; cursor: pointer; transition: var(--transition); background: var(--surface2); }
.upload-zone:hover { border-color: var(--accent); background: rgba(59,130,246,.04); }
.upload-zone i { font-size: 36px; color: var(--text-muted); margin-bottom: 12px; }
.upload-zone p { color: var(--text-muted); font-size: 13px; margin-bottom: 4px; }
.upload-zone small { font-size: 11px; color: var(--text-muted); opacity: .7; }

.form-ir input[type="text"], .form-ir input[type="file"] { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 7px; padding: 10px 14px; color: var(--text); outline: none; }
.form-ir input:focus { border-color: var(--accent); }
.form-ir label { display: block; font-size: 11px; font-weight: 600; color: var(--text-dim); margin-bottom: 6px; text-transform: uppercase; }
.form-ir .form-group { margin-bottom: 16px; }
.btn-submit { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; border: none; border-radius: 8px; padding: 10px 24px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-submit:hover { opacity: .9; }

.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .35; display: block; }
.empty-state h4 { font-size: 15px; font-weight: 600; color: var(--text-dim); margin-bottom: 6px; }

.formula-box { background: var(--surface2); border: 1px solid var(--border); border-left: 3px solid var(--accent); border-radius: 0 8px 8px 0; padding: 12px 16px; font-family: var(--font-mono); font-size: 12px; color: var(--text-dim); margin-bottom: 16px; }
.formula-box b { color: var(--accent); }

.section-divider { display: flex; align-items: center; gap: 10px; margin: 24px 0 16px; }
.section-divider span { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-dim); font-family: var(--font-mono); }
.section-divider::before, .section-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.alert-ir { padding: 12px 16px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.alert-ir-warn { background: rgba(217,119,6,.08); border: 1px solid rgba(217,119,6,.22); color: #92400e; }
.alert-ir-info { background: rgba(59,130,246,.08); border: 1px solid rgba(59,130,246,.22); color: #1d4ed8; }

@keyframes fadeSlideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.doc-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.doc-row:hover { background: rgba(59,130,246,.03); }
.doc-row .doc-no { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); min-width: 24px; }
.doc-row .doc-icon { font-size: 18px; flex-shrink: 0; }
.doc-row .doc-info { flex: 1; min-width: 0; }
.doc-row .doc-info .doc-title { font-size: 13px; font-weight: 600; color: var(--text); }
.doc-row .doc-info .doc-sub { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
.doc-row .doc-actions { display: flex; gap: 6px; }

.medal-row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 8px; margin-bottom: 8px; border: 1px solid var(--border); background: var(--surface2); }
.medal-row .medal { font-size: 22px; flex-shrink: 0; }
.medal-row .medal-info { flex: 1; min-width: 0; }
.medal-row .medal-name { font-size: 13px; font-weight: 600; color: var(--text); }
.medal-row .medal-score { font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); }
.medal-row .medal-badge { font-family: var(--font-mono); font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: rgba(59,130,246,.1); color: var(--accent); border: 1px solid rgba(59,130,246,.22); }

.kw-bobot-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.kw-bobot-panel .kbp-header { padding: 10px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--accent3); text-transform: uppercase; }

/* FIX: CSS Pagination */
.pagination-container { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 20px; border-top: 1px solid var(--border); }
.page-item { display: flex; justify-content: center; align-items: center; width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 6px; color: var(--text); text-decoration: none; font-weight: 600; font-size: 14px; background: transparent; transition: all 0.2s ease; }
.page-item:hover:not(.disabled):not(.active):not(.dots) { background: var(--surface2); border-color: var(--text-muted); }
.page-item.active { border-color: #4f46e5; color: #4f46e5; background: rgba(79,70,229,.06); }
.page-item.disabled { background: var(--surface2); color: var(--text-muted); border-color: transparent; cursor: not-allowed; opacity: 0.5; }
.page-item.dots { border: none; pointer-events: none; color: var(--text-muted); }

.text-muted-ir { color: var(--text-muted); }
.fw-mono { font-family: var(--font-mono); }
.mb-4 { margin-bottom: 20px !important; }
</style>
</head>
<body>
<div id="app">

    <aside id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">🔎</div>
            <h1>SISTEM IR<br>TF-IDF</h1>
            <p>Information Retrieval</p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Menu</div>

            <button class="nav-item active" data-page="search" onclick="switchPage('search', this)">
                <div class="nav-icon">🔍</div>
                <span class="nav-label">Cari Dokumen</span>
            </button>

            <button class="nav-item" data-page="docs" onclick="switchPage('docs', this)">
                <div class="nav-icon">📁</div>
                <span class="nav-label">Kelola Dokumen</span>
            </button>

            <button class="nav-item" data-page="analisis" onclick="switchPage('analisis', this)">
                <div class="nav-icon">📊</div>
                <span class="nav-label">Analisis Teks</span>
            </button>

            <button class="nav-item" data-page="tfidf" onclick="switchPage('tfidf', this)">
                <div class="nav-icon">🧮</div>
                <span class="nav-label">Detail Perhitungan</span>
            </button>
        </nav>

    </aside>

    <main id="main">

        <div class="page-header" id="pageHeader">
            <div>
                <h2 id="pageTitle">Cari Dokumen</h2>
                <div class="breadcrumb-path" id="pagePath">IR / Pencarian</div>
            </div>
            <?php if ($has_search): ?>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono);">
                    keyword: <b style="color:var(--accent)">"<?= htmlspecialchars($get_keyword_user) ?>"</b>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="page-content">

            <div id="page-search" class="page-view active">

                <div class="card-ir mb-4">
                    <div class="card-ir-header">
                        <span>🔍</span>
                        <h3>Pencarian Dokumen</h3>
                    </div>
                    <div class="card-ir-body">
                        <form method="GET" action="" id="searchForm">
                            <div class="search-wrapper">
                                <input type="text" name="search" id="searchInput"
                                       placeholder="masukkan kata kunci..."
                                       value="<?= htmlspecialchars($get_keyword_user) ?>"
                                       autocomplete="off">
                                
                                <?php if ($has_search): ?>
                                    <a href="index.php" class="btn-clear-search" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                                <?php endif; ?>

                                <button type="submit">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($has_search): ?>
                <div class="kw-bobot-panel mb-4">
                    <div class="kbp-header">
                        <i class=></i>Tabel Bobot Kata Kunci
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ir-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Term</th>
                                    <th class="text-center">TF</th>
                                    <th class="text-center">N</th>
                                    <th class="text-center">DF</th>
                                    <th class="text-center">N/DF</th>
                                    <th class="text-center">IDF</th>
                                    <th class="text-center" style="color:var(--accent3);">W<sub>q</sub></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $kw_res = mysqli_query($konek, "SELECT * FROM tbkeyword ORDER BY Id");
                            if ($kw_res && mysqli_num_rows($kw_res) > 0):
                                $no = 1;
                                while ($kw = mysqli_fetch_assoc($kw_res)):
                                    $term = $kw['Term'];
                                    $tf   = (float)$kw['Count'];
                                    $df   = isset($df_map[$term]) ? $df_map[$term] : 1;
                                    $npdf = ($df > 0) ? ($N_docs / $df) : 0;
                                    $idf  = ($npdf > 0) ? log($npdf, 10) : 0;
                                    $bobot = (float)$kw['Bobot'];
                            ?>
                                <tr>
                                    <td class="text-center text-muted-ir fw-mono"><?= $no++ ?></td>
                                    <td><strong style="color:var(--text)"><?= htmlspecialchars($term) ?></strong></td>
                                    <td class="text-center num-cell"><?= $tf ?></td>
                                    <td class="text-center text-muted-ir"><?= $N_docs ?></td>
                                    <td class="text-center num-cell"><?= $df ?></td>
                                    <td class="text-center num-cell"><?= number_format($npdf, 2) ?></td>
                                    <td class="text-center num-cell"><?= number_format($idf, 3) ?></td>
                                    <td class="text-center" style="color:var(--accent3);font-family:var(--font-mono);font-weight:700;"><?= number_format($bobot, 3) ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="8" class="text-center" style="color:var(--text-muted);padding:20px;">Tidak ada data keyword</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="section-divider"><span>Hasil Pencarian— <?= $total_hasil_result ?> dokumen</span></div>

                <?php if ($total_hasil_result > 0): ?>
                <?php
                $rank = 1;
                foreach ($search_results as $row):
                    $ext = strtolower($row['file_type']);
                    $icon_class  = 'fa-file-word'; $icon_color = 'color:#2563eb';
                    $type_cls = 'type-docx';
                    if ($ext == 'pdf') { $icon_class = 'fa-file-pdf'; $icon_color = 'color:#dc2626'; $type_cls = 'type-pdf'; }
                    elseif (in_array($ext, ['xlsx','xls'])) { $icon_class = 'fa-file-excel'; $icon_color = 'color:#059669'; $type_cls = 'type-xlsx'; }
                    $doc_id = $row['id'];
                    $doc_id_safe = "doc_{$doc_id}";
                ?>
                <div class="result-item">
                    <div class="rank-badge">Bobot: <?= number_format($row['Hasil_Bobot_Akhir'], 6) ?></div>
                    <h5>
                        <i class="fas <?= $icon_class ?> mr-1" style="<?= $icon_color ?>"></i>
                        <?= htmlspecialchars($row['nama_file']) ?>
                    </h5>
                    <div class="doc-snippet">
                        <?= htmlspecialchars(substr($row['isi_dokumen'], 0, 180)) ?><?= strlen($row['isi_dokumen']) > 180 ? '…' : '' ?>
                    </div>
                    <div class="result-meta">
                        <a href="view.php?id=<?= $doc_id ?>" class="btn-ir btn-ir-blue" target="_blank">
                            <i class=></i> Lihat Dokumen
                        </a>
                        <button class="btn-ir btn-ir-warn" onclick="toggleDetail('<?= $doc_id_safe ?>', this)">
                            <i class=></i> Bobot Term dokumen
                        </button>
                    </div>

                    <div class="result-detail-panel" id="detail-<?= $doc_id_safe ?>">
                        <div class="rdp-header">
                            Bobot Term — <?= htmlspecialchars($row['nama_file']) ?>
                        </div>
                        <div class="rdp-body">

                            <?php
                            $q_doc_terms = mysqli_query($konek,
                                "SELECT ti.Term, ti.Count, ti.Bobot
                                 FROM tbindex ti
                                 WHERE ti.DocId = {$doc_id}
                                 ORDER BY ti.Bobot DESC"
                            );
                            ?>
                            <div class="rdp-section">
                                <div class="rdp-section-title"></i>Bobot Term Dokumen Ini</div>
                                <div style="overflow-x:auto;">
                                    <table class="ir-table" style="font-size:11px;">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Term</th>
                                                <th class="text-center">TF</th>
                                                <th class="text-center">N</th>
                                                <th class="text-center">DF</th>
                                                <th class="text-center">N/DF</th>
                                                <th class="text-center">IDF</th>
                                                <th class="text-center" style="color:var(--accent);">W<sub>d</sub></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if ($q_doc_terms && mysqli_num_rows($q_doc_terms) > 0):
                                            $n = 1;
                                            while ($td_row = mysqli_fetch_assoc($q_doc_terms)):
                                                $t   = $td_row['Term'];
                                                $tf2 = (float)$td_row['Count'];
                                                $df2 = isset($df_map[$t]) ? $df_map[$t] : 1;
                                                $npdf2 = ($df2 > 0) ? ($N_docs / $df2) : 0;
                                                $idf2  = ($npdf2 > 0) ? log($npdf2, 10) : 0;
                                                $w2    = (float)$td_row['Bobot'];
                                        ?>
                                            <tr>
                                                <td class="text-center text-muted-ir fw-mono"><?= $n++ ?></td>
                                                <td><strong style="color:var(--text)"><?= htmlspecialchars($t) ?></strong></td>
                                                <td class="text-center num-cell"><?= $tf2 ?></td>
                                                <td class="text-center text-muted-ir"><?= $N_docs ?></td>
                                                <td class="text-center num-cell"><?= $df2 ?></td>
                                                <td class="text-center num-cell"><?= number_format($npdf2, 2) ?></td>
                                                <td class="text-center num-cell"><?= number_format($idf2, 3) ?></td>
                                                <td class="text-center" style="color:var(--accent);font-family:var(--font-mono);font-weight:700;"><?= number_format($w2, 3) ?></td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:12px;">Tidak ada data term</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <?php $rank++; endforeach; ?>
                <?php else: ?>
                <div class="alert-ir alert-ir-warn">
                    <i class="fas fa-exclamation-triangle"></i>
                    Tidak ada hasil pencarian untuk "<b><?= htmlspecialchars($get_keyword_user) ?></b>"
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h4>Masukkan Kata Kunci</h4>
                    <p>Ketik kata kunci di atas untuk mencari dokumen yang relevan</p>
                </div>
                <?php endif; ?>

            </div><div id="page-docs" class="page-view">

                <div class="row" style="gap:0;display:flex;flex-wrap:wrap;margin:-8px;">

                    <!-- Di dalam div id="page-docs" -->

                    <div style="flex:1;min-width:280px;padding:8px;">
                        <div class="card-ir">
                            <div class="card-ir-header">
                                <h3>Upload Dokumen</h3>
                            </div>
                            <div class="card-ir-body">
                                
                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert-ir <?= $_SESSION['message_type'] == 'success' ? 'alert-ir-info' : 'alert-ir-warn' ?>" style="margin-bottom: 15px;">
                                        <?= $_SESSION['message'] ?>
                                    </div>
                                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                                <?php endif; ?>

                                <!-- FORM UPLOAD - TAMPILAN BOX KOSONG TAPI TETAP BISA DIPILIH -->
                                <form method="POST" action="upload.php" enctype="multipart/form-data" class="form-ir">
                                    <div class="form-group">

                                        
                                        <!-- Input file VISIBLE, hanya diubah stylenya -->
                                        <div class="upload-zone" style="cursor: pointer; padding: 30px 20px; text-align: center; border: 2px dashed var(--border); border-radius: var(--radius); background: var(--surface2);">
                                            <i class="fas fa-cloud-upload-alt" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>
                                            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Klik untuk memilih file</p>
                                            <small style="font-size: 11px; color: var(--text-muted); opacity: 0.7;">PDF, DOCX, DOC, XLSX, XLS — maks 10MB per file</small>
                                        </div>
                                        
                                        <input type="file" 
                                            name="file_dokumen[]" 
                                            id="fileInput"
                                            accept=".pdf,.docx,.doc,.xlsx,.xls" 
                                            multiple 
                                            required
                                            style="display: none;">
                                        
                                        <div id="fileList" style="margin-top: 10px; font-size: 11px; color: var(--text-muted);"></div>
                                        
                                    </div>
                                    
                                    <button type="submit" class="btn-submit">
                                        <i class="fas fa-upload"></i> Upload Dokumen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tambahkan JavaScript minimal untuk trigger click -->
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const uploadZone = document.querySelector('.upload-zone');
                        const fileInput = document.getElementById('fileInput');
                        const fileListDiv = document.getElementById('fileList');
                        
                        if (uploadZone && fileInput) {
                            // Klik area upload untuk trigger file input
                            uploadZone.addEventListener('click', function() {
                                fileInput.click();
                            });
                            
                            // Tampilkan file yang dipilih
                            fileInput.addEventListener('change', function() {
                                if (this.files && this.files.length > 0) {
                                    let fileNames = [];
                                    for (let i = 0; i < this.files.length; i++) {
                                        fileNames.push(this.files[i].name);
                                    }
                                    
                                    if (this.files.length === 1) {
                                        uploadZone.innerHTML = '<i class="fas fa-file" style="font-size: 36px; color: var(--accent3); margin-bottom: 12px; display: block;"></i>' +
                                                            '<p style="color: var(--accent3); font-size: 13px; margin-bottom: 4px;">' + this.files[0].name + '</p>' +
                                                            '<small style="font-size: 11px; color: var(--text-muted);">' + (this.files[0].size / 1024).toFixed(1) + ' KB</small>';
                                        fileListDiv.innerHTML = '';
                                    } else {
                                        uploadZone.innerHTML = '<i class="fas fa-files" style="font-size: 36px; color: var(--accent3); margin-bottom: 12px; display: block;"></i>' +
                                                            '<p style="color: var(--accent3); font-size: 13px; margin-bottom: 4px;">' + this.files.length + ' file dipilih</p>' +
                                                            '<small style="font-size: 11px; color: var(--text-muted);">Total: ' + (this.files.length) + ' file</small>';
                                        
                                        fileListDiv.innerHTML = '<strong>File yang dipilih:</strong><br>' + 
                                                                fileNames.map(name => '• ' + name).join('<br>');
                                    }
                                } else {
                                    uploadZone.innerHTML = '<i class="fas fa-cloud-upload-alt" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px; display: block;"></i>' +
                                                        '<p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Klik untuk memilih file</p>' +
                                                        '<small style="font-size: 11px; color: var(--text-muted); opacity: 0.7;">PDF, DOCX, XLSX — maks 10MB per file</small>';
                                    fileListDiv.innerHTML = '';
                                }
                            });
                        }
                    });
                    </script>

                    <div style="flex:2;min-width:340px;padding:8px;">
                        <div class="card-ir">
                            <div class="card-ir-header">
                                <h3>Daftar Dokumen</h3>
                                <span style="margin-left:auto;font-size:11px;color:var(--text-muted);font-family:var(--font-mono);"><?= $stat_total_docs ?> dokumen</span>
                            </div>
                            <div class="card-ir-body" style="padding:0;">
                                <?php
                                $dl = mysqli_query($konek, "SELECT id, nama_file, file_type FROM tbdokumen ORDER BY id ASC");
                                if ($dl && mysqli_num_rows($dl) > 0):
                                    $dno = 1;
                                    while ($drow = mysqli_fetch_assoc($dl)):
                                        $ext2 = strtolower($drow['file_type']);
                                        $dico = 'fa-file-word'; $dcol = 'color:#2563eb';
                                        if ($ext2 == 'pdf') { $dico = 'fa-file-pdf'; $dcol = 'color:#dc2626'; }
                                        elseif (in_array($ext2, ['xlsx','xls'])) { $dico = 'fa-file-excel'; $dcol = 'color:#059669'; }
                                ?>
                                <div class="doc-row">
                                    <div class="doc-no"><?= $dno++ ?></div>
                                    <div class="doc-icon"><i class="fas <?= $dico ?>" style="<?= $dcol ?>"></i></div>
                                    <div class="doc-info">
                                        <div class="doc-title"><?= htmlspecialchars($drow['nama_file']) ?></div>
                                    </div>
                                    <div class="doc-actions">
                                        <a href="view.php?id=<?= $drow['id'] ?>" class="btn-ir btn-ir-blue" target="_blank" title="Lihat Isi">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="hapus.php?id=<?= $drow['id'] ?>" class="btn-ir btn-ir-danger"
                                           onclick="return confirm('Hapus dokumen ini?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endwhile; else: ?>
                                <div class="empty-state" style="padding:40px 20px;">
                                    <i class="fas fa-folder-open"></i>
                                    <h4>Belum Ada Dokumen</h4>
                                    <p>Upload dokumen pertamamu!</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div><div id="page-analisis" class="page-view">
                <div class="section-divider"><span>Ringkasan Statistik</span></div>
                <div class="stat-row">
                    <div class="stat-pill c-blue">
                        <div class="sp-num"><?= $stat_total_docs ?></div>
                        <div class="sp-lbl">Jumlah Dokumen</div>
                    </div>
                    <div class="stat-pill c-red">
                        <div class="sp-num"><?= number_format($stat_total_raw_words) ?></div>
                        <div class="sp-lbl">Kata Mentah</div>
                    </div>
                    <div class="stat-pill c-green">
                        <div class="sp-num"><?= number_format($stat_total_tokens) ?></div>
                        <div class="sp-lbl">Total Token</div>
                    </div>
                    <div class="stat-pill c-warn">
                        <div class="sp-num"><?= number_format($stat_total_after_stop) ?></div>
                        <div class="sp-lbl">Setelah Stopword</div>
                    </div>
                    <div class="stat-pill c-purple">
                        <div class="sp-num"><?= number_format($stat_total_stems) ?></div>
                        <div class="sp-lbl">Setelah Stemming</div>
                    </div>
                </div>

                <div class="card-ir">
                    <div class="card-ir-header">
                        <h3>Detail Preprocessing Per Dokumen</h3>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ir-table" style="width:100%; font-size:12px;">
                            <thead>
                                <tr style="background:var(--surface2);">
                                    <th style="width:50px; text-align:center;">No</th>
                                    <th>Informasi Dokumen</th>
                                    <th style="width:150px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($prep_data)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:40px; color:var(--text-muted);">
                                        <i class="fas fa-inbox" style="font-size:24px; display:block; margin-bottom:8px; opacity:.3;"></i>
                                        Tidak ada dokumen
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($prep_data as $pi => $p):
                                $doc = $p['doc'];
                                $ft  = $doc['file_type'];
                                $ico = $ft === 'pdf' ? 'fa-file-pdf' : (in_array($ft, ['xlsx','xls']) ? 'fa-file-excel' : 'fa-file-word');
                                $icol = $ft === 'pdf' ? 'color:#dc2626' : (in_array($ft, ['xlsx','xls']) ? 'color:#059669' : 'color:#2563eb');
                            ?>
                                <tr style="border-bottom:1px solid var(--border);">
                                    <td class="text-center fw-mono" style="color:var(--text-muted)"><?= $pi+1 ?></td>
                                    <td>
                                        <div style="font-weight:600; font-size:13px; color:var(--accent);">
                                            <i class="fas <?= $ico ?> mr-1" style="<?= $icol ?>"></i>
                                            <?= htmlspecialchars(substr($doc['nama_file'],0,50)) ?>
                                        </div>
                                        <small style="color:var(--text-muted)"><?= strtoupper($ft) ?> </small>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" onclick="togglePrepDetail('prep-detail-<?= $pi ?>')" style="padding: 6px 12px; font-size: 11px; background: var(--surface2); border: 1px solid var(--border); border-radius: 4px; color: var(--text); cursor: pointer; transition: 0.2s;">
                                            <i class=></i> Lihat Detail
                                        </button>
                                    </td>
                                </tr>

                                <tr id="prep-detail-<?= $pi ?>" style="display:none; background: var(--surface2);">
                                    <td colspan="3" style="padding: 0;">
                                        <div style="padding: 20px; border-bottom: 2px solid var(--border);">
                                            <h4 style="margin-top:0; margin-bottom:15px; color:var(--text); font-size:14px; border-bottom: 1px solid var(--border); padding-bottom:8px;">Tahapan Preprocessing</h4>

                                            <div style="margin-bottom:15px;">
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Teks Mentah </strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); font-size:11px; max-height:120px; overflow-y:auto; line-height:1.5;">
                                                    <?= htmlspecialchars($doc['isi_dokumen']) ?>
                                                </div>
                                            </div>
                                            
                                            <div style="margin-bottom:15px;">
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Hasil Cleaning</strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); font-size:11px; max-height:120px; overflow-y:auto; line-height:1.5; color:var(--accent);">
                                                    <?= htmlspecialchars($p['cleaned']) ?>
                                                </div>
                                            </div>

                                            <div style="margin-bottom:15px;">
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Tokenizing <span style="font-size:10px; font-weight:normal;">(<?= count($p['tokens']) ?> token)</span></strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); max-height:120px; overflow-y:auto;">
                                                    <?php foreach($p['tokens'] as $tk) echo "<span class='tbadge tbadge-token' style='margin:2px; display:inline-block;'>".htmlspecialchars($tk)."</span>"; ?>
                                                </div>
                                            </div>

                                            <div style="margin-bottom:15px;">
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Stopword Removal <span style="font-size:10px; font-weight:normal;">(<?= count($p['removed']) ?> kata)</span></strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); max-height:120px; overflow-y:auto;">
                                                    <?php foreach($p['removed'] as $rm) echo "<span class='tbadge tbadge-stop' style='margin:2px; display:inline-block;'>".htmlspecialchars($rm)."</span>"; ?>
                                                </div>
                                            </div>

                                            <div style="margin-bottom:15px;">
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Hasil Stopword Removal <span style="font-size:10px; font-weight:normal;">(<?= count($p['after_stop']) ?> kata)</span></strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); max-height:120px; overflow-y:auto;">
                                                    <?php foreach($p['after_stop'] as $ks) echo "<span class='tbadge tbadge-keep' style='margin:2px; display:inline-block;'>".htmlspecialchars($ks)."</span>"; ?>
                                                </div>
                                            </div>

                                            <div>
                                                <strong style="display:block; margin-bottom:5px; color:var(--text-dim);">Hasil Stemming <span style="font-size:10px; font-weight:normal;">(<?= count($p['stems']) ?> stem)</span></strong>
                                                <div style="background:var(--surface); padding:10px; border-radius:6px; border:1px solid var(--border); max-height:120px; overflow-y:auto;">
                                                    <?php foreach($p['stems'] as $st) echo "<span class='tbadge tbadge-stem' style='margin:2px; display:inline-block;'>".htmlspecialchars($st)."</span>"; ?>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                function togglePrepDetail(rowId) {
                    var detailRow = document.getElementById(rowId);
                    if (detailRow.style.display === "none") {
                        detailRow.style.display = "table-row";
                    } else {
                        detailRow.style.display = "none";
                    }
                }
                </script>
            </div><div id="page-tfidf" class="page-view">

                <div class="formula-box mb-4">
                    <div style="margin-bottom:4px;"><b>TF</b> = frekuensi term dalam dokumen</div>
                    <div style="margin-bottom:4px;"><b>IDF</b> = log₁₀(N / df) &nbsp;—&nbsp; N = total dokumen, df = dokumen yang mengandung term</div>
                    <div><b>W<sub>d</sub></b> = TF × IDF &nbsp;·&nbsp; <b>W<sub>q</sub></b> = TF<sub>query</sub> × IDF</div>
                </div>

                <div class="section-divider"><span>Bobot Seluruh Dokumen (W<sub>d</sub>)</span></div>
                <div class="card-ir">
                    <div class="card-ir-header">
                        <h3>Tabel TF-IDF Dokumen</h3>
                        <span style="margin-left:auto;font-size:10px;color:var(--text-muted);font-family:var(--font-mono);">N = <?= $N_docs ?> dokumen</span>
                    </div>
                    
                    <div style="overflow-x:auto;">
                        <table class="ir-table" id="tabelBobot">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Dokumen</th>
                                    <th>Term</th>
                                    <th class="text-center">TF</th>
                                    <th class="text-center">N</th>
                                    <th class="text-center">DF</th>
                                    <th class="text-center">N/DF</th>
                                    <th class="text-center">IDF</th>
                                    <th class="text-center" style="color:var(--accent);">W<sub>d</sub></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $limit = 10;
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $page = max($page, 1);
                            $offset = ($page - 1) * $limit;

                            $count_query = mysqli_query($konek, "SELECT COUNT(*) as total FROM tbindex ti JOIN tbdokumen td ON ti.DocId = td.id");
                            $total_row = mysqli_fetch_assoc($count_query);
                            $total_data = $total_row['total'];
                            $total_pages = ceil($total_data / $limit);

                            $all_docs = mysqli_query($konek,
                                "SELECT ti.DocId, ti.Term, ti.Count, ti.Bobot, td.nama_file
                                FROM tbindex ti
                                JOIN tbdokumen td ON ti.DocId = td.id
                                ORDER BY ti.DocId ASC, ti.Term ASC
                                LIMIT $limit OFFSET $offset"
                            );

                            if ($all_docs && mysqli_num_rows($all_docs) > 0):
                                $ano = $offset + 1;
                                while ($ar = mysqli_fetch_assoc($all_docs)):
                                    $at   = $ar['Term'];
                                    $atf  = (float)$ar['Count'];
                                    $adf  = isset($df_map[$at]) ? $df_map[$at] : 1;
                                    $anpdf= ($adf > 0) ? ($N_docs/$adf) : 0;
                                    $aidf = ($anpdf > 0) ? log($anpdf,10) : 0;
                                    $aw   = (float)$ar['Bobot'];
                            ?>
                                <tr>
                                    <td class="text-center fw-mono text-muted-ir"><?= $ano++ ?></td>
                                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($ar['nama_file']) ?>">
                                        <small style="color:var(--text-dim)"><?= htmlspecialchars(substr($ar['nama_file'],0,30)) ?></small>
                                    </td>
                                    <td><strong style="color:var(--text)"><?= htmlspecialchars($at) ?></strong></td>
                                    <td class="text-center num-cell"><?= $atf ?></td>
                                    <td class="text-center text-muted-ir"><?= $N_docs ?></td>
                                    <td class="text-center num-cell"><?= $adf ?></td>
                                    <td class="text-center num-cell"><?= number_format($anpdf,2) ?></td>
                                    <td class="text-center num-cell"><?= number_format($aidf,3) ?></td>
                                    <td class="text-center" style="color:var(--accent);font-family:var(--font-mono);font-weight:700;background:rgba(59,130,246,.05);"><?= number_format($aw,4) ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted);">Tidak ada data. Upload dokumen dan lakukan pencarian.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&tab=tfidf" class="page-item"><i class="fas fa-chevron-left">&lt;</i></a>
                        <?php else: ?>
                            <span class="page-item disabled"><i class="fas fa-chevron-left">&lt;</i></span>
                        <?php endif; ?>

                        <?php
                        $pages_to_show = [];
                        if ($total_pages <= 5) {
                            for ($i = 1; $i <= $total_pages; $i++) $pages_to_show[] = $i;
                        } else {
                            if ($page <= 3) {
                                $pages_to_show = [1, 2, 3, 4, '...', $total_pages];
                            } elseif ($page >= $total_pages - 2) {
                                $pages_to_show = [1, '...', $total_pages - 3, $total_pages - 2, $total_pages - 1, $total_pages];
                            } else {
                                $pages_to_show = [1, '...', $page - 1, $page, $page + 1, '...', $total_pages];
                            }
                        }

                        foreach ($pages_to_show as $p):
                            if ($p === '...'):
                        ?>
                                <span class="page-item dots">...</span>
                            <?php elseif ($p == $page): ?>
                                <span class="page-item active"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>&tab=tfidf" class="page-item"><?= $p ?></a>
                        <?php 
                            endif;
                        endforeach; 
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&tab=tfidf" class="page-item"><i class="fas fa-chevron-right">&gt;</i></a>
                        <?php else: ?>
                            <span class="page-item disabled"><i class="fas fa-chevron-right">&gt;</i></span>
                        <?php endif; ?>
                        
                    </div>
                    <?php endif; ?>
                    
                </div>

                <div class="section-divider"><span>Proses Perangkingan (W<sub>q</sub> × W<sub>d</sub>)</span></div>

                <?php if (!$has_search): ?>
                <div class="card-ir">
                    <div class="card-ir-body" style="text-align:center;padding:40px;color:var(--text-muted);">
                        <i class="fas fa-search" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3;"></i>
                        Lakukan pencarian dahulu di tab "Cari Dokumen" untuk melihat proses perangkingan dokumen.
                    </div>
                </div>
                <?php else: ?>

                <div class="formula-box mb-4">
                    <div><b>Skor Akhir</b> = Σ (W<sub>q,i</sub> × W<sub>d,i</sub>) &nbsp;—&nbsp; perkalian bobot kata kunci (W<sub>q</sub>) dengan bobot dokumen (W<sub>d</sub>) untuk setiap term yang sama, dijumlahkan per dokumen (Dot Product)</div>
                </div>

                <div class="card-ir mb-4">
                    <div class="card-ir-header">
                        <h3>Perkalian Bobot Kata Kunci × Bobot Dokumen</h3>
                        <span style="margin-left:auto;font-size:10px;color:var(--text-muted);font-family:var(--font-mono);">keyword: "<?= htmlspecialchars($get_keyword_user) ?>"</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ir-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Dokumen</th>
                                    <th>Term</th>
                                    <th class="text-center" style="color:var(--accent3);">W<sub>q</sub></th>
                                    <th class="text-center" style="color:var(--accent);">W<sub>d</sub></th>
                                    <th class="text-center">W<sub>q</sub> × W<sub>d</sub></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $mul_res = mysqli_query($konek,
                                "SELECT td.nama_file, ti.DocId, ti.Term, kw.Bobot AS Wq, ti.Bobot AS Wd, (kw.Bobot * ti.Bobot) AS Hasil
                                 FROM tbkeyword kw
                                 INNER JOIN tbindex ti ON kw.Term = ti.Term
                                 INNER JOIN tbdokumen td ON ti.DocId = td.id
                                 INNER JOIN simpan_hasil sh ON sh.DocId = ti.DocId
                                 WHERE kw.Bobot > 0 AND ti.Bobot > 0
                                 ORDER BY sh.Hasil_Bobot_Akhir DESC, ti.DocId ASC, Hasil DESC"
                            );
                            if ($mul_res && mysqli_num_rows($mul_res) > 0):
                                $mno = 1;
                                $cur_doc = null;
                                while ($mr = mysqli_fetch_assoc($mul_res)):
                                    $is_new_doc = ($cur_doc !== $mr['DocId']);
                                    $cur_doc = $mr['DocId'];
                            ?>
                                <tr<?= $is_new_doc ? ' style="border-top:2px solid var(--border);"' : '' ?>>
                                    <td class="text-center fw-mono text-muted-ir"><?= $mno++ ?></td>
                                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($mr['nama_file']) ?>">
                                        <small style="color:var(--text-dim)"><?= htmlspecialchars(substr($mr['nama_file'],0,30)) ?></small>
                                    </td>
                                    <td><strong style="color:var(--text)"><?= htmlspecialchars($mr['Term']) ?></strong></td>
                                    <td class="text-center num-cell" style="color:var(--accent3);"><?= number_format((float)$mr['Wq'],3) ?></td>
                                    <td class="text-center num-cell" style="color:var(--accent);"><?= number_format((float)$mr['Wd'],4) ?></td>
                                    <td class="text-center" style="font-family:var(--font-mono);font-weight:700;background:rgba(16,185,129,.08);color:var(--accent3);"><?= number_format((float)$mr['Hasil'],4) ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center" style="color:var(--text-muted);padding:20px;">Tidak ada term yang cocok antara kata kunci dan dokumen</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-ir">
                    <div class="card-ir-header">
                        <h3>Hasil Akhir Perangkingan</h3>
                        <span style="margin-left:auto;font-size:10px;color:var(--text-muted);font-family:var(--font-mono);">diurutkan berdasarkan Σ(W<sub>q</sub>×W<sub>d</sub>) tertinggi</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="ir-table">
                            <thead>
                                <tr>
                                    <th style="width:70px;text-align:center;">Rank</th>
                                    <th>Dokumen</th>
                                    <th class="text-center" style="color:var(--accent);">Skor Akhir (Dot Product)</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $rank_res = mysqli_query($konek,
                                "SELECT td.nama_file, sh.Hasil_Bobot_Akhir
                                 FROM simpan_hasil sh
                                 INNER JOIN tbdokumen td ON sh.DocId = td.id
                                 WHERE sh.Hasil_Bobot_Akhir > 0
                                 ORDER BY sh.Hasil_Bobot_Akhir DESC, td.nama_file ASC"
                            );
                            if ($rank_res && mysqli_num_rows($rank_res) > 0):
                                $rno = 1;
                                while ($rr = mysqli_fetch_assoc($rank_res)):
                            ?>
                                <tr<?= $rno <= 3 ? ' style="background:rgba(59,130,246,.05);"' : '' ?>>
                                    <td class="text-center fw-mono" style="color:var(--text-dim);font-size:13px;font-weight:700;"><?= $rno ?></td>
                                    <td><strong style="color:var(--text)"><?= htmlspecialchars($rr['nama_file']) ?></strong></td>
                                    <td class="text-center" style="font-family:var(--font-mono);font-weight:700;font-size:13px;color:var(--accent);"><?= number_format((float)$rr['Hasil_Bobot_Akhir'],6) ?></td>
                                </tr>
                            <?php $rno++; endwhile; else: ?>
                                <tr><td colspan="3" class="text-center" style="color:var(--text-muted);padding:20px;">Belum ada hasil ranking</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php endif; ?>

            </div></div></main></div><script src="assets/jquery/dist/jquery.min.js"></script>
<script src="assets/bootstrap/dist/js/bootstrap.min.js"></script>
<script>
const pageMeta = {
    search:  { title: 'Cari Dokumen',   path: 'IR / Pencarian' },
    docs:    { title: 'Kelola Dokumen', path: 'IR / Manajemen' },
    analisis:{ title: 'Analisis Teks',  path: 'IR / Preprocessing' },
    tfidf:   { title: 'Detail Perhitungan',  path: 'IR / Perhitungan' },
};

function switchPage(pageId, navEl) {
    document.querySelectorAll('.page-view').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    document.getElementById('page-' + pageId).classList.add('active');
    
    if(!navEl) navEl = document.querySelector(`[data-page="${pageId}"]`);
    if(navEl) navEl.classList.add('active');
    
    const meta = pageMeta[pageId] || {};
    document.getElementById('pageTitle').textContent = meta.title || pageId;
    document.getElementById('pagePath').textContent  = meta.path  || '';
    document.getElementById('main').scrollTop = 0;
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam) {
        switchPage(tabParam);
    } 
    else if (urlParams.has('search')) {
        switchPage('search');
    }
});

function toggleDetail(docId, btn) {
    const panel = document.getElementById('detail-' + docId);
    if (!panel) return;
    const isOpen = panel.classList.contains('open');
    panel.classList.toggle('open');
    btn.innerHTML = isOpen
        ? '<i class=></i> Bobot Term dokumen'
        : '<i class=></i> Bobot Term dokumen';
}

function showFileName(input) {
    const txt = document.getElementById('uploadText');
    if (input.files && input.files[0]) {
        txt.textContent = input.files[0].name;
        txt.style.color = 'var(--accent3)';
    }
}

const zone = document.getElementById('uploadZone');
if (zone) {
    zone.addEventListener('dragover', e => { 
        e.preventDefault(); 
        zone.classList.add('drag-over'); 
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const fi = document.getElementById('fileInput');
        if (fi && e.dataTransfer.files.length) {
            fi.files = e.dataTransfer.files;
            showFileName(fi);
        }
    });
}
</script>
</body>
</html>