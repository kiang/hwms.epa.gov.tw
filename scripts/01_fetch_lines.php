<?php
$baseDir = dirname(__DIR__);
include $baseDir . '/scripts/vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$csvPath = $baseDir . '/docs/data/csv';
$listFh = fopen($csvPath . '/list.csv', 'r');
fgetcsv($listFh, 2048);
while ($line = fgetcsv($listFh, 2048)) {
    $rawPath = $baseDir . '/raw/' . $line[0];
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $rawHtml = $rawPath . '/' . $line[5] . '.html';
    // $url = "https://hwms.epa.gov.tw/dispPageBox/route/routeCP.aspx?ddsPageID=LINEINFO&dbid={$line[5]}";
    // $client->request('GET', $url);
    // file_put_contents($rawHtml, $client->getResponse()->getContent());
    $html = file_get_contents($rawHtml);
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
            if (!file_exists($rawHtml)) {
                $url = "https://hwms.epa.gov.tw/dispPageBox/route/routeCP.aspx?ddsPageID=LINEINFO&dbid={$line[5]}&p={$targetPage}";
                $client->request('GET', $url);
                file_put_contents($rawHtml, $client->getResponse()->getContent());
                echo "{$rawHtml}\n";
            }

        }
    }
}