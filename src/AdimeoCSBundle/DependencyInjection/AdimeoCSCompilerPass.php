<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 18/05/2016
 * Time: 15:31
 */

namespace AdimeoCSBundle\DependencyInjection;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdimeoCSCompilerPass implements CompilerPassInterface
{
  public function process(ContainerBuilder $container)
  {
    $services = $container->findTaggedServiceIds("adimeocs.callback");
    $container->setParameter("adimeocs.callbacks", array_keys($services));
  }


}