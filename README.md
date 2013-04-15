PGBrowser
=========

A 'pretty good' mechanize-like php library for managing cookies and submitting forms.

```php
require 'pgbrowser.php';

$b = new PGBrowser();
$page = $b->get('http://www.google.com/');
$form = $page->form();
$form->set('q', 'foo');
$page = $form->submit();
echo $page->title;
```

Now do something with $page->html or query it with $page->xpath->query()

PGBrowser will also let you query the page with phpquery, simple-html-dom or xpath:

```php
require 'pgbrowser.php';
require 'phpquery.php';
$browser = new PGBrowser('phpquery');
$page = $browser->get('http://www.google.com/search?q=php');
foreach($page->search('li.g') as $li){
  echo $page->at('a', $li)->text() . "\n";
}
```

*New* - PGBrowser can now cache requests to disk. Cached responses go into a folder called 'cache'

```php
$browser->useCache(); // turn on cacheing
$browser->useCache(false); // turn off cacheing
```

