<?php
require 'vendor/autoload.php';

$browser = new \PGuardiario\PGBrowser(\PGuardiario\PGBrowser::PHPQUERY);
$page = $browser->get('http://www.google.com/search?q=php');

$result = $page->search('li.g a');

for ($i=0;$i<$result->count();$i++)
{
  echo $result[$i]->text() . "\n";
}
