<?php
namespace ScriptoSearch;

use SolrClient;
use SolrClientException;
use SolrQuery;
use Search\Querier\AbstractQuerier;
use Search\Querier\Exception\QuerierException;
use Search\Query;
use Search\Response;

class Querier extends AbstractQuerier
{
    protected $client;
    protected $solrNode;

    protected $searchFields;

    public function query(Query $query)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');
        $api = $serviceLocator->get('Omeka\ApiManager');

        $client = $this->getClient();

        $solrNode = $this->getSolrNode();
        $solrNodeSettings = $solrNode->settings();
        $resource_name_field = $solrNodeSettings['resource_name_field'];
        $sites_field = $solrNodeSettings['sites_field'];

        $solrQuery = new SolrQuery;
        $solrQuery->setParam('defType', 'edismax');

        if (!empty($solrNodeSettings['qf'])) {
            $solrQuery->setParam('qf', $solrNodeSettings['qf']);
        }

        if (!empty($solrNodeSettings['mm'])) {
            $solrQuery->setParam('mm', $solrNodeSettings['mm']);
        }

        $uf = [];
        $searchFields = $this->getSearchFields();
        foreach ($searchFields as $name => $searchField) {
            $textFields = $searchField->textFields();
            if (!empty($textFields)) {
                $paramName = sprintf('f.%s.qf', $name);
                $solrQuery->setParam($paramName, $textFields);
                $uf[] = $name;
            }

            $facetField = $searchField->facetField();
            if (!empty($facetField)) {
                $searchFieldMapByFacetField[$facetField] = $searchField;
            }
        }

        if (!empty($uf)) {
            $solrQuery->setParam('uf', implode(' ', $uf));
        } else {
            $solrQuery->setParam('uf', '-*');
        }

        // Highlight
        $solrQuery->setParam('hl', 'true');
        $solrQuery->setParam('hl.fl', 'transcription_t');
        $solrQuery->setParam('hl.simple.pre', '<span class="highlight">');
        $solrQuery->setParam('hl.simple.post', '</span>');
        $solrQuery->setParam('hl.snippets', 300);
        $solrQuery->setParam('hl.fragsize', 200);

        $q = $query->getQuery();
        $q = $this->getQueryStringFromSearchQuery($q);
        if (empty($q)) {
            $q = '*:*';
        } elseif ($q == '__Déchiffrages__') {
            $q = '__TOTRANSCRIBE*';
        }

        $solrQuery->setQuery($q);

        $solrQuery->setGroup(true);
        $solrQuery->addGroupField($resource_name_field);

        $resources = $query->getResources();
        $fq = sprintf('%s:(%s)', $resource_name_field, implode(' OR ', $resources));

        $facetFields = $query->getFacetFields();
        if (!empty($facetFields)) {
            $solrQuery->setFacet(true);
            foreach ($facetFields as $facetField) {
                $searchField = $this->getSearchField($facetField);
                if (!$searchField) {
                    throw new QuerierException(sprintf('Field %s does not exist', $facetField));
                }
                $solrFacetField = $searchField->facetField();
                if (!$solrFacetField) {
                    throw new QuerierException(sprintf('Field %s is not facetable', $facetField));
                }

                $solrQuery->addFacetField($solrFacetField);
            }
        }

        $facetLimit = $query->getFacetLimit();
        if ($facetLimit) {
            $solrQuery->setFacetLimit($facetLimit);
        }

        $facetFilters = $query->getFacetFilters();
        if (!empty($facetFilters)) {
            foreach ($facetFilters as $name => $values) {
                $values = array_filter($values);
                foreach ($values as $value) {
                    if (is_array($value)) {
                        $value = array_filter($value);
                        if (empty($value)) {
                            continue;
                        }

                        $value = '(' . implode(' OR ', array_map([$this, 'enclose'], $value)) . ')';
                    } else {
                        $value = $this->enclose($value);
                    }

                    $searchField = $this->getSearchField($name);
                    if (!$searchField) {
                        throw new QuerierException(sprintf('Field %s does not exist', $name));
                    }
                    $solrFacetField = $searchField->facetField();
                    if (!$solrFacetField) {
                        throw new QuerierException(sprintf('Field %s is not facetable', $name));
                    }

                    $solrQuery->addFilterQuery(sprintf('%s:%s', $solrFacetField, $value));
                }
            }
        }

