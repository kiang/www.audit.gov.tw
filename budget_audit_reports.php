<?php

function extractLinks($text = '') {
    $links = array();
    $pos = strpos($text, 'href="');
    while (false !== $pos) {
        $link = array();
        $pos += 6;
        $posEnd = strpos($text, '"', $pos);
        $link['url'] = substr($text, $pos, $posEnd - $pos);
        $pos = strpos($text, '>', $posEnd) + 1;
        $posEnd = strpos($text, '<', $pos);
        $link['title'] = trim(substr($text, $pos, $posEnd - $pos));
        $links[] = $link;
        $pos = strpos($text, 'href="', $posEnd);
    }
    return $links;
}

$tmpPath = __DIR__ . '/cache';
$resultPath = __DIR__ . '/budget_audit_reports';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}
if (!file_exists($resultPath)) {
    mkdir($resultPath, 0777, true);
}
$listUrl = 'http://www.audit.gov.tw/files/11-1000-97.php';
$listFile = $tmpPath . '/list';
if (!file_exists($listFile)) {
    file_put_contents($listFile, file_get_contents($listUrl));
}
$list = file_get_contents($listFile);
$pos = strpos($list, '<table summary="cglist"');
$posEnd = strpos($list, '</table>', $pos);
$list = substr($list, $pos, $posEnd - $pos);
$links = extractLinks($list);
$targetFiles = array();
foreach ($links AS $link) {
    $linkFile = $tmpPath . '/' . md5($link['url']);
    if (!file_exists($linkFile)) {
        file_put_contents($linkFile, file_get_contents($link['url']));
    }
    $linkText = file_get_contents($linkFile);
    $pos = strpos($linkText, '<span class="date float-right" >');
    $posEnd = strpos($linkText, '</table>', $pos);
    $linkText = substr($linkText, $pos, $posEnd - $pos);
    $subLinks = extractLinks($linkText);
    foreach ($subLinks AS $subLink) {
        $subLinkFile = $tmpPath . '/' . md5($subLink['url']);
        if (!file_exists($subLinkFile)) {
            file_put_contents($subLinkFile, file_get_contents($subLink['url']));
        }
        $subLinkText = file_get_contents($subLinkFile);
        $pos = strpos($subLinkText, '<table class="baseTB"');
        $posEnd = strpos($subLinkText, '</table>', $pos);
        $subLinkText = substr($subLinkText, $pos, $posEnd - $pos);
        $fileLinks = extractLinks($subLinkText);
        foreach ($fileLinks AS $fileLink) {
            if (!empty($fileLink['title']) && $fileLink['title'] !== '下載附件') {
                $fileLink['title'] = implode(' > ', array(
                    $link['title'],
                    $subLink['title'],
                    $fileLink['title'],
                ));
                $targetFiles[] = $fileLink;
            }
        }
    }
}

$fh = fopen(__DIR__ . '/budget_audit_reports.csv', 'w');
fputcsv($fh, array('標題', '檔名', '來源網址'));
foreach ($targetFiles AS $targetFile) {
    $targetFile['url'] = 'http://www.audit.gov.tw' . $targetFile['url'];
    $info = pathinfo($targetFile['url']);
    $fileName = md5($targetFile['url']) . '.' . $info['extension'];
    if (!file_exists("{$resultPath}/{$fileName}")) {
        echo "downloading {$targetFile['url']}\n";
        file_put_contents("{$resultPath}/{$fileName}", file_get_contents($targetFile['url']));
    }
    fputcsv($fh, array($targetFile['title'], $fileName, $targetFile['url']));
}
fclose($fh);
