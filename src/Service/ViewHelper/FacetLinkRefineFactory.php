<?php

namespace ScriptoSearch\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use ScriptoSearch\View\Helper\FacetLinkRefine;

class FacetLinkRefineFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $application = $container->get('Application');

        $viewHelper = new FacetLinkRefine($application);

        return $viewHelper;
    }
}
