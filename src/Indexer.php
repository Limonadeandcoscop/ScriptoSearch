<?php
namespace ScriptoSearch;

use SolrClient;
use SolrInputDocument;
use SolrServerException;
use Omeka\Entity\Resource;
use Search\Indexer\AbstractIndexer;
use Scripto\Entity\ScriptoMedia;

class Indexer extends AbstractIndexer
{
    protected $client;
    protected $solrNode;

    public function canIndex($resourceName)
    {
        $serviceLocator = $this->getServiceLocator();
        $valueExtractorManager = $serviceLocator->get('Solr\ValueExtractorManager');
        $valueExtractor = $valueExtractorManager->get($resourceName);

        return isset($valueExtractor);
    }

    public function clearIndex()
    {
        $client = $this->getClient();
        $client->deleteByQuery('*:*');
        $client->commit();
    }

    /**
     * Used for background (bulk) indexation
     */
    public function indexResource(Resource $resource)
    {
        $this->addResource($resource);
        $this->commit();
    }

    public function indexResources(array $resources)
    {
        foreach ($resources as $resource) {
            $this->addResource($resource);
        }
        $this->commit();
    }

    public function deleteResource($resourceName, $resourceId)
    {
        $id = $this->getDocumentId($resourceName, $resourceId);
        $this->getClient()->deleteById($id);
        $this->commit();
    }

    protected function getDocumentId($resourceName, $resourceId)
    {
        return sprintf('%s:%s', $resourceName, $resourceId);
    }


    protected function addResource(ScriptoMedia $resource)
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');
        $settings = $serviceLocator->get('Omeka\Settings');
        $valueExtractorManager = $serviceLocator->get('Solr\ValueExtractorManager');
        $valueFormatterManager = $serviceLocator->get('Solr\ValueFormatterManager');
        $entityManager = $serviceLocator->get('Omeka\EntityManager');

        $resource = $api->read('scripto_media', $resource->getId())->getContent();

        $client = $this->getClient();

        $resourceName = 'scripto_media';
        $id = $this->getDocumentId($resourceName, $resource->id());

        $solrNode = $this->getSolrNode();
        $solrNodeSettings = $solrNode->settings();

        $document = new SolrInputDocument;
        $document->addField('id', $id);
        $resource_name_field = $solrNodeSettings['resource_name_field'];
        $document->addField($resource_name_field, $resourceName);

        $solrMappings = $api->search('solr_mappings', [
            'resource_name' => $resourceName,
            'solr_node_id' => $solrNode->id(),
        ])->getContent();

        $schema = $solrNode->schema();

        $valueExtractor = $valueExtractorManager->get($resourceName);
        foreach ($solrMappings as $solrMapping) {

            $solrField = $solrMapping->fieldName();

            $source = $solrMapping->source();

                    $values = $valueExtractor->extractValue($resource, $source);

            if (!is_array($values)) {
                $values = (array) $values;
            }

            $schemaField = $schema->getField($solrField);

            if (!$schemaField->isMultivalued()) {
                $values = array_slice($values, 0, 1);
            }

            $solrMappingSettings = $solrMapping->settings();
            $formatter = $solrMappingSettings['formatter'];
            if ($formatter) {
                $valueFormatter = $valueFormatterManager->get($formatter);
            }

            foreach ($values as $value) {
                if ($formatter && $valueFormatter) {
                    $value = $valueFormatter->format($value);
                }
                $document->addField($solrField, $value);
            }
        }

        try {
            $client->addDocument($document);
        } catch (SolrServerException $e) {
            $this->getLogger()->err($e);
            $this->getLogger()->err(sprintf('Indexing of resource %s failed', $resource->id()));
        }

    }

    /**
     * Used for single (live) indexation
     */
    public function indexMedia(ScriptoMedia $resource)
    {
        $this->addMedia($resource);
        $this->commit();
    }

    protected function addMedia(ScriptoMedia $resource)
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');
        $settings = $serviceLocator->get('Omeka\Settings');
        $valueExtractorManager = $serviceLocator->get('Solr\ValueExtractorManager');
        $valueFormatterManager = $serviceLocator->get('Solr\ValueFormatterManager');

        $resourceName = 'scripto_media';
        $id = $this->getDocumentId($resourceName, $resource->getId());

        $client = $this->getClient();
        $solrNode = $this->getSolrNode();
        $solrNodeSettings = $solrNode->settings();

        $document = new SolrInputDocument;
        $document->addField('id', $id);
        $resource_name_field = $solrNodeSettings['resource_name_field'];
        $document->addField($resource_name_field, $resourceName);

        $solrMappings = $api->search('solr_mappings', [
            'resource_name' => $resourceName,
            'solr_node_id' => $solrNode->id(),
        ])->getContent();

        $schema = $solrNode->schema();

        $valueExtractor = $valueExtractorManager->get($resourceName);

        foreach ($solrMappings as $solrMapping) {

            $solrField = $solrMapping->fieldName();

            $source = $solrMapping->source();

            $values = $valueExtractor->extractScriptoMediaValue($resource, $source);

            if (!is_array($values)) {
                $values = (array) $values;
            }

            $schemaField = $schema->getField($solrField);

            if (!$schemaField->isMultivalued()) {
                $values = array_slice($values, 0, 1);
            }

            $solrMappingSettings = $solrMapping->settings();
            $formatter = $solrMappingSettings['formatter'];
            if ($formatter) {
                $valueFormatter = $valueFormatterManager->get($formatter);
            }

            foreach ($values as $value) {
                if ($formatter && $valueFormatter) {
                    $value = $valueFormatter->format($value);
                }
                $document->addField($solrField, $value);
            }
        }

        try {
            $client->addDocument($document);
        } catch (SolrServerException $e) {
            $this->getLogger()->err($e);
            $this->getLogger()->err(sprintf('Indexing of resource %s failed', $resource->id()));
        }

    }

    protected function commit()
    {
        $this->getLogger()->info('Commit');
        $this->getClient()->commit();
    }

    protected function getClient()
    {
        if (!isset($this->client)) {
            $solrNode = $this->getSolrNode();
            $this->client = new SolrClient($solrNode->clientSettings());
        }

        return $this->client;
    }

    protected function getSolrNode()
    {
        if (!isset($this->solrNode)) {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');

            $solrNodeId = $this->getAdapterSetting('solr_node_id');
            if ($solrNodeId) {
                $response = $api->read('solr_nodes', $solrNodeId);
                $this->solrNode = $response->getContent();
            }
        }

        return $this->solrNode;
    }
}
