<?php
namespace ScriptoSearch;

return  [
  'search_adapters' => [
    'factories' => [
        'scripto_media' => Service\AdapterFactory::class,
    ],
  ],
  'solr_value_extractors' => [
    'factories' => [
      'scripto_media' => Service\ValueExtractor\ScriptoMediaValueExtractorFactory::class,
    ],
  ],
  'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
  'view_helpers' => [
        'factories' => [
            'facetLinkRemove' => Service\ViewHelper\FacetLinkRemoveFactory::class,
            'facetLinkRefine' => Service\ViewHelper\FacetLinkRefineFactory::class,
        ],
    ],
];
