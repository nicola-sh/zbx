<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\ACharts\Actions;

use API;
use CController;
use CControllerResponseData;
use Modules\ACharts\Includes\MetricMatcher;
use Modules\ACharts\Includes\RequestRateLimiter;

class ItemLookup extends CController
{
    private const CANDIDATE_LIMIT = 8;

    protected function init(): void
    {
        $this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
        $this->disableCsrfValidation();
    }

    protected function checkPermissions(): bool
    {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function checkInput(): bool
    {
        $fields = [
            'hostid' => 'required|db hosts.hostid',
            'search' => 'string',
            'mode' => 'in search,browse',
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(
                (new CControllerResponseData(['main_block' => json_encode([
                    'error' => [
                        'messages' => array_column(get_and_clear_messages(), 'message'),
                    ],
                ], JSON_THROW_ON_ERROR)]))->disableView()
            );
        }

        return $ret;
    }

    protected function doAction(): void
    {
        if (!RequestRateLimiter::check('lookup')) {
            $this->setResponse(
                (new CControllerResponseData(['main_block' => json_encode([
                    'error' => _('Too many requests. Please wait.'),
                ], JSON_THROW_ON_ERROR)]))->disableView()
            );

            return;
        }

        $hostid = (string) $this->getInput('hostid');
        $search = trim((string) $this->getInput('search', ''));
        $mode = (string) $this->getInput('mode', 'search');

        if ($mode === 'browse') {
            $preview = $this->browseItems($hostid, $search);
        }
        else {
            $matcher = new MetricMatcher();
            $collection = $matcher->collect([$hostid], $search !== '' ? [$search] : []);
            $preview = $matcher->preview($collection['metrics'], $search, self::CANDIDATE_LIMIT);
            $preview['mode'] = 'search';
        }

        $this->setResponse(
            (new CControllerResponseData(['main_block' => json_encode($preview, JSON_THROW_ON_ERROR)]))->disableView()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function browseItems(string $hostid, string $search): array
    {
        $limit = 50;
        $params = [
            'output' => ['itemid', 'name', 'value_type', 'units'],
            'hostids' => [$hostid],
            'sortfield' => 'name',
            'sortorder' => 'ASC',
            'limit' => $limit,
            'filter' => [
                'value_type' => [0, 3],
            ],
        ];

        if ($search !== '') {
            $params['search'] = ['name' => $search];
            $params['searchByAny'] = true;
        }

        $items = API::Item()->get($params);
        $candidates = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $candidates[] = [
                'itemid' => (string) ($item['itemid'] ?? ''),
                'name' => $name,
                'units' => trim((string) ($item['units'] ?? '')),
            ];
        }

        return [
            'status' => $candidates !== [] ? 'ambiguous' : 'none',
            'match' => null,
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'has_more_candidates' => count($candidates) >= $limit,
            'mode' => 'browse',
        ];
    }
}
