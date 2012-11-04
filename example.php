<?php
require 'pgbrowser.php';

$b = new PGBrowser();
$page = $b->get('http://www.google.com/');
$form = $page->form();
$form->set('q', 'foo');
$page = $form->submit();
echo $page->title;
?>