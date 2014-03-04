<?
require '../pgbrowser.php';
$b = new PGBrowser();
$b->useCache = true;

$page = $b->get('http://www.google.com/');
$form = $page->form();
$form->set('q', 'foo');
$page = $form->submit();
echo preg_match ('/foo - /', $page->title) ? 'success' : 'failure';
?>