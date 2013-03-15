<?php
class PGBrowser{ 
  var $ch, $lastUrl, $parserType;

  function __construct($parserType = null){
    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_USERAGENT, "PGBrowser/0.0.1 (http://github.com/monkeysuffrage/pgbrowser/)");
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
    $this->parserType = $parserType;
  }

  function setProxy($host, $port){
    curl_setopt($this->ch, CURLOPT_PROXY, "http://$host:$port");
  }

  function setUserAgent($string){
    curl_setopt($this->ch, CURLOPT_USERAGENT, $string);
  }

  function setTimeout($timeout){
    curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
  }

  function clean($str){
    return preg_replace(array('/&nbsp;/'), array(' '), $str);
  }

  function mock($url, $filename) {
    $response = file_get_contents($filename);
    return new PGPage($url, $this->clean($response), $this);
  }

  function get($url) {
    curl_setopt($this->ch, CURLOPT_URL, $url);
    if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
    curl_setopt($this->ch, CURLOPT_POST, false);
    $this->lastUrl = $url;
    $response = curl_exec($this->ch);
    return new PGPage($url, $this->clean($response), $this);
  }

  function post($url, $body) {
    curl_setopt($this->ch, CURLOPT_URL, $url);
    if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
    curl_setopt($this->ch, CURLOPT_POST, true);
    curl_setopt($this->ch, CURLOPT_POSTFIELDS,$body);
    $this->lastUrl = $url;
    $response = curl_exec($this->ch);
    return new PGPage($url, $this->clean($response), $this);
  }
}

class PGPage{
  var $url, $browser, $dom, $xpath, $_forms, $title, $html, $parser, $parserType;

  function __construct($url, $response, $browser){
    $this->url = $url;
    $this->html = $response;
    $this->browser = $browser;
    $this->dom = new DOMDocument();
    @$this->dom->loadHTML($response);
    $this->xpath = new DOMXPath($this->dom);
    $this->title = ($node = $this->xpath->query('//title')->item(0)) ? $node->nodeValue : '';
    $this->forms = array();
    foreach($this->xpath->query('//form') as $form){
      $this->_forms[] = new PGForm($form, $this);
    }
    $this->setParser($browser->parserType, $response);
  }

  function setParser($parserType, $body){
    switch(true){
      case preg_match('/simple/i', $parserType): $this->parserType = 'simple'; $this->parser = str_get_html($body); break;
      case preg_match('/phpquery/i', $parserType): $this->parserType = 'phpquery'; $this->parser = phpQuery::newDocumentHTML($body); break;
    }
  }

  function forms(){
    if(func_num_args()) return $this->_forms[func_get_arg(0)];
    return $this->_forms;
  }

  function form(){
    return $this->_forms[0];
  }

  function at($q, $el = null){
    switch($this->parserType){
      case 'simple':
        $doc = $el ? $el : $this->parser;
        return $doc->find($q, 0);
      case 'phpquery': return $this->search($q, $el)->eq(0);
      default: return $this->search($q, $el)->item(0);
    }
  }

  function search($q, $el = null){
    switch($this->parserType){
      case 'simple':
        $doc = $el ? $el : $this->parser;
        return $doc->find($q);
      case 'phpquery':
        phpQuery::selectDocument($this->parser);
        $doc = $el ? pq($el) : $this->parser;
        return $doc->find($q);
      default: return $this->xpath->query($q, $el);
    }
  }
}

class PGForm{
  var $dom, $page, $browser, $fields, $action, $method;

  function __construct($dom, $page){
    require_once  'phpuri.php';

    $this->page = $page;
    $this->browser = $this->page->browser;
    $this->dom = $dom;
    $this->method = strtolower($this->dom->getAttribute('method'));
    if(empty($this->method)) $this->method = 'get';
    $this->action = phpUri::parse($this->page->url)->join($this->dom->getAttribute('action'));
    $this->initFields();    
  }

  function set($key, $value){
    $this->fields[$key] = $value;
  }

  function submit(){
    $body = http_build_query($this->fields);

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
      $set = true;
      $value = $input->getAttribute('value');
      $type = $input->getAttribute('type');
      $name = $input->getAttribute('name');
      $tag = $input->tagName;
      switch(true){
        case $type == 'submit':
        case $type == 'button':
          continue 2; break;
        case $type == 'checkbox':
          if(!$input->getAttribute('checked')){continue 2; break;}
          $value = empty($value) ? 'on' : $value; break;
        case $tag == 'select':
          if($input->getAttribute('multiple')){
            // what to do here?
            $set = false;
          } else {
            if($selected = $this->page->xpath->query('.//option[@selected]', $input)->item(0)){
              $value = $selected->getAttribute('value');
            } else {
              $value = $this->page->xpath->query('.//option', $input)->item(0)->getAttribute('value');
            }
          }
      }
      if($set) $this->fields[$name] = $value;
    }
  }

  function doPostBack($attribute){
    preg_match_all("/'([^']*)'/", $attribute, $m);  
    $this->set('__EVENTTARGET', $m[1][0]);
    $this->set('__EVENTARGUMENT', $m[1][1]);
    $this->set('__ASYNCPOST', 'true');
    return $this->submit();
  }
}
?>