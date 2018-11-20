<?php

namespace AdimeoCSBundle\Command;

use AdimeoCSBundle\Crawl\DomainCrawler;
use AdimeoCSBundle\Crawl\PageCrawler;
use AdimeoCSBundle\Datastore\DatastoreItem;
use AdimeoCSBundle\Datastore\DatastoreManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlerCommand extends ContainerAwareCommand
{

  protected function configure() {
    $this
      ->setName('acs:crawl')
      ->addArgument('op', InputArgument::REQUIRED, 'Operation to run')
      ->setDescription('Adimeo Crawling Services')
    ;
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    $op = $input->getArgument('op');
    $json = '';
    while (!feof(STDIN)) {
      $json .= fread(STDIN, 1024);
    }
    $data = json_decode($json, TRUE);

    if($op == 'domain'){
      if(isset($data['domain']) && isset($data['scheme'])) {

        (new DatastoreManager())->init();

        $crawler = new DomainCrawler($data['domain'], $data['scheme'], 'rnd' . rand(1, 999999));
        $crawler->setMaxPages(isset($data['maxPages']) ? $data['maxPages'] : -1);
        $crawler->setAuthorizedDomains(isset($data['authorizedDomains']) && $data['authorizedDomains'] != null ? $data['authorizedDomains'] : array());
        if(isset($data['callback'])){
          if(class_exists($data['callback'])){
            $crawler->setCallback(new $data['callback']());
          }
        }
        $crawler->setSettings($data);
        $crawler->setNoDiscovery(isset($data['noDiscovery']) ? $data['noDiscovery'] : false);
        $crawler->setIgnoreSitemap(isset($data['ignoreSitemap']) ? $data['ignoreSitemap'] : false);

        //We should save the process signature
        $item = new DatastoreItem();
        $item->setTag($crawler->getTag());
        $item->setFlag(DomainCrawler::CRAWL_SIGNATURE_FLAG);
        $item->setKey(getmypid());
        $item->setDatetime(new \DateTime());
        $item->setSearchable('Crawler for domain ' . $data['domain']);
        $item->setData($data);
        $item->save();

        $crawler->start();
      }
      else{
        $output->writeln('Missing required data "domain" and/or "scheme');
      }
    } elseif($op == 'page'){
      try {
        $item = DatastoreItem::instantiateFromArray($data);
      }catch(\Exception $ex){
        print $ex->getMessage() . PHP_EOL;
      }
      $crawler = new PageCrawler($item);
      $crawler->crawl();
    }
  }

}