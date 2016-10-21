<?php

namespace AdimeoCSBundle\Crawl;

use AdimeoCSBundle\Callback\Callback;
use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Component\Process\Process;

class DomainCrawler
{

  const POOL_SIZE = 10;
  const CRAWL_STATUS_NEW = 2;
  const CRAWL_STATUS_PROCESSING = 1;
  const CRAWL_STATUS_DONE = 0;
  const CRAWL_SIGNATURE_FLAG = -1;

  /**
   * @var string
   */
  private $domain;

  /**
   * @var string
   */
  private $scheme;

  /**
   * @var array
   */
  private $authorizedDomains = array();

  /**
   * @var int
   */
  private $maxPages = -1;

  /**
   * @var string
   */
  private $tagPrefix;

  /**
   * @var Callback
   */
  private $callback;

  /**
   * @var array
   */
  private $settings;

  /**
   * @var Process[]
   */
  private $pool = array();

  /**
   * @var bool
   */
  private $stop = FALSE;

  /**
   * @var int
   */
  private $urlCount = 0;

  /**
   * DomainCrawler constructor.
   * @param string $domain
   * @param string $scheme
   * @param string $tagPrefix
   */
  public function __construct($domain, $scheme, $tagPrefix)
  {
    $this->domain = $domain;
    $this->scheme = $scheme;
    $this->tagPrefix = $tagPrefix;
  }

  /**
   * @return string
   */
  public function getDomain()
  {
    return $this->domain;
  }

  /**
   * @param string $domain
   */
  public function setDomain($domain)
  {
    $this->domain = $domain;
  }

  /**
   * @return string
   */
  public function getScheme()
  {
    return $this->scheme;
  }

  /**
   * @param string $scheme
   */
  public function setScheme($scheme)
  {
    $this->scheme = $scheme;
  }

  /**
   * @return array
   */
  public function getAuthorizedDomains()
  {
    return $this->authorizedDomains;
  }

  /**
   * @param array $authorizedDomains
   */
  public function setAuthorizedDomains($authorizedDomains)
  {
    $this->authorizedDomains = $authorizedDomains;
  }

  /**
   * @return int
   */
  public function getMaxPages()
  {
    return $this->maxPages;
  }

  /**
   * @param int $maxPages
   */
  public function setMaxPages($maxPages)
  {
    $this->maxPages = $maxPages;
  }

  /**
   * @return Callback
   */
  public function getCallback()
  {
    return $this->callback;
  }

  /**
   * @param Callback $callback
   */
  public function setCallback($callback)
  {
    $this->callback = $callback;
  }

  /**
   * @return array
   */
  public function getSettings()
  {
    return $this->settings;
  }

  /**
   * @param array $settings
   */
  public function setSettings($settings)
  {
    $this->settings = $settings;
  }

  public function getTag(){
    return 'crawling_' . $this->domain . '_' . $this->tagPrefix;
  }

  public function start(){
    //Start with the homepage
    $homepage = $this->scheme . '://' . $this->getDomain();
    $dsItem = new DatastoreItem();
    $dsItem->setFlag(static::CRAWL_STATUS_NEW);
    $dsItem->setTag($this->getTag());
    $dsItem->setSearchable('Crawling domain ' . $this->domain);
    $dsItem->setKey($homepage);
    $dsItem->setDatetime(new \DateTime());
    $dsItem->setClass(static::class);
    $dsItem->setData($this->getSettings());
    $dsItem->save();

    //Let's loop while there are pages to crawl
    $items = $this->getItemsToCrawl();
    while(count($items) > 0 && !$this->stop){

      //lets check if the pool has dead threads
      for ($i = 0; $i < static::POOL_SIZE; $i++) {
        if (isset($this->pool[$i])) {
          if (!$this->pool[$i]->isRunning()) {
            //the thread is finished. Lets handle the outputÂ
            $this->handlePageCrawlerOutput($this->pool[$i]->getOutput());
            unset($this->pool[$i]);
          }
        }
      }

      //lets handle each item if the pool can afford it
      foreach($items as $index => $item) {
        if($item->getFlag() == DomainCrawler::CRAWL_STATUS_NEW) {
          //lets check the pool
          for ($i = 0; $i < static::POOL_SIZE; $i++) {
            if (!isset($this->pool[$i]))  {
              $item->setFlag(DomainCrawler::CRAWL_STATUS_PROCESSING);
              $item->save();
              $crawler = new PageCrawler($item);
              $process = new Process($crawler->getCommand());
              $process->setInput($item->toJSON());
              $process->start();
              $this->pool[$i] = $process;
              print 'Launching new process on unallocated thread => PID=' . $process->getPid() . ', slot=' . ($i + 1) . PHP_EOL;
              break;
            }

          }
        }
      }
      //we should sleep for 10 ms
      usleep(10 * 1000);
      unset($items);
      //get new pages to crawl
      $items = $this->getItemsToCrawl();
    }

    //All urls have been processed, we should wait for all processes in the pool to finish
    foreach($this->pool as $process){
      if($process->isRunning()){
        $process->wait();
      }
    }

    //Now we should clean up
    $this->cleanUp();
  }

  private function handlePageCrawlerOutput($output){
    $doc = json_decode($output, TRUE);
    $this->urlCount++;
    if($this->getMaxPages() > 0 && $this->urlCount >= $this->getMaxPages()){
      $this->stop();
      return;
    }
    if($doc != null && isset($doc['data_store_item'])){
      $parent = DatastoreItem::instantiateFromArray($doc['data_store_item']);
      if($this->getCallback() != null){
        $this->getCallback()->execute($parent, $doc);
      }
      if(isset($doc['internal_links'])) {
        foreach ($doc['internal_links'] as $url) {
          $this->processUrl($url, $parent);
        }
      }
      if(!empty($this->getAuthorizedDomains()) && isset($doc['external_links'])){
        foreach($this->getAuthorizedDomains() as $domain){
          foreach($doc['external_links'] as $link){
            if(Tools::getDomain($link) == $domain){
              $this->processUrl($link, $parent);
            }
          }
        }
      }
      $parent->setFlag(DomainCrawler::CRAWL_STATUS_DONE);
      $parent->save();
    }
    if(isset($parent)) {
      print 'Crawled ' . $parent->getKey() . PHP_EOL;
    }
  }

  private function processUrl($url, DatastoreItem $parent){
    $matches = DatastoreItem::loadByProperties(array(
      'tag' => $parent->getTag(),
      'key' => $url
    ));
    if (count($matches) === 0) {
      $item = new DatastoreItem();
      $item->setClass(static::class);
      $item->setFlag(DomainCrawler::CRAWL_STATUS_NEW);
      $item->setDatetime(new \DateTime());
      $item->setKey($url);
      $item->setTag($parent->getTag());
      $item->setSearchable('Discovered ' . $url . ' from ' . $parent->getKey());
      $item->setData($parent->getData());
      $item->save();
    }
  }

  public function stop(){
    $this->stop = TRUE;
  }

  /**
   * @return DatastoreItem[]
   */
  private function getItemsToCrawl(){
    $items = DatastoreItem::loadByProperties(array(
      'tag' => $this->getTag(),
      'flag' => static::CRAWL_STATUS_NEW
    ));
    $items2 = DatastoreItem::loadByProperties(array(
      'tag' => $this->getTag(),
      'flag' => static::CRAWL_STATUS_PROCESSING
    ));
    return array_merge($items, $items2);
  }

  private function cleanUp(){
    $items = DatastoreItem::loadByProperties(array(
      'tag' => $this->getTag()
    ));
    $dm = new DatastoreManager();
    $dm->bulkDelete($items);
  }


}