        $queryFilters = $query->getQueryFilters();
        foreach ($queryFilters as $queryFilter) {
            $fq = $this->getQueryStringFromSearchQuery($queryFilter);
            if (!empty($fq)) {
                $solrQuery->addFilterQuery($fq);
            }
        }


        $dateRangeFilters = $query->getDateRangeFilters();
        foreach ($dateRangeFilters as $name => $filterValues) {
            foreach ($filterValues as $filterValue) {
                $start = $filterValue['start'] ? $filterValue['start'] : '*';
                $end = $filterValue['end'] ? $filterValue['end'] : '*';
                $solrQuery->addFilterQuery("$name:[$start TO $end]");
            }
        }

        $sort = $query->getSort();
        if (isset($sort)) {
            list($sortField, $sortOrder) = explode(' ', $sort);
            $sortOrder = $sortOrder == 'asc' ? SolrQuery::ORDER_ASC : SolrQuery::ORDER_DESC;

            if ($sortField !== 'score') {
                $searchField = $this->getSearchField($sortField);
                if (!$searchField) {
                    throw new QuerierException(sprintf('Field %s does not exist', $sortField));
                }
                $solrSortField = $searchField->sortField();
                if (!$solrSortField) {
                    throw new QuerierException(sprintf('Field %s is not sortable', $sortField));
                }
                $sortField = $solrSortField;
            }

            $solrQuery->addSortField($sortField, $sortOrder);
        }

        if ($limit = $query->getLimit()) {
            $solrQuery->setGroupLimit($limit);
        }

        if ($offset = $query->getOffset()) {
            $solrQuery->setGroupOffset($offset);
        }


        try {
            $solrQueryResponse = $client->query($solrQuery);
        } catch (SolrClientException $e) {
            throw new QuerierException($e->getMessage(), $e->getCode(), $e);
        }
        $solrResponse = $solrQueryResponse->getResponse();

        $highlighting = $solrResponse['highlighting'];

        $response = new Response;
        $response->setTotalResults($solrResponse['grouped'][$resource_name_field]['matches']);

        foreach ($solrResponse['grouped'][$resource_name_field]['groups'] as $group) {
            $response->setResourceTotalResults($group['groupValue'], $group['doclist']['numFound']);
            foreach ($group['doclist']['docs'] as $doc) {
                list(, $resourceId) = explode(':', $doc['id']);
                $transcription = $doc['transcription_t'];
                $hl_transcription = @$highlighting[$doc['id']]->transcription_t[0];
                if ($q == '__TOTRANSCRIBE*') {
                    $hl_transcription = str_replace('<span class="highlight">__TOTRANSCRIBE', '<span class="highlight totranscribe">[', $hl_transcription);
                    $hl_transcription = str_replace('</span>', '', $hl_transcription);
                    $hl_transcription = str_replace('TOTRANSCRIBE__', '?]</span>', $hl_transcription);
                }

                if (empty($hl_transcription)) {
                    if (mb_strlen($transcription) > 300) {
                        $transcription = preg_replace('/\s+?(\S+)?$/', '', substr($transcription, 0, 300)).'...';
                    }
                    $hl_transcription = $transcription;
                }

                $hl_transcription = str_replace('__TOTRANSCRIBE', '[', $hl_transcription);
                $hl_transcription = str_replace('TOTRANSCRIBE__', '?]', $hl_transcription);

                $response->addResult($group['groupValue'], ['id' => $resourceId, 'status' => $doc['status_t'], 'transcription' => $hl_transcription]);
            }
        }


        if (!empty($solrResponse['facet_counts']['facet_fields'])) {
            foreach ($solrResponse['facet_counts']['facet_fields'] as $name => $values) {
                foreach ($values as $value => $count) {
                    if ($count > 0) {
                        $searchField = $searchFieldMapByFacetField[$name];
                        $response->addFacetCount($searchField->name(), $value, $count);
                    }
                }
            }
        }

