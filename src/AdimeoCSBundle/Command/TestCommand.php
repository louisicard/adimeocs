<?php

namespace AdimeoCSBundle\Command;

use AdimeoCSBundle\Crawl\CurlClient;
use AdimeoCSBundle\Crawl\DomainCrawler;
use AdimeoCSBundle\Crawl\PageCrawler;
use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends ContainerAwareCommand
{

  protected function configure() {
    $this
      ->setName('acs:test')
      ->setDescription('Adimeo Crawling Services test')
    ;
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    $dsm = new DatastoreManager();
    $dsm->init();
    $dsm->saveDocument(array(
      'tag' => 'allo',
      'flag' => 1
    ));
    $dsm->saveDocument(array(
      'tag' => 'allo1',
      'flag' => 1
    ));
    $dsm->saveDocument(array(
      'tag' => 'allo',
      'flag' => 2
    ));
    $dsm->saveDocument(array(
      'tag' => 'allo',
      'flag' => 1
    ));
    $r = $dsm->getCrawlStats('allo');
    var_dump($r);
  }

}