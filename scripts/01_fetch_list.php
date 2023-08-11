<?php
$baseDir = dirname(__DIR__);
include $baseDir . '/scripts/vendor/autoload.php';

use Goutte\Client;

$client = new Client();
$client->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0');

$crawler = $client->request('GET', 'https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx');

$lastPage = 1;
$csvPath = $baseDir . '/docs/data/csv';
if (!file_exists($csvPath)) {
    mkdir($csvPath, 0777, true);
}
$listFh = fopen($csvPath . '/list.csv', 'w');
fputcsv($listFh, ['縣市別', '鄉鎮市區', '清運方式', '清運路線序號', '清運路線名稱', 'dbid']);
for ($currentPage = 1; $currentPage <= $lastPage; $currentPage++) {
    $url = "https://hwms.epa.gov.tw/dispPageBox/route/routeCP.aspx?ddsPageID=ROUTE&p={$currentPage}";
    $rawHtml = $baseDir . '/raw/page_' . $currentPage . '.html';
    $client->request('GET', $url);
    file_put_contents($rawHtml, $client->getResponse()->getContent());
    $html = file_get_contents($rawHtml);
    $pos = strpos($html, '<table class="table table-striped">');
    $posEnd = strpos($html, '<!-- content END -->', $pos);
    $main = substr($html, $pos, $posEnd - $pos);
    if ($lastPage === 1) {
        $parts = explode('</li>', $main);
        array_shift($parts);
        foreach ($parts as $part) {
            if (false !== strpos($part, '移動到最後')) {
                $cols = explode('routeCP.aspx?ddsPageID=ROUTE&p=', $part);
                $cols = explode('"', $cols[1]);
                $lastPage = intval($cols[0]);
            }
        }
    }
    $lines = explode('</tr>', $main);
    foreach ($lines as $line) {
        $cols = explode('</td>', $line);
        $pos = false;
        if (isset($cols[4])) {
            $pos = strpos($cols[4], 'dbid=');
        }
        if (false !== $pos) {
            $parts = explode('&dbid=', $cols[4]);
            $parts = explode('\'', $parts[1]);
            $cols[5] = $parts[0];
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            fputcsv($listFh, $cols);
        }
    }
}