<?php
session_start();
require_once '../config_ai.php';
if (!isset($_SESSION[SESSION_USER_KEY])) { header('Location: ../login.php'); exit; }

$t = ($_GET['t'] ?? 'A') === 'B' ? 'B' : 'A';
$game = $_SESSION['game_data'] ?? null;
if (!$game || ($game['template'] ?? '') !== $t) {
    echo 'No game loaded. <a href="../homepage.php">Go back</a>'; exit;
}

if ($t === 'A') {
    $diseaseData = [];
    foreach ($game['pairs'] as $i => $p) {
        $diseaseData[] = [
            'id'      => $i + 1,
            'disease' => htmlspecialchars(trim($p['term']), ENT_QUOTES, 'UTF-8'),
            'country' => htmlspecialchars(trim($p['definition']), ENT_QUOTES, 'UTF-8'),
        ];
    }
} else {
    $sentencePool = [];
    foreach ($game['sentences'] as $s) {
        $s = str_replace(["\u{2018}","\u{2019}","\u{201C}","\u{201D}","\u{2013}","\u{2014}","\u{2026}","\u{00A0}"],
                         ["'","'",'"','"','-','-','...',' '], $s);
        $s = preg_replace('/[<>&]/', '', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        if ($s !== '') $sentencePool[] = $s;
    }
}

include $t === 'B' ? 'template/game_b.php' : 'template/game_a.php';