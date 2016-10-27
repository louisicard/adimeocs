<?php

namespace AdimeoCSBundle;

use AdimeoCSBundle\DependencyInjection\AdimeoCSCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AdimeoCSBundle extends Bundle
{

  public function build(ContainerBuilder $container)
  {
    parent::build($container);

    $container->addCompilerPass(new AdimeoCSCompilerPass());
  }
}
