<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts\Actions;

use CController;
use CControllerResponseData;
use Modules\MainCharts\Includes\MetricMatcher;

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
        $matcher = new MetricMatcher();
        $collection = $matcher->collect([$this->getInput('hostid')], [$this->getInput('search', '')]);
        $preview = $matcher->preview($collection['metrics'], $this->getInput('search', ''), self::CANDIDATE_LIMIT);
        $preview['mode'] = 'single';

        $this->setResponse(
            (new CControllerResponseData(['main_block' => json_encode($preview, JSON_THROW_ON_ERROR)]))->disableView()
        );
    }
}
