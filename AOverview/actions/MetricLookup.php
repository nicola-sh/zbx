<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\AOverview\Actions;

use CController;
use CControllerResponseData;
use Modules\AOverview\Includes\MetricMatcher;
use Modules\AOverview\Includes\RequestRateLimiter;
use Modules\AOverview\Includes\WildcardMetricResolver;
use Modules\AOverview\Includes\WidgetForm;

class MetricLookup extends CController
{
    private const CANDIDATE_LIMIT = 5;
    private const ROW_LIMIT = 6;

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
            'mode' => 'in single,wildcard',
            'metric_type' => 'in disk,partition,interface',
            'exclude' => 'string',
            'interfaces_high' => 'string',
            'interfaces_unit' => 'in 0,1,2',
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
                (new CControllerResponseData([
                    'main_block' => json_encode(['error' => _('Too many requests. Please wait.')], JSON_THROW_ON_ERROR),
                ]))->disableView()
            );

            return;
        }

        if ($this->getInput('mode', 'single') === 'wildcard') {
            $this->setResponse(
                (new CControllerResponseData(['main_block' => json_encode(
                    $this->previewWildcardMatches(),
                    JSON_THROW_ON_ERROR
                )]))->disableView()
            );
            return;
        }

        $matcher = new MetricMatcher();
        $collection = $matcher->collect([$this->getInput('hostid')], [$this->getInput('search', '')]);
        $preview = $matcher->preview($collection['metrics'], $this->getInput('search', ''), self::CANDIDATE_LIMIT);
        $preview['mode'] = 'single';

        $this->setResponse(
            (new CControllerResponseData(['main_block' => json_encode($preview, JSON_THROW_ON_ERROR)]))->disableView()
        );
    }

    private function previewWildcardMatches(): array
    {
        $resolver = new WildcardMetricResolver();
        $pattern = $this->getInput('search', '');
        $exclude = $this->getInput('exclude', '');
        $metric_type = $this->getInput('metric_type', '');
        $preview = match ($metric_type) {
            'interface' => $resolver->previewInterfaceRows(
                $this->collectWildcardPreviewMetrics($resolver, $pattern, $metric_type),
                $pattern,
                $exclude,
                $this->getInterfaceCapacityFromInput(),
                self::ROW_LIMIT
            ),
            default => $resolver->previewSingleWildcardRows(
                $this->collectWildcardPreviewMetrics($resolver, $pattern, $metric_type),
                $pattern,
                $exclude,
                self::ROW_LIMIT
            ),
        };

        return [
            'mode' => 'wildcard',
            'metric_type' => $metric_type,
        ] + $preview;
    }

    private function collectWildcardPreviewMetrics(
        WildcardMetricResolver $resolver,
        string $pattern,
        string $metric_type
    ): array {
        $inspection = $metric_type === 'interface'
            ? $resolver->inspectInterfacePattern($pattern)
            : $resolver->inspectSingleWildcardPattern($pattern);

        if ($inspection['status'] !== WildcardMetricResolver::STATUS_READY) {
            return [];
        }

        $matcher = new MetricMatcher();
        $collection = $matcher->collect([$this->getInput('hostid')], $inspection['search_terms']);

        return $collection['metrics'];
    }

    private function getInterfaceCapacityFromInput(): int
    {
        $interfaces_high = (int) $this->getInput('interfaces_high', '0');
        $interfaces_unit = (int) $this->getInput('interfaces_unit', (string) WidgetForm::INTERFACES_UNIT_GBPS);

        $factor = match ($interfaces_unit) {
            WidgetForm::INTERFACES_UNIT_GBPS => 1_000_000_000,
            WidgetForm::INTERFACES_UNIT_MBPS => 1_000_000,
            default                          => 1_000,
        };

        return $interfaces_high > 0 ? $interfaces_high * $factor : 0;
    }
}
