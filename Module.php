<?php
namespace ScriptoSearch;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

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


    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
         $sharedEventManager->attach(
            'Scripto\Api\Adapter\ScriptoMediaAdapter',
            'api.hydrate.post',
            [$this, 'updateScriptoMediaIndexation']
        );
    }

    public function updateScriptoMediaIndexation($event) {

        $request = $event->getParam('request');
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');

        $scriptoMediaEntity = $event->getParam('entity');

        $requestResource = $request->getResource();

        $searchIndexes = $api->search('search_indexes')->getContent();

        foreach ($searchIndexes as $searchIndex) {
            $searchIndexSettings = $searchIndex->settings();
            if (in_array($requestResource, $searchIndexSettings['resources'])) {
                $indexer = $searchIndex->indexer();
                if ($request->getOperation() == 'delete') {
                    // TODO
                } else {
                    $indexer->indexMedia($scriptoMediaEntity);
                }
            }
        }
    }
}

