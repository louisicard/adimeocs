<?php

namespace AdimeoCSBundle\Callback;

use AdimeoCSBundle\Crawl\CurlClient;
use AdimeoCSBundle\Datastore\DatastoreItem;

class CtSearchCallback implements Callback
{
  function execute(DatastoreItem $item, $document)
  {
    $data = $item->getData();
    $params = [];
    $params['datasourceId'] = isset($data['datasourceId']) ? $data['datasourceId'] : null;
    $params['title'] = isset($document['title']) ? $document['title'] : null;
    $params['html'] = isset($document['html']) ? $document['html'] : null;
    $params['url'] = $item->getKey();
    foreach($params as $k => $v){
      if($v == null){
        unset($params[$k]);
      }
    }
    if(isset($data['callback_url'])){
      $curl = new CurlClient($data['callback_url']);
      $curl->setMethod('POST');
      $curl->setParams($params);
      $curl->getResponse();
    }
  }

}