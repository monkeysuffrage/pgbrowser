<?php 
class PGBrowser{ 
  var $ch, $lastUrl;

  function __construct(){
    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_USERAGENT, "PgPGBrowser/0.0.1 (http://github.com/monkeysuffrage/pgbrowser/)");
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate,identity');
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
      "Accept-Charset:	ISO-8859-1,utf-8;q=0.7,*;q=0.7",
      "Accept-Language:	en-us,en;q=0.5",
      "Connection: keep-alive",
      "Keep-Alive: 300",
      "Expect:"
    ));
    curl_setopt($this->ch, CURLOPT_COOKIEJAR, 'cookies.txt');
  }

  function setProxy($host, $port){
    curl_setopt($this->ch, CURLOPT_PROXY, "http://$host:$port");
  }

  function setTimeout($timeout){
    curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
  }

  function rel2abs($rel, $base){
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if(empty($rel)) return $base;
    if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel;
    extract(parse_url($base));
    $path = preg_replace('#/[^/]*$#', '', $path);
    if ($rel[0] == '/') $path = '';
    $abs = "$host$path/$rel";
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}
    return $scheme.'://'.$abs;
  }

  function get($url) {
    curl_setopt($this->ch, CURLOPT_URL, $url);
    if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
    curl_setopt($this->ch, CURLOPT_POST, false);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS, false);
    $this->lastUrl = $url;
    $response = curl_exec($this->ch);
    return new PGPage($url, $response, $this);
  }

  function post($url, $body) {
    curl_setopt($this->ch, CURLOPT_URL, $url);
    if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
    curl_setopt($this->ch, CURLOPT_POST, true);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS,$body);
    $this->lastUrl = $url;
    $response = curl_exec($this->ch);
    return new PGPage($url, $response, $this);
  }
}

class PGPage{
  var $url, $browser, $dom, $xpath, $_forms, $title, $html;

  function __construct($url, $response, $browser){
    $this->url = $url;
    $this->html = $response;
    $this->browser = $browser;
    $this->dom = new DOMDocument();
    @$this->dom->loadHTML($response);
    $this->xpath = new DOMXPath($this->dom);
    $this->title = $this->xpath->query('//title')->item(0)->nodeValue;
    $this->forms = array();
    foreach($this->xpath->query('//form') as $form){
      $this->_forms[] = new PGForm($form, $this);
    }
  }

  function forms(){
    if(func_num_args()) return $this->_forms[func_get_arg(0)];
    return $this->_forms;
  }

  function form(){
    return $this->_forms[0];
  }

  function at($q){
    return $this->xpath->query($q)->item(0);
  }

  function search($q){
    return $this->xpath->query($q);
  }
}

class PGForm{
  var $dom, $page, $browser, $fields, $action, $method;

  function __construct($dom, $page){
    $this->page = $page;
    $this->browser = $this->page->browser;
    $this->dom = $dom;
    $this->method = strtolower($this->dom->getAttribute('method'));
    if(empty($this->method)) $this->method = 'get';
    $this->action = $this->browser->rel2abs($this->dom->getAttribute('action'), $this->page->url);
    $this->initFields();    
  }

  function set($key, $value){
    $this->fields[$key] = $value;
  }

  function submit(){
    $f = array();
    foreach($this->fields as $key => $value) $f[] = urlencode($key) . '=' . urlencode($value);
    $body = implode($f, '&');

    switch($this->method){
      case 'get':
        $url = $this->action .'?' . $body;
        return $this->browser->get($url);
      case 'post':
        return $this->browser->post($this->action, $body);
      default: echo "Unknown form method: $this->method\n";
    }
  }

  function initFields(){
    $this->fields = array();
    foreach($this->page->xpath->query('.//input|.//select', $this->dom) as $input){
      $value = $input->getAttribute('value');
      $type = $input->getAttribute('type');
      $name = $input->getAttribute('name');
      $tag = $input->tagName;
      switch(true){
        case $type == 'submit':
          continue 2;
        case $type == 'checkbox':
          $value = empty($value) ? 'on' : $value;
        case $tag == 'select':
          if($selected = $this->page->xpath->query('.//option[@selected]', $input)->item(0)){
            $value = $selected->nodeValue;
          } else {
            $value = $this->page->xpath->query('.//option', $input)->item(0)->nodeValue;
          }
      }
      $this->fields[$name] = $value;
    }
  }

  function doPostBack($target, $argument){
    $this->set('__EVENTTARGET', $target);
    $this->set('__EVENTARGUMENT', $argument);
    return $this->submit();
  }
}

?>