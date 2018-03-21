<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 30/08/2016
 * Time: 17:55
 */

namespace AdimeoCSBundle\Crawl;

class Tools
{

  public static function getCleanHTMLToXML($html){
    $options = array(
      'hide-comments' => true,
      'tidy-mark' => false,
      'indent' => true,
      'indent-spaces' => 4,
      'new-blocklevel-tags' => 'article,header,footer,section,nav,figure',
      'new-inline-tags' => 'video,audio,canvas,ruby,rt,rp,time',
      'vertical-space' => false,
      'output-xhtml' => true,
      'wrap' => 0,
      'wrap-attributes' => false,
      'break-before-br' => false,
      'vertical-space' => false,
      'wrap-script-literals' => false
    );
    $dom = new \DOMDocument();
    try{
      libxml_use_internal_errors(true);
      $cleanHtml = mb_convert_encoding(tidy_repair_string($html, $options, 'utf8'), 'HTML-ENTITIES', 'UTF-8');
      $dom->loadHTML($cleanHtml);

      //Let's test if we find links
      $testXml = simplexml_import_dom($dom);
      if(count($testXml->xpath('//a')) == 0) {
        //No links found so Tidy must have messed up
        $cleanHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($cleanHtml);
      }
      libxml_clear_errors();
    }catch(\Exception $ex){}
    return simplexml_import_dom($dom);
  }

  public static function getDomain($url){
    $parts = explode('//', $url);
    if(count($parts) > 1){
      $part = $parts[1];
      return strtolower(explode('/', $part)[0]);
    }
    return null;
  }

  public static function getScheme($url){
    $parts = explode('://', $url);
    if(count($parts) > 1){
      return strtolower($parts[0]);
    }
    return null;
  }

  public static function processUri($uri, $domain, $scheme){
    if(strpos($uri, '//') === 0){
      $uri = 'http://' . substr($uri, 2);
    }
    if(strpos($uri, '#') !== FALSE){
      return null;
    }
    elseif(strpos($uri, '/') === 0){
      return array(
        'url' => $scheme . '://' . $domain . $uri,
        'type' => 'internal'
      );
    }
    elseif(strpos($uri, '.') === 0){
      return array(
        'url' => $scheme . '://' . $domain . '/' . $uri,
        'type' => 'internal'
      );
    }
    elseif(strpos($uri, $scheme . '://' . $domain) === 0){
      return array(
        'url' => $uri,
        'type' => 'internal'
      );
    }
    elseif(strpos($uri, 'http') === 0){
      return array(
        'url' => $uri,
        'type' => 'external'
      );
    }
    elseif(strpos($uri, 'mailto:') === 0){
      return null;
    }
    else{
      return array(
        'url' => $scheme . '://' . $domain . '/' . $uri,
        'type' => 'internal'
      );
    }
  }

  public static function extractTextFromHTML($html) {
    $html = str_replace('&nbsp;', ' ', $html);
    $html = str_replace('&rsquo;', ' ', $html);
    try {
      $tidy = tidy_parse_string($html, array(), 'utf8');
      $body = tidy_get_body($tidy);
      if($body != null)
        $html = $body->value;
    } catch (Exception $ex) {

    }
    $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
    $html = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $html);
    $html = html_entity_decode($html, ENT_COMPAT | ENT_HTML401, 'utf-8');
    $html = trim(preg_replace('#<[^>]+>#', ' ', $html));
    $html_no_multiple_spaces = trim(preg_replace('!\s+!', ' ', $html));
    if(preg_match('!\s+!', $html) && !empty($html_no_multiple_spaces)){
      $html = $html_no_multiple_spaces;
    }
    $clean_html = html_entity_decode(trim(htmlentities($html, null, 'utf-8')));
    $r = empty($clean_html) ? $html : $clean_html;

    $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
    $r = preg_replace($regex, '$1', $r);
    $r = static::cleanNonUTF8($r);

    return $r;
  }

  public static function cleanNonUTF8($str){
    $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;
    $str = preg_replace($regex, '$1', $str);
    return $str;
  }

  public static function getQueryStringAsArray($uri){
    $r = array();
    $url_parts = explode('?', $uri);
    if(count($url_parts) == 2){
      $qs_parts = explode('&', $url_parts[1]);
      foreach($qs_parts as $part){
        $qs_parts = explode('=', $part);
        if(count($qs_parts) == 2){
          $r[$qs_parts[0]] = $qs_parts[1];
        }
      }
    }
    return $r;
  }

  public static function detectLanguage($text){
    $text = preg_replace('/\s/', ' ', strtolower($text));
    $words = explode(' ', $text);
    $words_count = array();
    foreach($words as $word){
      if(in_array($word, array_keys($words_count)) && isset($words_count[$word])){
        $words_count[$word]++;
      }
      else{
        $words_count[$word] = 1;
      }
    }
    $french = isset($words_count['le']) && $words_count['le'] > 3
      || isset($words_count['la']) && $words_count['la'] > 3
      || isset($words_count['est']) && $words_count['est'] > 3
      || isset($words_count['et']) && $words_count['et'] > 3
      || isset($words_count['au']) && $words_count['au'] > 3;
    $lang = $french ? 'fr' : 'not_fr';
    return $lang;
  }

  public static function getUrlsFromSitemap($url) {
    $curl = new CurlClient($url);
    $sitemapXmlData = $curl->getResponse();
    $r = [];
    if(isset($sitemapXmlData['code']) && $sitemapXmlData['code'] == 200) {
      print 'Handling sitemap.xml at ' . $url . PHP_EOL;
      $xml = simplexml_load_string(str_replace('xmlns=', 'ns=', $sitemapXmlData['data']));
      $sitemaps = $xml->xpath('//sitemapindex/sitemap');
      foreach($sitemaps as $sitemap) {
        $r = array_merge($r, static::getUrlsFromSitemap((string)$sitemap->loc));
      }
      $urls = $xml->xpath('//urlset/url');
      foreach($urls as $page) {
        $r[] = (string)$page->loc;
      }
    }
    return $r;
  }

}