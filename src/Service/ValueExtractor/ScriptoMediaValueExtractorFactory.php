<?php
namespace ScriptoSearch\Service\ValueExtractor;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use ScriptoSearch\ValueExtractor\ScriptoMediaValueExtractor;

class ScriptoMediaValueExtractorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $api = $container->get('Omeka\ApiManager');
        $eventManager = $container->get('EventManager');

        $itemValueExtractor = new ScriptoMediaValueExtractor;
        $itemValueExtractor->setApiManager($api);
        $itemValueExtractor->setEventManager($eventManager);

        return $itemValueExtractor;
    }
}
