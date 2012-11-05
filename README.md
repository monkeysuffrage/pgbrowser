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