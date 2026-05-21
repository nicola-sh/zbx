# Общий модуль ZbxCommon

Библиотека PHP для виджетов **AOverview** и **ACharts**. В проекте **нет** React, Vue и JSON Forms — только нативный JS виджетов Zabbix и Chart.js в ACharts.

## Зачем отдельный модуль

Раньше `MetricMatcher.php` и `RequestRateLimiter.php` были **скопированы дважды** (в AOverview и ACharts). Любое исправление нужно было дублировать.  
**ZbxCommon** — одна копия, подключается обоими виджетами через autoload Zabbix.

## Где это лежит в Zabbix

Официально: каждый модуль — **отдельная папка** в каталоге frontend modules, обычно:

```text
/usr/share/zabbix/ui/modules/          # пакеты (пример)
/var/www/html/zabbix/ui/modules/     # или ваш путь к UI
```

Структура **этого репозитория** при установке:

```text
ui/modules/
├── ZbxCommon/          ← библиотека (type: module, без виджета)
│   ├── manifest.json
│   ├── Module.php
│   └── includes/
│       ├── MetricMatcher.php
│       └── RequestRateLimiter.php
├── AOverview/          ← виджет
│   ├── manifest.json   (type: widget)
│   └── ...
└── ACharts/            ← виджет
    ├── manifest.json   (type: widget)
    └── ...
```

Документация Zabbix:

- [Modules (обзор)](https://www.zabbix.com/documentation/7.4/en/devel/modules)
- [Структура файлов модуля](https://www.zabbix.com/documentation/7.4/en/devel/modules/file_structure)
- [manifest.json](https://www.zabbix.com/documentation/7.4/en/devel/modules/file_structure/manifest)
- [Виджеты](https://www.zabbix.com/documentation/7.4/en/devel/modules/widgets)
- [Туториал: виджет](https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget)

## Установка

1. Скопировать **три** папки в `ui/modules/`:
   - `ZbxCommon`
   - `AOverview`
   - `ACharts`
2. **Administration → General → Modules**
3. Включить **Zbx Common** (первым)
4. Включить **AOverview** и **ACharts**

Если ZbxCommon выключен, виджеты получат ошибку autoload (`Class Modules\ZbxCommon\Includes\MetricMatcher not found`).

## Как Zabbix подключает классы

В `manifest.json` каждого модуля указан `namespace`. Класс в файле:

`ZbxCommon/includes/MetricMatcher.php`

```php
namespace Modules\ZbxCommon\Includes;

class MetricMatcher { ... }
```

Виджет использует:

```php
use Modules\ZbxCommon\Includes\MetricMatcher;

$matcher = new MetricMatcher();
```

Zabbix при загрузке **включённых** модулей регистрирует autoload по namespace → путь `modules/{id}/...`. Отдельный `composer` в виджетах **не нужен**.

## Что вынесено в ZbxCommon

| Класс | Назначение |
|-------|------------|
| `MetricMatcher` | Поиск item по имени (exact / partial / ambiguous), `collect()`, `preview()` |
| `RequestRateLimiter` | Лимит JSON-запросов (~120/мин на сессию) |

## Что остаётся в каждом виджете

- **AOverview:** `WidgetView`, sparkline, бейджи, `WildcardMetricResolver`, редактор на vanilla JS
- **ACharts:** `ChartHistory`, `SeriesHostResolver`, Chart.js, редактор серий на vanilla JS

## Редакторы (без React/Vue)

Редакторы — **обычный JavaScript** (`widget.edit.js`), поля Zabbix `CWidgetField*`, DOM (кнопки Find / Browse, таблица серий). Это тот же подход, что в [официальном туториале виджета](https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget), без SPA-фреймворков.

## Добавление нового общего кода

1. Положить класс в `ZbxCommon/includes/MyHelper.php`
2. Namespace: `Modules\ZbxCommon\Includes`
3. В виджете: `use Modules\ZbxCommon\Includes\MyHelper;`
4. Обновить версию в `ZbxCommon/manifest.json`
5. На сервере обновить папку модуля и перезагрузить PHP/UI при необходимости

Не дублировать файл во второй виджет — только один источник в ZbxCommon.
