# Анализ недостатков виджетов AOverview и ACharts

Документ собран по результатам разбора кода модулей **AOverview** и **ACharts** (PHP, JS, views, CSS, README, TODO.md), сессий доработки и тестирования на Zabbix 7.4.

**Целевая платформа:** Zabbix 7.4 (с fallback для 7.x).

---

## Переименование модулей

| Было | Стало |
|------|-------|
| `main_charts/` | **`ACharts/`** |
| `main_overview/` | **`AOverview/`** |

- Manifest id: `acharts`, `aoverview`; отображаемые имена: **ACharts**, **AOverview**
- PHP: `Modules\ACharts`, `Modules\AOverview`
- JS: `CWidgetACharts`, `CWidgetAOverview`
- Actions: `widget.acharts.*`, `widget.aoverview.*`
- CSS/DOM: `a-charts-*`, `a-overview-*`, `dashboard-widget-acharts`, `dashboard-widget-aoverview`
- Версии: ACharts **0.3.x**, AOverview **0.6.x**
- Виджеты `main_*` на дашборде **не мигрируются автоматически** — нужно добавить ACharts/AOverview заново (`README.md`)

---

## Уже реализованные улучшения

### ACharts

- Визуальный редактор серий (`assets/js/widget.edit.js`, `views/widget.edit.php`) → JSON `chart_series`
- `ItemLookup` (`actions/ItemLookup.php`, `widget.acharts.lookup`)
- `itemid` в `ChartSeriesHelper`; единицы в tooltip
- Нормализация периода при save (`normalizePeriodForStorage`, `getFieldValue()` override в `validate()`)

### AOverview

- Состояния ячеек: `missing` / `ambiguous` / `empty` + `state_reason` на дашборде
- Сортировка multi-host по светофору (red → yellow → green)
- Строки метрик с кнопкой **Тест** (`makeMetricAssistantRow` в `widget.edit.js`)
- Компактный CSS; пороги в `ho-thresholds-cascade`

---

## Исправленные ошибки (сессия)

| Ошибка | Решение |
|--------|---------|
| `setFieldValue()` не существует в Zabbix 7.4 | `ACharts/includes/WidgetForm.php`: override `getFieldValue()` + `$field_value_overrides` в `validate()` |
| Period: «Invalid parameter "3 hours"» | UI слал подписи вместо кодов (`3h`); `setValues()` для radio + `normalizePeriodForStorage()` |
| Пустой редактор Data series | Ранний `boot()` до DOM; `scheduleBoot()` + `window.scheduleAChartsSeriesEditorBoot`, re-init при overlay |
| AOverview: сложный выбор item | Inline rows + **Тест**; `getItemNameView(..., $with_lookup = true)` |
| Виджет «Zabbix: host charts» в UI | Старый модуль `main_charts` — нужен деплой **ACharts** |

---


## Критичные / высокий приоритет

| # | Виджет | Недостаток |
|---|--------|------------|
| 1 | **AOverview** | **Баг конфигурации sparkline:** PHP пишет `data-a-overview-config` (`views/components/layout.container.php`), JS читает `dataset.mainOverviewConfig` / `hostOverviewConfig` (`assets/js/class.widget.js`). Пер-хостовые настройки (invert swap, потолок load/IF) в детализации multi-host могут не применяться к sparkline. |
| 2 | **ACharts** | **`ChartHistory` не ограничивает `hostid` серии** списком хостов виджета — только проверка `db hosts.hostid`. Теоретически можно запросить историю любого доступного хоста через POST (`actions/ChartHistory.php`, `resolveSeriesHostId`). |
| 3 | **AOverview** | **Нет явного состояния «хосты не выбраны»** (в ACharts есть `empty: true` + сообщение). При пустом `hostid` — пустая карточка без понятной подсказки (`actions/WidgetView.php`). |
| 4 | **Оба** | **Нет CI/тестов** (Phase 4 в `TODO.md` не сделана): регрессии ловятся только вручную. |
| 5 | **ACharts** | **Период не привязан к времени дашборда** — только фиксированные пресеты (`1h`…`30d`, `includes/WidgetForm.php`), в отличие от штатных графиков Zabbix. |
| 6 | **Оба** | **Миграция с `main_overview` / `main_charts` без автоконвертации** — виджеты на дашборде нужно добавлять заново (`README.md`). |

