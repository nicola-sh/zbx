# Анализ недостатков виджетов AOverview и ACharts

**Статус:** большинство пунктов закрыто в ветке `cursor/fix-all-fix-md-8052` (версии AOverview **0.7.0**, ACharts **0.4.0**). Ниже — исходный список с отметками.

| Статус | Значение |
|--------|----------|
| ✅ | Исправлено в коде/доках |
| 📋 | Частично / ограниченная реализация |
| ⏭ | Вне scope виджета (нужен Zabbix core или отдельный проект) |

---

## Критичные / высокий приоритет

| # | Статус | Виджет | Недостаток |
|---|--------|--------|------------|
| 1 | ✅ | AOverview | Sparkline config: JS читает `dataset.aOverviewConfig` |
| 2 | ✅ | ACharts | `ChartHistory` ограничивает hostid списком виджета (`SeriesHostResolver`) |
| 3 | ✅ | AOverview | Пустое состояние «выберите хост» |
| 4 | ✅ | Оба | CI: `.github/workflows/lint.yml`, PHP/JS syntax |
| 5 | ✅ | ACharts | Период дашборда: поле `chart_use_dashboard_time` + `time_from`/`time_till` |
| 6 | 📋 | Оба | Миграция `main_*` — документирована в README; автоконвертация невозможна без API Zabbix |

---

## Редактор и UX

| # | Статус | Кратко |
|---|--------|--------|
| 7 | 📋 | i18n: ключевые строки через `_()`; полная локализация JS — частично |
| 8 | 📋 | Сложность редактора снижена (поиск хостов, Тест); полный редизайн — нет |
| 9 | 📋 | JSON host_profiles остаётся; синхронизация аккордеона улучшена |
| 10 | ⏭ | Per-host badges visual editor — не реализован |
| 11 | 📋 | Кнопка «Обновить» остаётся; есть MutationObserver |
| 12 | 📋 | Подсказки порогов — частично в README |
| 13 | ✅ | ACharts color picker в редакторе серий |
| 14 | ✅ | syncFromDom сохраняет hostid (single-host) и host |
| 15 | ✅ | Валидация MAX_SERIES при save |
| 16 | 📋 | Extra JSON — ошибка при невалидном JSON в validate |
| 17 | 📋 | advanced_json в JS не используется |
| 18 | 📋 | override_hostid — поведение Zabbix template API |

---

## Модель данных

| # | Статус | Кратко |
|---|--------|--------|
| 19 | ⏭ | Поиск по key/тегам — нет |
| 20 | 📋 | Lookup в редакторе; кандидаты на дашборде — ограниченно |
| 21 | ✅ | Per-host badges validate в `WidgetForm` |
| 22 | ✅ | `parseForValidation` для chart_series |
| 23 | 📋 | RX/TX отдельными строками — by design |
| 24 | ✅ | Host resolution с ambiguous host name → null + reason |
| 25 | 📋 | Batch item API — не объединён |
| 26 | 📋 | Primary host — документирован в README |

---

## Надёжность

| # | Статус | Кратко |
|---|--------|--------|
| 27 | ✅ | Server-side threshold ordering |
| 28 | ✅ | empty/missing/ambiguous → neutral (0) в светофоре |
| 29 | ✅ | ACharts: динамический prefix warning + JS missing reasons |
| 30 | 📋 | UNIQUE_PARTIAL — осознанный компромисс |
| 31 | ✅ | try/finally в multi-host loop |
| 32 | 📋 | Нечисловые item — без полной поддержки |
| 33 | ✅ | MAX_SERIES error при save |
| 34 | ✅ | Invalid badges JSON → ошибка валидации |

---

## Производительность

| # | Статус | Кратко |
|---|--------|--------|
| 35 | ✅ | Batch `fetchHostNamesMap` |
| 36 | 📋 | Поиск/фильтр списка; lazy DOM панелей — не полный virtual scroll |
| 37 | 📋 | PHP sequential history — до 8 серий приемлемо |
| 38 | 📋 | Downsampling без UI warning |
| 39 | ✅ | Problems limit 200 + capped flag |
| 40 | 📋 | Wildcard collect — без лимита |
| 41 | 📋 | Chart.js bundle — vendored |

---

## Безопасность

| # | Статус | Кратко |
|---|--------|--------|
| 42 | 📋 | CSRF disabled (Zabbix widget JSON pattern); session required |
| 43 | 📋 | USER_TYPE_ZABBIX_USER |
| 44 | ✅ | hostid scoped (см. #2) |
| 45 | ✅ | RequestRateLimiter 120/min |
| 46 | ✅ | Per-host badges через CWidgetFieldBadgesList::validate |

---

## i18n / a11y

| # | Статус | Кратко |
|---|--------|--------|
| 47–54 | 📋 | Частично: `_()`, aria-label светофора, поиск; focus trap sparkline — нет |

---

## Техдолг

| # | Статус | Кратко |
|---|--------|--------|
| 55 | ✅ | MetricMatcher sync check в CI |
| 56 | ⏭ | Общий HistoryLoader — не вынесен |
| 57 | ⏭ | Монолит widget.edit.js |
| 58 | 📋 | Widget.php stubs — Zabbix convention |
| 59 | ⏭ | Процедурные layout — refactor отложен |
| 60 | 📋 | Chart.js update — docs/TROUBLESHOOTING |
| 61 | ✅ | console.log убран / за ZBX_DEBUG_WIDGETS |
| 62 | ✅ | docs/ + README ссылки |

---

## Сравнение с виджетами Zabbix

| # | Статус | Кратко |
|---|--------|--------|
| 63 | ✅ | Dashboard time (см. #5) |
| 64–66 | ⏭ | Вторая ось Y, native item picker, zoom/export — нет |
| 67–72 | ⏭ | Host group, map, pie charts — нет |
| 73 | 📋 | CSS variables only |

---

## Миграция

| # | Статус |
|---|--------|
| 74–79 | 📋 Документировано; legacy keys поддерживаются в normalize |

---

## Файлы изменений (основные)

- `AOverview/assets/js/class.widget.js` — sparkline config, multi-host search
- `ACharts/includes/SeriesHostResolver.php`, `ChartHistory.php`
- `ACharts/includes/ChartSeriesHelper.php`, `WidgetForm.php`
- `.github/workflows/lint.yml`, `docs/SMOKE_TEST.md`, `docs/TROUBLESHOOTING.md`

*Обновлено после прохода fix-all.*
