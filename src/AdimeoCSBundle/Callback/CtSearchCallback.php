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
    if(isset($data['callbackUrl'])){
      $curl = new CurlClient($data['callbackUrl']);
      $curl->setMethod('POST');
      $curl->setParams($params);
      $curl->getResponse();
    }
  }

  function getSettingsFields()
  {
    return array(
      'datasourceId',
      'callbackUrl'
    );
  }


}