---

## Редактор и UX

| # | Виджет | Недостаток |
|---|--------|------------|
| 7 | **AOverview** | **Смешение RU/EN** в форме: «Пороги и цвета», «Обновить» vs английские подсказки и бейджи; в JS захардкожены «Тест», «Шаблон с *» (`views/widget.edit.php`, `assets/js/widget.edit.js`). |
| 8 | **AOverview** | **Сложная модель настройки:** глобальные поля скрыты (`a-overview-hidden-metrics`), реальная работа — в JS-аккордеоне + JSON `host_profiles`. Высокий порог входа. |
| 9 | **AOverview** | **Два источника правды:** аккордеон синхронизирует `host_profiles`, но есть ручное поле JSON — легко перезаписать при смене списка хостов. |
| 10 | **AOverview** | **Бейджи per-host** — только textarea JSON, без визуального редактора (в отличие от глобальных бейджей с drag-and-drop в `initBadgesTable`). |
| 11 | **AOverview** | Кнопка **«Обновить»** для пересборки панелей хостов (`js-ho-refresh-host-panels`) — лишний шаг, хотя есть `MutationObserver`. |
| 12 | **AOverview** | Пороги для load/IF в процентах полосы, но **не объяснено**, что это не «сырой» load/bps (`load_high`, `interfaces_high` отдельно). |
| 13 | **ACharts** | Цвет серии — **ручной hex**, без color picker (в AOverview picker есть). |
| 14 | **ACharts** | Серии: визуальный редактор + **скрытый JSON** — два пути, риск рассинхрона; при sync в DOM поле `host` обнуляется (`host: ''` в `widget.edit.js` → `syncFromDom`). |
| 15 | **ACharts** | Максимум **8 серий** (`ChartSeriesHelper::MAX_SERIES`) без явного предупреждения при обрезке в `normalizeList`. |
| 16 | **AOverview** | Host-profile **«Extra JSON»** (`buildAdvancedExtras`) — невалидный JSON при сохранении молча отбрасывается. |
| 17 | **ACharts** | Строка `advanced_json` в `widget.edit.js` не используется; подпись только в PHP `<summary>`. |
| 18 | **Оба** | `override_hostid` скрыт при редактировании виджета из шаблона (`templateid === null` в `views/widget.edit.php`). |

---

## Модель данных и разрешение item'ов

| # | Виджет | Недостаток |
|---|--------|------------|
| 19 | **Оба** | Поиск item только по **имени/подстроке** (`MetricMatcher` в обоих модулях) — нет key шаблона, тегов, discovery. |
| 20 | **Оба** | **Неоднозначные имена** → «No data» / missing без списка кандидатов на дашборде (диагностика в основном в редакторе через lookup). |
| 21 | **AOverview** | **Per-host `badges` в overrides** не проходят `CWidgetFieldBadgesList::validate()` при сохранении — валидируется только глобальное поле `badges`. |
| 22 | **ACharts** | Битый JSON в `chart_series` при чтении **тихо подменяется defaults** (`ChartSeriesHelper::parse`), а не ошибка до сохранения. |
| 23 | **AOverview** | Интерфейсы: **отдельные строки RX/TX** (`WildcardMetricResolver::buildInterfaceOutputRows`), а не суммарный трафик — может не совпадать с ожиданиями NOC. |
| 24 | **ACharts** | При нескольких хостах с одинаковым видимым именем привязка по `host` в серии может **молча не сработать** (`WidgetView.php` → `resolveSeriesHostId` → `null`). |
| 25 | **ACharts** | `WidgetView` при `itemid` делает отдельные API-вызовы; смешанные серии — избыточные запросы. |
| 26 | **AOverview** | Single-host: порядок хоста = первый в multiselect (`getPrimaryHostId`), не документирован. |