        return $response;
    }

    protected function enclose($value)
    {
        return '"' . addcslashes($value, '"') . '"';
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

    protected function getQueryStringFromSearchQuery($q)
    {
        if (is_string($q)) {
            return $q;
        }

        if (is_array($q) && isset($q['match']) && !empty($q['queries'])) {
            $joiner = $q['match'] === 'any' ? ' OR ' : ' AND ';
            $parts = array_filter(array_map(function ($query) {
                return $this->getQueryStringFromSearchQuery($query);
            }, $q['queries']));

            if (!empty($parts)) {
                $qs = sprintf('(%s)', implode($joiner, $parts));
                return $qs;
            }

            return '';
        }

        if (is_array($q) && isset($q['field']) && !empty($q['term'])) {
            $searchField = $this->getSearchField($q['field']);
            if (!isset($searchField)) {
                throw new QuerierException(sprintf('Field %s does not exist', $q['field']));
            }

            switch ($q['operator']) {
                case Adapter::OPERATOR_CONTAINS_ANY_WORD:
                    $solrFields = $searchField->textFields();
                    if (empty($solrFields)) {
                        throw new QuerierException(sprintf('Field %s cannot be used with "contains any word" operator', $searchField->name()));
                    }

                    $term = $this->escape($q['term']);
                    break;

                case Adapter::OPERATOR_CONTAINS_ALL_WORDS:
                    $solrFields = $searchField->textFields();
                    if (empty($solrFields)) {
                        throw new QuerierException(sprintf('Field %s cannot be used with "contains all words" operator', $searchField->name()));
                    }

                    $term = $this->escape($q['term']);
                    $words = explode(' ', $term);
                    $term = implode(' ', array_map(function ($word) {
                        return "+$word";
                    }, $words));
                    break;

                case Adapter::OPERATOR_CONTAINS_EXPR:
                    $solrFields = $searchField->textFields();
                    if (empty($solrFields)) {
                        throw new QuerierException(sprintf('Field %s cannot be used with "contains expression" operator', $searchField->name()));
                    }

                    $term = sprintf('"%s"', $this->escape($q['term']));
                    break;

                case Adapter::OPERATOR_MATCHES_PATTERN:
                    $solrFields = $searchField->stringFields();
                    if (empty($solrFields)) {
                        throw new QuerierException(sprintf('Field %s cannot be used with "matches pattern" operator', $searchField->name()));
                    }

                    $parts = preg_split('/([*?])/', $q['term'], -1, PREG_SPLIT_DELIM_CAPTURE);
                    $term = implode('', array_map(function ($part) {
                        if ($part === '*') {
                            return '.*';
                        }
                        if ($part === '?') {
                            return '.';
                        }
                        return $this->escapeRegexp($part);
                    }, $parts));
                    $term = sprintf('/%s/', $term);
                    break;

                default:
                    throw new QuerierException(sprintf("Unknown operator '%s'", $q['operator']));
            }

            $qs = sprintf('(%s)', implode(' OR ', array_map(function ($solrField) use ($term) {
                return sprintf('%s:(%s)', $solrField, $term);
            }, array_filter(explode(' ', $solrFields)))));

            return $qs;
        }
    }

    protected function getSearchFields()
    {
        if (!$this->searchFields) {
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $searchFields = $api->search('solr_search_fields')->getContent();
            $this->searchFields = [];
            foreach ($searchFields as $searchField) {
                $this->searchFields[$searchField->name()] = $searchField;
            }
        }

        return $this->searchFields;
    }

    protected function getSearchField($name)
    {
        $searchFields = $this->getSearchFields();

        return $searchFields[$name] ?? null;
    }

    protected function escape($string)
    {
        return preg_replace('/([+\-&|!(){}[\]\^"~*?:])/', '\\\\$1', $string);
    }

    protected function escapeRegexp($string)
    {
        return preg_quote($string, '/');
    }
}
