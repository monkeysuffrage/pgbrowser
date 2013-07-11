<?php
class PGBrowser{ 
  var $ch, $lastUrl, $parserType, $_useCache, $_convertUrls, $visited;

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

  public function setopt($key, $value){
    curl_setopt($this->ch, $key, $value);
  }

  // private methods
  private function clean($str){
    return preg_replace(array('/&nbsp;/'), array(' '), $str);
  }

  private function cache_filename($url){
    return 'cache/' . md5($url);
  }

  public function delete_cache($url){
    unlink($this->cache_filename(($url)));
  }

  // public methods
  public function setProxy($host, $port, $user = NULL, $password = NULL){
    curl_setopt($this->ch, CURLOPT_PROXY, "http://$host:$port");
    if(!empty($user)) curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, "$user:$password");
  }

  public function setUserAgent($string){
    curl_setopt($this->ch, CURLOPT_USERAGENT, $string);
  }

  public function setTimeout($timeout){
    curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
  }

  public function useCache($bool = true){
    if($bool) @mkdir('cache', 0777);
    $this->_useCache = $bool;
  }

  public function convertUrls($bool = true){
    $this->_convertUrls = $bool;
  }

  public function visited($url){
    if(!isset($this->visited)) $this->visited = array();
    if(array_search($url, $this->visited) !== false) return true;
    $this->visited[] = $url;
    return false;
  }

  public function mock($url, $filename) {
    $response = file_get_contents($filename);
    $page = new PGPage($url, $this->clean($response), $this);
    $this->lastUrl = $url;
    return $page;
  }

  public function get($url) {
    if($this->_useCache && file_exists($this->cache_filename($url))){
      $response = file_get_contents($this->cache_filename($url));
      $page = new PGPage($url, $this->clean($response), $this);
    } else {
      curl_setopt($this->ch, CURLOPT_URL, $url);
      if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
      curl_setopt($this->ch, CURLOPT_POST, false);
      $response = curl_exec($this->ch);
      $page = new PGPage($url, $this->clean($response), $this);
      if($this->_useCache) file_put_contents($this->cache_filename($url), $response);
    }
    $this->lastUrl = $url;
    return $page;
  }

  public function setHeaders($headers){
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
  }

  public function post($url, $body, $headers = null) {
    if($this->_useCache && file_exists($this->cache_filename($url . $body))){
      $response = file_get_contents($this->cache_filename($url . $body));
      $page = new PGPage($url, $this->clean($response), $this);
    } else {
      if($headers) $this->setHeaders($headers);
      curl_setopt($this->ch, CURLOPT_URL, $url);
      if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
      curl_setopt($this->ch, CURLOPT_POST, true);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS,$body);
      $response = curl_exec($this->ch);
      $page = new PGPage($url, $this->clean($response), $this);
      if($this->_useCache) file_put_contents($this->cache_filename($url . $body), $response);
      if($headers) $this->setHeaders(preg_replace('/(.*?:).*/','\1', $headers)); // clear headers
    }
    $this->lastUrl = $url;
    return $page;
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
    if($browser->_convertUrls) $this->convertUrls();
    $this->setParser($browser->parserType, $this->html);
  }

  function convertUrls(){
    $uri = phpUri::parse($this->url);

    foreach($this->xpath->query('//*[@src]') as $el){
      $el->setAttribute('src', $uri->join($el->getAttribute('src')));
    }
    foreach($this->xpath->query('//*[@href]') as $el){
      $el->setAttribute('href', $uri->join($el->getAttribute('href')));
    }
    
    $this->html = $this->dom->saveHTML();
  }

  function __destruct(){
    if($this->browser->parserType == 'phpquery'){
      $id = phpQuery::getDocumentID($this->parser);
      phpQuery::unloadDocuments($id);
    }
  }

  // public methods
  public function setParser($parserType, $body){
    switch(true){
      case preg_match('/simple/i', $parserType): $this->parserType = 'simple'; $this->parser = str_get_html($body); break;
      case preg_match('/phpquery/i', $parserType): $this->parserType = 'phpquery'; $this->parser = @phpQuery::newDocumentHTML($body); break;
    }
  }

  public function forms(){
    if(func_num_args()) return $this->_forms[func_get_arg(0)];
    return $this->_forms;
  }

  public function form(){
    return $this->_forms[0];
  }

  public function at($q, $el = null){
    switch($this->parserType){
      case 'simple':
        $doc = $el ? $el : $this->parser;
        return $doc->find($q, 0);
      case 'phpquery': 
        $el = $this->search($q, $el)->eq(0);
        return (0 === $el->size() && $el->markupOuter() == '') ? null : $el;
      default: return $this->search($q, $el)->item(0);
    }
  }

  public function search($q, $el = null){
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
  var $dom, $page, $browser, $fields, $action, $method, $enctype;

  function __construct($dom, $page){
    require_once  'phpuri.php';

    $this->page = $page;
    $this->browser = $this->page->browser;
    $this->dom = $dom;
    $this->method = strtolower($this->dom->getAttribute('method'));
    if(empty($this->method)) $this->method = 'get';
    $this->enctype = strtolower($this->dom->getAttribute('enctype'));
    if(empty($this->enctype)) $this->enctype = '';
    $this->action = phpUri::parse($this->page->url)->join($this->dom->getAttribute('action'));
    $this->initFields();    
  }

  // private methods
  private function initFields(){
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
            } else if($option = $this->page->xpath->query('.//option[@value]', $input)->item(0)){
              $value = $option->getAttribute('value');
            } else {
              $value = '';
            }
          }
      }
      if($set) $this->fields[$name] = $value;
    }
  }

  // public methods
  public function set($key, $value){
    $this->fields[$key] = $value;
  }

  private function generate_boundary(){
    return "--". substr(md5(rand(0,32000)),0,10);

  }

  private function multipart_build_query($fields, $boundary = null){
    $retval = '';
    foreach($fields as $key => $value){
      $retval .= "--" . $boundary . "\nContent-Disposition: form-data; name=\"$key\"\n\n$value\n";
    }
    $retval .= "--" . $boundary . "--";
    return $retval;
  }


  public function submit(){
    $body = http_build_query($this->fields);

    switch($this->method){
      case 'get':
        $url = $this->action .'?' . $body;
        return $this->browser->get($url);
      case 'post':
        if('multipart/form-data' == $this->enctype){
          $boundary = $this->generate_boundary();
          $body = $this->multipart_build_query($this->fields, $boundary);
          return $this->browser->post($this->action, $body, array("Content-Type: multipart/form-data; boundary=$boundary"));
        } else {
          return $this->browser->post($this->action, $body, array("Content-Type: application/x-www-form-urlencoded"));
        }
      default: echo "Unknown form method: $this->method\n";
    }
  }

  public function doPostBack($attribute){
    preg_match_all("/['\"]([^'\"]*)['\"]/", $attribute, $m);  
    $this->set('__EVENTTARGET', $m[1][0]);
    $this->set('__EVENTARGUMENT', $m[1][1]);
    // $this->set('__ASYNCPOST', 'true');
    return $this->submit();
  }
}
?>