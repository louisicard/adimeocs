<?php

namespace AdimeoCSBundle\Callback;


use AdimeoCSBundle\Datastore\DatastoreItem;

interface Callback
{

  /**
   * @param DatastoreItem $item
   * @param mixed $document
   * @return mixed
   */
  function execute(DatastoreItem $item, $document);

  /**
   * @return array
   */
  function getSettingsFields();

}