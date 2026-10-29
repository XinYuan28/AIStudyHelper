<?php
const MAX_EXTRACT_CHARS = 100000;

function to_utf8(string $t): string {
    $t = preg_replace('/^\xEF\xBB\xBF/', '', $t);
    if (!mb_check_encoding($t, 'UTF-8')) {
        $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }
    return $t;
}

function xml_to_text(string $xml): string {
    $xml = preg_replace('/<\/(w:p|a:p|text:p|text:h)>/i', "\n", $xml);
    $xml = preg_replace('/<(w:tab|w:br|a:br|text:tab|text:line-break|text:s)\b[^>]*\/>/i', ' ', $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n[ ]+/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function zip_open_file(string $path): ?ZipArchive {
    if (!class_exists('ZipArchive')) return null;
    $zip = new ZipArchive();
    return $zip->open($path) === true ? $zip : null;
}

function extract_docx(string $path): string {
    $zip = zip_open_file($path);
    if (!$zip) return '';
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    return $xml === false ? '' : xml_to_text($xml);
}

function extract_odt(string $path): string {
    $zip = zip_open_file($path);
    if (!$zip) return '';
    $xml = $zip->getFromName('content.xml');
    $zip->close();
    return $xml === false ? '' : xml_to_text($xml);
}

function extract_pptx(string $path): string {
    $zip = zip_open_file($path);
    if (!$zip) return '';

    $slides = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $m)) {
            $slides[(int)$m[1]] = $name;
        }
    }
    ksort($slides);

    $out = [];
    foreach ($slides as $n => $name) {
        $text = xml_to_text($zip->getFromName($name));
        if ($text !== '') $out[] = "Slide $n:\n$text";
    }
    $zip->close();
    return implode("\n\n", $out);
}

function extract_xlsx(string $path): string {
    $zip = zip_open_file($path);
    if (!$zip) return '';

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $ss, $m);
        foreach ($m[1] as $si) {
            preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $si, $t);
            $shared[] = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }

    $sheets = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $m)) {
            $sheets[(int)$m[1]] = $name;
        }
    }
    ksort($sheets);

    $out = [];
    foreach ($sheets as $n => $name) {
        $xml = $zip->getFromName($name);
        if ($xml === false) continue;

        $lines = [];
        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $xml, $rows);
        foreach ($rows[1] as $row) {
            $cells = [];
            preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $row, $cm, PREG_SET_ORDER);
            foreach ($cm as $c) {
                $attrs = $c[1];
                $inner = $c[2] ?? '';
                $type  = preg_match('/\bt="([^"]*)"/', $attrs, $tm) ? $tm[1] : '';
                $val   = '';

                if ($type === 'inlineStr') {
                    preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $inner, $t);
                    $val = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                    $val = $vm[1];
                    if ($type === 's') {
                        $val = $shared[(int)$val] ?? '';
                    } else {
                        $val = html_entity_decode($val, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
                if ($val !== '') $cells[] = $val;
            }
            if ($cells) $lines[] = implode(' | ', $cells);
        }
        if ($lines) $out[] = "Sheet $n:\n" . implode("\n", $lines);
    }
    $zip->close();
    return implode("\n\n", $out);
}

function extract_rtf(string $path): string {
    $t = file_get_contents($path);
    $t = preg_replace('/\\\\par[d]?/', "\n", $t);
    $t = preg_replace("/\\\\'[0-9a-f]{2}/i", '', $t);
    $t = preg_replace('/\\\\[a-z]+-?\d* ?/i', '', $t);
    $t = preg_replace('/[{}]/', '', $t);
    return trim(to_utf8($t));
}

function extract_text(string $path, string $ext): array {
    switch ($ext) {
        case 'docx': $text = extract_docx($path); break;
        case 'pptx': $text = extract_pptx($path); break;
        case 'xlsx': $text = extract_xlsx($path); break;
        case 'odt':  $text = extract_odt($path);  break;
        case 'rtf':  $text = extract_rtf($path);  break;
        default:     $text = to_utf8(file_get_contents($path));
    }
    $text = trim($text);

    $trimmed = false;
    if (mb_strlen($text) > MAX_EXTRACT_CHARS) {
        $text = mb_substr($text, 0, MAX_EXTRACT_CHARS);
        $trimmed = true;
    }
    return ['text' => $text, 'trimmed' => $trimmed];
}