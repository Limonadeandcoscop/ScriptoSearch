<?php
namespace ScriptoSearch\ValueExtractor;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Solr\ValueExtractor\AbstractValueExtractor;
use Scripto\Entity\ScriptoMedia;

class ScriptoMediaValueExtractor extends AbstractValueExtractor
{
    protected $api;

    public function setApiManager(ApiManager $api)
    {
        $this->api = $api;
    }

    public function getLabel()
    {
        return 'ScriptoMedia';
    }

    public function getAvailableFields()
    {
        $fields = [
            'transcription' => [
                'label' => 'Scripto media transcription',
            ],
            'media' => [
                'label' => 'Scripto media title',
            ],
            'item' => [
                'label' => 'Scripto item title',
            ],
            'project' => [
                'label' => 'Scripto project title',
            ],
            'status' => [
                'label' => 'Media status',
            ],
        ];
        return $fields;
    }


    /**
     * Used for single (live) indexation
     */
    public function extractScriptoMediaValue(ScriptoMedia $sMediaEntity, $field)
    {
        $params = ['field' => $field, 'value' => null];

        $params = $this->triggerEvent('solr.value_extractor.extract_value', $item, $params);
        if (isset($params['value'])) {
            return $params['value'];
        }

        if ($field === 'transcription') {
            $v = $sMediaEntity->getWikitextData('wikitext');
            preg_match_all('/\[([^]]+)\]/', $v, $matches);

            foreach($matches[0] as $match) {
                $firstchar = $match[0];
                $lastchars = substr($match, -2);
                if ($firstchar == '[' && $lastchars == '?]') {
                    $new_match = str_replace('[', '__TOTRANSCRIBE', $match);
                    $new_match = str_replace('?]', 'TOTRANSCRIBE__', $new_match);
                    $v = str_replace($match, $new_match, $v);
                }
            }
            return $v;
        }

        $sMedia = $this->api->read('scripto_media', $sMediaEntity->getId())->getContent();

        if ($field === 'media') {
            $v = $sMedia->media()->displayTitle();
            return $v;
        }

        if ($field === 'item') {
            $v = $sMedia->scriptoItem()->item()->title();
            return $v;
        }

        if ($field === 'project') {
            $v = $sMedia->scriptoItem()->scriptoProject()->title();
            return $v;
        }

        if ($field === 'status') {
            $v = $sMedia->status();
            return $v;
        }
    }

    /**
     * Used for background (bulk) indexation
     */
    public function extractValue(AbstractResourceRepresentation $sMedia, $field)
    {
        $params = ['field' => $field, 'value' => null];

        $params = $this->triggerEvent('solr.value_extractor.extract_value', $sMedia, $params);
        if (isset($params['value'])) {
            return $params['value'];
        }

        if ($field === 'transcription') {
            $v = strip_tags($sMedia->pageHtml(0));

            preg_match_all('/\[([^]]+)\]/', $v, $matches);

            foreach($matches[0] as $match) {
                $firstchar = $match[0];
                $lastchars = substr($match, -2);
                if ($firstchar == '[' && $lastchars == '?]') {
                    $new_match = str_replace('[', '__TOTRANSCRIBE', $match);
                    $new_match = str_replace('?]', 'TOTRANSCRIBE__', $new_match);
                    $v = str_replace($match, $new_match, $v);
                }
            }
            return $v;
        }

        if ($field === 'media') {
            $v = $sMedia->media()->displayTitle();
            return $v;
        }

        if ($field === 'item') {
            $v = $sMedia->scriptoItem()->item()->title();
            return $v;
        }

        if ($field === 'project') {
            $v = $sMedia->scriptoItem()->scriptoProject()->title();
            return $v;
        }

        if ($field === 'status') {
            $v = $sMedia->status();
            return $v;
        }
    }

}
