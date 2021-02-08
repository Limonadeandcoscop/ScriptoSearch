<?php

namespace ScriptoSearch\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\Mvc\Application;

class FacetLinkRemove extends AbstractHelper
{
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function __invoke($name, $facet)
    {

        // Handle multiples -facets on same field

        $params = explode('&limit', urldecode($_SERVER['QUERY_STRING']));

        if (count($params)) {
            $selected = [];
            foreach($params as $param) {
                preg_match('/\[(.*)\]\[0\]/', $param, $matches);
                if (count($matches) && isset($matches[1])) {
                    $p = explode('[0]', $param);
                    $arrayFacets[trim($p[0], '[]')] = ltrim($p[1], '=');
                    @array_push($selected, $matches[1]);
                }
            }
        }

        if (isset($arrayFacets[$name]) && @$arrayFacets[$name] != $facet['value']) return;

        $mvcEvent = $this->application->getMvcEvent();
        $routeMatch = $mvcEvent->getRouteMatch();
        $request = $mvcEvent->getRequest();

        $route = $routeMatch->getMatchedRouteName();
        $params = $routeMatch->getParams();
        $query = $request->getQuery()->toArray();

        $active = false;
        if (isset($query['limit'][$name]) && false !== array_search($facet['value'], $query['limit'][$name])) {
            $values = $query['limit'][$name];
            $values = array_filter($values, function ($v) use ($facet) {
                return $v != $facet['value'];
            });
            $query['limit'][$name] = $values;
            $active = true;
        } else {
            $query['limit'][$name][] = $facet['value'];
        }

        unset($query['page']);

        $view = $this->getView();
        $url = $view->url($route, $params, ['query' => $query]);

        return $view->partial('scripto-search/facet-link-remove', [
            'url' => $url,
            'active' => $active,
            'value' => $facet['value'],
        ]);
    }
}
