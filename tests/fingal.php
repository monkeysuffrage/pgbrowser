<?
require '../pgbrowser.php';
$b = new PGBrowser();
$page = $b->get('http://data.fingal.ie/ViewDataSets/');
$nextLink = $page->at('//a[@id="lnkNext"][@href]');
$page = $page->form()->doPostBack($nextLink->getAttribute('href'));
echo preg_match ('/\b2\b/', $page->at('//span[@id="lblCurrentPage"]')->nodeValue) ? 'success' : 'failure';
?>