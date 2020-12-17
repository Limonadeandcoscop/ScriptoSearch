<?php
namespace ScriptoSearch;

use Laminas\I18n\Translator\TranslatorInterface;
use Omeka\Api\Manager as ApiManager;
use Search\Adapter\AbstractAdapter;
use Search\Api\Representation\SearchIndexRepresentation;
use Solr\Form\ConfigFieldset;

class Adapter extends AbstractAdapter
{
    const OPERATOR_CONTAINS_ANY_WORD = 'contains_any_word';
    const OPERATOR_CONTAINS_ALL_WORDS = 'contains_all_words';
    const OPERATOR_CONTAINS_EXPR = 'contains_expr';
    const OPERATOR_MATCHES_PATTERN = 'matches_pattern';

    protected $api;
    protected $translator;

    public function __construct(ApiManager $api, TranslatorInterface $translator)
    {
        $this->api = $api;
        $this->translator = $translator;
    }

    public function getLabel()
    {
        return 'Scripto';
    }

    public function getConfigFieldset()
    {
        $solrNodes = $this->api->search('solr_nodes')->getContent();

        return new ConfigFieldset(null, ['solrNodes' => $solrNodes]);
    }

    public function getHandledResources()
    {
        return [
            'scripto_media' => $this->translator->translate('Scripto medias'),
        ];
    }

    public function getIndexerClass()
    {
        return 'ScriptoSearch\Indexer';
    }

    public function getQuerierClass()
    {
        return 'ScriptoSearch\Querier';
    }

    public function getAvailableFacetFields(SearchIndexRepresentation $index)
    {
        $settings = $index->settings();
        $solrNodeId = $settings['adapter']['solr_node_id'];
        $response = $this->api->search('solr_search_fields', [
            'solr_node_id' => $solrNodeId,
            'facetable' => true,
        ]);
        $searchFields = $response->getContent();
        $fields = [];
        foreach ($searchFields as $searchField) {
            $name = $searchField->name();
            $fields[$name] = [
                'name' => $name,
                'label' => $searchField->label(),
            ];
        }

        return $fields;
    }

    public function getAvailableSortFields(SearchIndexRepresentation $index)
    {
        $settings = $index->settings();
        $solrNodeId = $settings['adapter']['solr_node_id'];
        $response = $this->api->search('solr_search_fields', [
            'solr_node_id' => $solrNodeId,
            'sortable' => true,
        ]);
        $searchFields = $response->getContent();

        $fields = [
            'score desc' => [
                'name' => 'score desc',
                'label' => $this->translator->translate('Relevance'),
            ],
        ];

        $directionLabel = [
            'asc' => $this->translator->translate('Asc'),
            'desc' => $this->translator->translate('Desc'),
        ];

        foreach ($searchFields as $searchField) {
            foreach (['asc', 'desc'] as $direction) {
                $name = sprintf('%s %s', $searchField->name(), $direction);
                $label = sprintf('%s %s', $searchField->label(), $directionLabel[$direction]);

                $fields[$name] = [
                    'name' => $name,
                    'label' => $label,
                ];
            }
        }

        return $fields;
    }

    public function getAvailableSearchFields(SearchIndexRepresentation $index)
    {
        $settings = $index->settings();
        $solrNodeId = $settings['adapter']['solr_node_id'];
        $response = $this->api->search('solr_search_fields', [
            'solr_node_id' => $solrNodeId,
            'searchable' => true,
        ]);
        $searchFields = $response->getContent();
        $fields = [];
        foreach ($searchFields as $searchField) {
            $name = $searchField->name();

            $validOperators = [];
            if (!empty($searchField->textFields())) {
                $validOperators[] = self::OPERATOR_CONTAINS_ANY_WORD;
                $validOperators[] = self::OPERATOR_CONTAINS_ALL_WORDS;
                $validOperators[] = self::OPERATOR_CONTAINS_EXPR;
            }
            if (!empty($searchField->stringFields())) {
                $validOperators[] = self::OPERATOR_MATCHES_PATTERN;
            }

            $fields[$name] = [
                'name' => $name,
                'label' => $searchField->label(),
                'valid_operators' => $validOperators,
            ];
        }

        return $fields;
    }

    public function getAvailableOperators(SearchIndexRepresentation $index)
    {
        $operators = [
            self::OPERATOR_CONTAINS_ANY_WORD => [
                'name' => self::OPERATOR_CONTAINS_ANY_WORD,
                'display_name' => $this->translator->translate('contains any word'),
            ],
            self::OPERATOR_CONTAINS_ALL_WORDS => [
                'name' => self::OPERATOR_CONTAINS_ALL_WORDS,
                'display_name' => $this->translator->translate('contains all words'),
            ],
            self::OPERATOR_CONTAINS_EXPR => [
                'name' => self::OPERATOR_CONTAINS_EXPR,
                'display_name' => $this->translator->translate('contains expression'),
            ],
            self::OPERATOR_MATCHES_PATTERN => [
                'name' => self::OPERATOR_MATCHES_PATTERN,
                'display_name' => $this->translator->translate('matches pattern'),
            ],
        ];

        return $operators;
    }
}
