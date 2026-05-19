<?php

/*
 * MIT License
 * Copyright (c) 2026 nicola
 */

namespace Modules\MainCharts;

use Modules\MainCharts\Includes\I18n;
use Zabbix\Core\CWidget;

class Widget extends CWidget
{
    public function init(): void
    {
        I18n::boot();
    }
}
