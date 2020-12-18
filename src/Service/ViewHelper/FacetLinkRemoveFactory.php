<?php

namespace ScriptoSearch\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use ScriptoSearch\View\Helper\FacetLinkRemove;

class FacetLinkRemoveFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $application = $container->get('Application');

        $viewHelper = new FacetLinkRemove($application);

        return $viewHelper;
    }
}