---

## Надёжность и краевые случаи

| # | Виджет | Недостаток |
|---|--------|------------|
| 27 | **AOverview** | Порядок red > yellow проверяется **только в JS** редактора (`updateThresholdScale` для global); в `WidgetForm::validate()` серверной проверки нет. |
| 28 | **AOverview** | Пустая ячейка метрики считается **красным** в светофоре (`metricCellWorstLevel`, `state === 'empty'`) — агрессивная индикация при временном отсутствии данных. |
| 29 | **ACharts** | Все серии missing: PHP показывает warnings (`views/widget.view.php`), JS — «No data for the selected series»; дублирование и путаница. |
| 30 | **Оба** | `MetricMatcher`: единственное частичное совпадение (`STATUS_UNIQUE_PARTIAL`) — хрупко при появлении новых item'ов. |
| 31 | **AOverview** | Multi-host: мутация `$this->fields_values` в цикле `renderMultiHostDashboard` — хрупко при дальнейших правках. |
| 32 | **Оба** | Строковые/log item'ы в history/sparkline — **числовое приведение** без явной поддержки нечисловых типов. |
| 33 | **ACharts** | `ChartSeriesHelper::parse` обрезает серии до MAX без явной ошибки в UI. |
| 34 | **AOverview** | Невалидный JSON в per-host badges → пустой массив в runtime (`CWidgetFieldBadgesList::decodeStored`). |

---

## Производительность и масштаб

| # | Виджет | Недостаток |
|---|--------|------------|
| 35 | **AOverview** | Multi-host: **O(хосты × API)** — на каждый хост отдельно metrics, problems, host name (`renderMultiHostDashboard` в `WidgetView.php`). |
| 36 | **AOverview** | Все панели multi-host **рендерятся в DOM сразу** (скрытые, `layout.multi_host.php`) — тяжело при 20+ хостах. |
| 37 | **ACharts** | История по сериям **последовательно** в `ChartHistory::build` — до 8 × (history + trends) на refresh. |
| 38 | **Оба** | Downsampling — **прореживание по шагу** (`HistoryLoader`, `SparklineHistory`), пики могут теряться; лимиты 4800/2048 без предупреждения. |
| 39 | **AOverview** | Problems badge — до **1000 событий** на хост каждый refresh (`fetchProblems`). |
| 40 | **AOverview** | Wildcard: `MetricMatcher::collect` с `searchByAny` — широкие шаблоны тянут много item'ов в память. |
| 41 | **ACharts** | Тяжёлый bundle **Chart.js 4.4.9 + date-fns adapter** (`assets/js/`) на каждый экземпляр виджета. |

---

## Безопасность

