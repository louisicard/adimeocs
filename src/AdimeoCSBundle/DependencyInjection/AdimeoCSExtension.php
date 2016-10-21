<?php

namespace AdimeoCSBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class AdimeoCSExtension extends Extension {
  
  public function load(array $configs, ContainerBuilder $container) {
    if(count($configs) > 0){
      foreach($configs[0] as $k => $v){
        $container->setParameter('adimeo_cs.' . $k, $v);
      }
    }
    $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.yml');
  }
  
}