<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 30/08/2016
 * Time: 17:01
 */

namespace AdimeoCSBundle\Crawl;


class CurlClient
{

  private $url;
  private $username = NULL;
  private $password = NULL;
  private $method = 'GET';
  private $params = array();

  /**
   * CurlClient constructor.
   * @param string $url
   */
  public function __construct($url)
  {
    $this->url = $url;
  }

  public function setBasicAuthCredentials($username, $password){
    $this->username = $username;
    $this->password = $password;
  }

  /**
   * @return array
   */
  public function getResponse(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'User-Agent: Lynx/2.8.4rel.1 libwww-FM/2.14'
    ));
    if($this->username != NULL && $this->password != NULL){
      curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    }
    if($this->method != 'GET'){
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
    }
    if(!empty($this->params)){
      $qs = '';
      foreach($this->params as $k => $v){
        if($qs != ''){
          $qs .= '&';
        }
        $qs .= urlencode($k) . '=' . urlencode($v);
      }
      curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);
    }

    $r = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($r, 0, $header_size);
    $headers_r = explode(PHP_EOL, $header);
    $headers = [];
    foreach($headers_r as $hh){
      if(strpos($hh, ":") !== FALSE){
        $headers[strtolower(trim(substr($hh, 0, strpos($hh, ':'))))] = trim(substr($hh, strpos($hh, ':') + 1));
      }
    }
    $body = substr($r, $header_size);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    foreach($headers as $k => $v){
      if(strtolower($k) === 'content-type' && strpos(strtolower($v), 'iso-8859-1') !== FALSE){
        $body = utf8_encode($body);
      }
    }

    return array(
      'code' => $code,
      'data' => $body,
      'headers' => $headers
    );
  }

  /**
   * @return string
   */
  public function getMethod()
  {
    return $this->method;
  }

  /**
   * @param string $method
   */
  public function setMethod($method)
  {
    $this->method = $method;
  }

  /**
   * @return array
   */
  public function getParams()
  {
    return $this->params;
  }

  /**
   * @param array $params
   */
  public function setParams($params)
  {
    $this->params = $params;
  }

}