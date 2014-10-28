<?php
require 'vendor/autoload.php';

$b = new \PGuardiario\PGBrowser();
$page = $b->get('http://www.google.com/');
$form = $page->form();
$form->set('q', 'foo');
$page = $form->submit();
echo $page->title;
?>