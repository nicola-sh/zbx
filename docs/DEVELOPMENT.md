# Разработка zbx

## Стек (зафиксировано)

| Слой | Технология |
|------|------------|
| Виджеты Zabbix | PHP 8+, `CWidget`, `CWidgetForm`, `CController` |
| Общая библиотека | Модуль **ZbxCommon** (`Modules\ZbxCommon\Includes\*`) |
| Дашборд (runtime) | Vanilla JavaScript (`class.widget.js`, lifecycle Zabbix) |
| Редакторы | Vanilla JavaScript (`widget.edit.js`), DOM, без сборщика |
| Графики ACharts | **Chart.js** 4.x (vendored в `ACharts/assets/js/`) |

**Не используем:** React, Vue, Angular, JSON Forms, SurveyJS и другие SPA/form-фреймворки в редакторах. Причина: нативный multiselect хостов и поля `CWidgetField*` в Zabbix 7.4 не рассчитаны на встраивание React/Vue без отдельной сборки и конфликтов с UI Zabbix.

## Модули в репозитории

- `ZbxCommon/` — shared PHP (см. [SHARED_MODULE.md](SHARED_MODULE.md))
- `AOverview/` — карточки хостов
- `ACharts/` — графики Chart.js

## CI

- PHP syntax, JS syntax (`.github/workflows/lint.yml`)
- Проверка, что дубликаты `MetricMatcher` в виджетах не вернулись (`scripts/check-zbx-common-usage.sh`)

## Полезные ссылки Zabbix 7.4

- https://www.zabbix.com/documentation/7.4/en/devel/modules
- https://www.zabbix.com/documentation/7.4/en/devel/modules/widgets
- https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget
