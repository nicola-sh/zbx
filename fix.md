# Трекер AOverview и ACharts

**Версии:** AOverview **0.7.2**, ACharts **0.4.1**  
**Ветка:** `cursor/fix-all-fix-md-8052`  
**Операции:** [docs/SMOKE_TEST.md](docs/SMOKE_TEST.md), [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)

| Статус | Значение |
|--------|----------|
| ✅ | Сделано |
| 📋 | Частично / осознанный компромисс |
| ⏭ | Вне scope (Zabbix core или отдельная задача) |

---

## Сделано

### Критичное и стабильность

- ✅ Sparkline: JS читает `dataset.aOverviewConfig` (не legacy `mainOverviewConfig`).
- ✅ Пустое состояние AOverview при отсутствии хостов.
- ✅ ACharts: `ChartHistory` ограничивает `hostid` через `SeriesHostResolver`.
- ✅ CI: `.github/workflows/lint.yml` (PHP syntax, JS syntax, sync `MetricMatcher`).
- ✅ Период дашборда ACharts: `chart_use_dashboard_time` + `time_from` / `time_till`.
- ✅ Валидация порогов на сервере; `MAX_SERIES` при сохранении ACharts.
- ✅ Batch имён хостов; rate limit JSON actions (~120/мин); problems limit 200.

### AOverview 0.7.2 — редактор и UX

- ✅ Секция **«Оформление»**: ссылки, форма, подписи, полосы, **пороги и цвета** в одном месте.
- ✅ Подсказки: иконки **?** (`help_ui`, `makeHelpIcon`, `bindEditorHelpIcons`).
- ✅ Упрощение редактора: убран поиск/фильтр списка multi-host на дашборде; убраны отдельные JSON-редакторы «Свои бейджи» и «Доп. overrides» (бейджи — визуальный редактор; per-host настройки — аккордеон → `host_profiles` sync).
- ✅ Компактная таблица порогов; наследование пустых per-host полей из глобальных.
- ✅ i18n: ключевые строки через `_()`; aria-label светофора multi-host.

### ACharts 0.4.1 — редактор и данные

- ✅ Color picker в редакторе серий.
- ✅ Выбор item: **Find**, **Browse items** (`ItemLookup` mode `browse`), **Quick add** (CPU, Memory, Load, Disk).
- ✅ `parseForValidation` / `ChartSeriesHelper`; `syncFromDom` сохраняет hostid/host.
- ✅ Dashboard time, `missing_reason` в ответе history, защита от stale async refresh.

### Документация и миграция

- ✅ README, TODO, smoke/troubleshooting; legacy keys `main_*` в normalize + README migration note.
- ✅ `scripts/check-metric-matcher-sync.sh` в CI.

---

## Открытый backlog

| Тема | Статус | Комментарий |
|------|--------|-------------|
| Полная локализация JS | 📋 | PHP `_()`; строки в `widget.edit.js` частично на EN |
| Native Zabbix item picker | ⏭ | Свой lookup + browse; не виджет Zabbix UI |
| Визуальный редактор per-host badges (отдельно от глобальных) | ⏭ | Глобальные бейджи визуально; per-host — через `badges_placement` + sync JSON |
| Lazy / virtual scroll multi-host | 📋 | Список без client-side search; большие списки — без virtual scroll |
| Общий HistoryLoader PHP | ⏭ | Дублирование между виджетами приемлемо |
| Вторая ось Y, zoom/export графика | ⏭ | Chart.js базовый набор |
| Host groups по тегам, map/pie виджеты | ⏭ | Другой scope |
| Поиск item по key/тегам | ⏭ | Только name / browse list |
| Batch item API (один запрос на N item) | 📋 | До 8 серий ACharts — ок |
| eslint / phpstan | 📋 | Phase 4.2 TODO |
| Focus trap в sparkline overlay | ⏭ | a11y улучшение |
| Автоконвертация `main_*` → `A*` | ⏭ | Нет API Zabbix для смены type id |

---

## Осознанные компромиссы

- **CSRF** отключён на JSON actions (паттерн Zabbix widget); требуется сессия и `USER_TYPE_ZABBIX_USER`.
- **`host_profiles`** — скрытое JSON, пересобирается из аккордеона при save (ручное редактирование возможно в «Host profiles (sync)»).
- **Кнопка «Обновить»** в редакторе — пересборка панелей после смены списка хостов; есть авто-sync + MutationObserver.
- **RX/TX** интерфейсов — отдельные строки (by design).
- **UNIQUE_PARTIAL** в MetricMatcher — осознанный компромисс для wildcard.
- **Chart.js** — vendored bundle; обновление — вручную + TROUBLESHOOTING.

---

## Файлы (ключевые изменения ветки)

| Область | Пути |
|---------|------|
| AOverview runtime | `assets/js/class.widget.js`, `class.sparkline.js` |
| AOverview editor | `views/widget.edit.php`, `assets/js/widget.edit.js` |
| ACharts API | `actions/ChartHistory.php`, `ItemLookup.php`, `includes/SeriesHostResolver.php` |
| ACharts editor | `views/widget.edit.php`, `assets/js/widget.edit.js` |
| CI / docs | `.github/workflows/lint.yml`, `docs/*`, `scripts/check-metric-matcher-sync.sh` |

*Последнее обновление: ревизия документации под 0.7.2 / 0.4.1.*