| # | Виджет | Недостаток |
|---|--------|------------|
| 42 | **Оба** | JSON actions (`widget.aoverview.lookup`, `widget.aoverview.sparkline`, `widget.acharts.lookup`, `widget.acharts.history`) с **`disableCsrfValidation()`** — опора на cookie/session. |
| 43 | **Оба** | Права только **`getUserType() >= USER_TYPE_ZABBIX_USER`**, без доп. проверок на уровне виджета. |
| 44 | **ACharts** | Клиент может передать произвольный `hostid` в series payload — не сверяется с конфигом виджета (см. #2). |
| 45 | **Оба** | Нет **rate limit** на lookup/history — частый refresh дашборда нагружает API. |
| 46 | **AOverview** | URL в link-бейджах валидируются глобально (`CWidgetFieldBadgesList::sanitizeLinkUrl`); в per-host JSON — слабее. |

---

## i18n и доступность (a11y)

| # | Виджет | Недостаток |
|---|--------|------------|
| 47 | **AOverview** | Мало `_m()` / gettext — runtime-тексты в основном **захардкожены** (EN/RU mix в `WidgetView.php`, views). |
| 48 | **AOverview** | Sparkline dialog: `role="dialog"` (`layout.sparkline.php`), но нет **focus trap**, возврата фокуса; только Escape в `class.sparkline.js`. |
| 49 | **AOverview** | Multi-host summary: `aria-label` светофора — «red/yellow/green», а не человекочитаемый статус (`layout.multi_host.php`). |
| 50 | **ACharts** | График `role="img"` с общим `aria-label` (`widget.view.php`) — нет текстовой сводки для screen readers; tooltips только мышью. |
| 51 | **AOverview** | Threshold scale bar: `role="img"` + `aria-hidden` на визуальной шкале; значения только в скрытых/рядом inputs (`widget.edit.js`). |
| 52 | **ACharts** | Строки редактора в `UI` object (`widget.edit.js`) — только английский, без PHP `_m()`. |
| 53 | **AOverview** | Стрелки тренда на метриках **`aria-hidden`** (`class.widget.js`) — изменение не озвучивается. |
| 54 | **Оба** | Sparkline period buttons: `aria-current` есть (`class.sparkline.js`); закрытие — «Back» + иконка. |

---

## Технический долг / сопровождение

| # | Виджет | Недостаток |
|---|--------|------------|
| 55 | **Оба** | **Два копии `MetricMatcher.php`** (`ACharts/includes/`, `AOverview/includes/`) — риск расхождения (уже различаются, напр. `units`, `preview`). |
| 56 | **Оба** | **Дублирование логики history** (`ACharts/includes/HistoryLoader.php` vs `AOverview/actions/SparklineHistory.php`). |
| 57 | **AOverview** | **`assets/js/widget.edit.js` ~2600 строк** — монолит (пороги, бейджи, аккордеон, lookup). |
| 58 | **Оба** | `Widget.php` — **пустые заглушки**; вся логика в actions/includes. |
| 59 | **AOverview** | Смешение OOP и **процедурных** `views/components/layout*.php` + `require_once`. |
| 60 | **Оба** | Vendored Chart.js без документированного процесса обновления. |
| 61 | **AOverview** | `console.log` при ошибках lookup в редакторе (`widget.edit.js`) вместо управляемого логирования. |
| 62 | **Корень репо** | README/TODO только в корне — не рядом с модулями; `manifest.json` описания краткие. |

---

## Не хватает по сравнению с типичными виджетами Zabbix

| # | Виджет | Недостаток |
|---|--------|------------|
| 63 | **ACharts** | Нет **custom time period** / sync с временем дашборда. |
| 64 | **ACharts** | Нет **второй оси Y**; units только в tooltip; stacked chart с разными единицами вводит в заблуждение. |
| 65 | **ACharts** | Нет **item picker** (Zabbix multiselect) — только имя + «Find item» / «Тест». |
| 66 | **ACharts** | Нет zoom/pan, маркеров проблем, export PNG/CSV, legend click-to-toggle, перцентилей, опорных линий. |
| 67 | **AOverview** | Нет **host group** / **tag-based** выбора хостов. |
| 68 | **AOverview** | Нет **sort/limit/search** в multi-host списке на виджете (сортировка по светофору только server-side). |
| 69 | **AOverview** | Период sparkline **не настраивается** в виджете — default `1h` в `class.sparkline.js`. |
| 70 | **Оба** | Нет интеграции с **dashboard overrides / макросами**. |
| 71 | **ACharts** | Нет pie/donut/scatter. |
| 72 | **AOverview** | Нет map/topology/SLA-виджет parity. |
| 73 | **Оба** | Нет отдельной тонкой настройки темы графика под dark/light beyond CSS variables. |

---

## Миграция и совместимость

| # | Виджет | Недостаток |
|---|--------|------------|
| 74 | **Оба** | Переименование `main_overview` → `AOverview`, `main_charts` → `ACharts` — **ручная переустановка** виджетов. |
| 75 | **Оба** | Целевой **7.4**; shims (`method_exists` для `setValues`, `ZBX_STYLE_COLOR_PICKER`, hidden-header mode) — матрица 7.0–7.2 не проверена автоматически. |
| 76 | **AOverview** | Legacy-ключи порогов (`th_m*`, `th_num_m*`) в `WidgetForm::normalizeValues` — нет миграционного скрипта. |
| 77 | **ACharts** | Legacy индексы периода `0`–`6` → коды `1h`… в `normalizePeriodForStorage` — неочевидно при отладке сохранённых виджетов. |
| 78 | **AOverview** | `method_exists` для badge helpers в `widget.edit.php` — различия API между патчами Zabbix. |
| 79 | **ACharts** | Chart.js 4.x может конфликтовать, если на дашборде глобально подключена другая версия Chart (не проверено). |

---

## Что уже сделано хорошо

- Lifecycle Zabbix 7.4: `onActivate` / `onDeactivate` / `onClearContents` / `onDestroy` в JS.
- **ACharts:** защита от гонок async (`_fetchGeneration`), `missing_reason` для серий, нормализация period при save.
- **AOverview:** статусы ячеек `missing` / `ambiguous` / `empty` с `state_reason`, сортировка multi-host по светофору.
- Per-host overrides, wildcard для дисков/разделов/интерфейсов.
- Редактор: «Тест» / lookup для item'ов; визуальный редактор серий ACharts.
- Fallback для radio `setValues()` и совместимости форм 7.x.

---

## Статус roadmap (`TODO.md`)

| Phase | Статус |
|-------|--------|
| Phase 1 — Stability baseline | ✅ Done |
| Phase 2 — AOverview logic/UX | ✅ Done |
| Phase 3 — ACharts multi-host/multi-item | ✅ Done |
| Phase 4 — CI, lint, runbook, troubleshooting | ❌ Not done |

### Phase 4 (запланировано, не реализовано)

- GitHub Actions: PHP/JS syntax lint
- eslint / phpstan (optional)
- Smoke-test runbook для Zabbix 7.4
- Troubleshooting guide (cache, logs, misconfigurations)

---

## Рекомендуемый порядок исправлений

1. **#1** — Исправить dataset sparkline (`data-a-overview-config` ↔ JS `dataset.aOverviewConfig`).
2. **#2** — Ограничить `hostid` в `ChartHistory` списком хостов виджета.
3. **#3** — Пустое состояние «выберите хост» в AOverview.
4. **#4, #60–62** — CI + smoke runbook + troubleshooting.
5. **#7–#10** — Упростить и локализовать редактор AOverview.
6. **#35–#36** — Производительность multi-host (ленивая загрузка панелей / batch API).
7. **#63** — Привязка периода ACharts к времени дашборда.

---

## Ссылки на ключевые файлы

| Область | AOverview | ACharts |
|---------|-----------|---------|
| Manifest | `AOverview/manifest.json` | `ACharts/manifest.json` |
| Form | `AOverview/includes/WidgetForm.php` | `ACharts/includes/WidgetForm.php` |
| View action | `AOverview/actions/WidgetView.php` | `ACharts/actions/WidgetView.php` |
| JSON API | `MetricLookup.php`, `SparklineHistory.php` | `ItemLookup.php`, `ChartHistory.php` |
| Runtime JS | `assets/js/class.widget.js`, `class.sparkline.js` | `assets/js/class.widget.js` |
| Editor JS | `assets/js/widget.edit.js` | `assets/js/widget.edit.js` |
| Views | `views/components/layout*.php` | `views/widget.view.php` |

---

*Документ сформирован для планирования доработок. Версии модулей на момент анализа: AOverview 0.6.1, ACharts 0.3.2.*
