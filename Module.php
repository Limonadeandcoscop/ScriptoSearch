<?php
namespace ScriptoSearch;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
    protected $services;

    protected $api;

    protected $em;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $this->services = $this->getServiceLocator();
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $this->em = $this->getServiceLocator()->get('Omeka\EntityManager');
    }
}

