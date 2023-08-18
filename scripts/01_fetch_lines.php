<?php
$baseDir = dirname(__DIR__);
include $baseDir . '/scripts/vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$csvPath = $baseDir . '/docs/data/csv/orig';
$listFh = fopen($csvPath . '/list.csv', 'r');
fgetcsv($listFh, 2048);
while ($line = fgetcsv($listFh, 2048)) {
    $jsonPath = $baseDir . '/docs/data/json/orig/' . $line[0];
    if (!file_exists($jsonPath)) {
        mkdir($jsonPath, 0777, true);
    }
    $rawPath = $baseDir . '/raw/' . $line[0];
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $rawHtml = $rawPath . '/' . $line[5] . '.html';
    $url = "https://hwms.epa.gov.tw/dispPageBox/route/routeCP.aspx?ddsPageID=LINEINFO&dbid={$line[5]}";
    $client->request('GET', $url);
    file_put_contents($rawHtml, $client->getResponse()->getContent());
    $html = file_get_contents($rawHtml);
    $data = parseContent($html);

    $pagePos = strpos($html, '<ul class="pagination">');
    $pagePosEnd = strpos($html, '</ul>', $pagePos);
    $pageParts = explode("dbid={$line[5]}&p=", substr($html, $pagePos, $pagePosEnd - $pagePos));
    $pageDone = [
        1 => true,
    ];
    foreach ($pageParts as $k => $v) {
        $targetPage = intval($v);
        if ($targetPage > 1 && !isset($pageDone[$targetPage])) {
            $pageDone[$targetPage] = true;
            $rawHtml = $rawPath . '/' . $line[5] . '_' . $targetPage . '.html';
            $url = "https://hwms.epa.gov.tw/dispPageBox/route/routeCP.aspx?ddsPageID=LINEINFO&dbid={$line[5]}&p={$targetPage}";
            $client->request('GET', $url);
            file_put_contents($rawHtml, $client->getResponse()->getContent());
            $data2 = parseContent(file_get_contents($rawHtml));
            $data['points'] = array_merge($data['points'], $data2['points']);
        }
    }
    file_put_contents($jsonPath . '/' . $line[5] . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function parseContent($html)
{
    $data = [
        'meta' => [],
        'points' => [],
    ];
    $pos = strpos($html, '<dt>');
    if (false !== $pos) {
        $posEnd = strpos($html, '</dl>', $pos);
        $lines = explode('</dd>', substr($html, $pos, $posEnd - $pos));
        foreach ($lines as $line) {
            $cols = explode('</dt>', $line);
            if (count($cols) !== 2) {
                continue;
            }
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            $data['meta'][$cols[0]] = $cols[1];
        }
    }
    $pos = strpos($html, '<div class="dataResult">');
    if (false !== $pos) {
        $posEnd = strpos($html, '<div class="resultArea col-sm-9">', $pos);
        $lines = explode('</div>', substr($html, $pos, $posEnd - $pos));
        foreach ($lines as $line) {
            $cols = explode('</span>', $line);
            if (count($cols) !== 3) {
                continue;
            }
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            $data['meta'][$cols[1]] = $cols[0];
        }
    }

    $pos = strpos($html, '<tbody>');
    $header = ['清運序', '清運點名稱描述', '清運時間', '一般垃圾', '廚餘回收', '資源回收'];
    if (false !== $pos) {
        $posEnd = strpos($html, '</tbody>', $pos);
        $lines = explode('</tr>', substr($html, $pos, $posEnd - $pos));
        foreach ($lines as $line) {
            $cols = explode('</td>', $line);
            if (count($cols) !== 7) {
                continue;
            }
            array_pop($cols);
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            $data['points'][] = array_combine($header, $cols);
        }
    }
    return $data;
}