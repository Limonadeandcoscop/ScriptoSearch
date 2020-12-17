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
];
