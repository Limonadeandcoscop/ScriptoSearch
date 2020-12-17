<?php
namespace ScriptoSearch\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use ScriptoSearch\Adapter;

class AdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
     {
        $api = $container->get('Omeka\ApiManager');
        $translator = $container->get('MvcTranslator');

        $adapter = new Adapter($api, $translator);

        return $adapter;
    }
}
