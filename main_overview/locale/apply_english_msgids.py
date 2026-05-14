#!/usr/bin/env python3
"""Replace Russian UI literals with English gettext msgids in widget sources."""

from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# Longest Russian strings first to avoid partial collisions.
RU_EN: list[tuple[str, str]] = [
    (
        "После выбора одного или нескольких узлов ниже появится блок настроек для каждого (метрики, элементы, пороги). Если панелей нет — нажмите «Обновить панели узлов».",
        "After you select one or more hosts, a per-host settings block appears below (metrics, items, thresholds). If no panels appear, click \"Refresh host panels\".",
    ),
    (
        "Оставьте пустым, чтобы использовать общие значки. Вставьте JSON в том же формате, что и глобальный список, чтобы заменить значки только для этого узла.",
        "Leave empty to use global badges. Paste JSON in the same format as the global list to override badges for this host only.",
    ),
    (
        "Укажите точное имя элемента «живости», например «Zabbix agent ping». Частичное имя — только при единственном совпадении; иначе значок не отобразится.",
        "Enter the exact liveliness item name, for example \"Zabbix agent ping\". A partial name is used only when it matches exactly one item; otherwise the badge will not render.",
    ),
    (
        "Укажите точное имя элемента аптайма, например «System uptime». Частичное имя — только при единственном совпадении; иначе значок не покажет аптайм.",
        "Enter the exact uptime item name, for example \"System uptime\". A partial name is used only when it matches exactly one item; otherwise the badge will not show uptime.",
    ),
    (
        "Предпочтительно точное имя элемента. Частичное имя используется только при единственном совпадении; иначе отображается «Нет данных».",
        "Prefer an exact item name. A partial name is used only when it matches exactly one item; otherwise \"No data\" is shown.",
    ),
    (
        "Возраст в секундах с момента последних данных выбранного элемента «живости». Сначала срабатывает предупреждение, затем «устарело».",
        "Seconds since the last data for the selected liveliness item. Warning triggers first, then stale.",
    ),
    (
        "Максимальное значение нагрузки для масштаба полосы и спарклайна. На экране по-прежнему показывается фактическая нагрузка.",
        "Maximum load for scaling the bar and sparkline. The actual load is still shown on screen.",
    ),
    (
        "Ожидаемая максимальная пропускная способность для масштаба полос интерфейсов. Учитывается выбранная единица измерения.",
        "Expected maximum throughput for scaling interface bars. The selected unit is applied.",
    ),
    (
        "Значок %1$s: URL должен начинаться с http://, https:// или быть относительным путём (например zabbix.php?action=...).",
        "Badge %1$s: URL must start with http://, https://, or be a relative path (for example zabbix.php?action=...).",
    ),
    (
        "JSON пересобирается автоматически из списка узлов при сохранении. При необходимости его можно править вручную.",
        "JSON is rebuilt automatically from the host list when you save. You can still edit it manually if needed.",
    ),
    (
        "Точного или единственного частичного совпадения пока нет. Укажите точное имя элемента:",
        "No exact or single partial match yet. Enter an exact item name:",
    ),
    (
        "Шаблон или исключения изменились. Нажмите «Проверить», чтобы обновить предпросмотр.",
        "Template or exclusions changed. Click \"Test\" to refresh the preview.",
    ),
    (
        "Добавьте фиксированный текст вокруг «*», чтобы сузить список совпадений.",
        "Add fixed text around \"*\" to narrow the match list.",
    ),
    (
        "Значок %1$s: для типа «%2$s» текст отображения не может быть пустым.",
        "Badge %1$s: display text cannot be empty for type \"%2$s\".",
    ),
    (
        "Показаны только первые строки. Уточните шаблон, чтобы сузить список.",
        "Only the first rows are shown. Refine the template to narrow the list.",
    ),
    (
        "Не учитывать подтверждённые проблемы в счётчике значка «Проблемы».",
        "Do not count acknowledged problems in the Problems badge tally.",
    ),
    (
        "Для интерфейсов используйте не менее двух символов «*» в шаблоне.",
        "For interfaces use at least two \"*\" characters in the template.",
    ),
    (
        "Не учитывать подавленные проблемы в счётчике значка «Проблемы».",
        "Do not count suppressed problems in the Problems badge tally.",
    ),
    (
        "Ввод изменён. Нажмите «Проверить», чтобы обновить предпросмотр.",
        "Input changed. Click \"Test\" to refresh the preview.",
    ),
    (
        "Найдено совпадений по имени: %s. Выберите точное имя элемента:",
        "Name matches found: %s. Pick an exact item name:",
    ),
    (
        "Открывать ссылки метрик и значков в текущей вкладке браузера.",
        "Open metric and badge links in the current browser tab.",
    ),
    (
        "должен содержать по одной записи для каждого выбранного узла",
        "must contain one entry per selected host",
    ),
    (
        "Анимировать значок проблем при наличии активных инцидентов.",
        "Animate the problems badge when there are active incidents.",
    ),
    (
        "Дополнительные поля JSON (сливаются с переопределениями)",
        "Extra JSON fields (merged with overrides)",
    ),
    (
        "Строка узла %1$s: некорректный параметр «%2$s»: %3$s.",
        "Host row %1$s: invalid parameter \"%2$s\": %3$s.",
    ),
    (
        "Введите шаблон с «*» для предпросмотра интерфейсов.",
        "Enter a template with \"*\" to preview interfaces.",
    ),
    (
        "Строка узла %1$s: поле «%2$s» не может быть пустым.",
        "Host row %1$s: field \"%2$s\" cannot be empty.",
    ),
    (
        "Введите шаблон с «*» для предпросмотра разделов.",
        "Enter a template with \"*\" to preview partitions.",
    ),
    (
        "Выберите один или несколько узлов в поле выше.",
        "Pick one or more hosts in the field above.",
    ),
    (
        "Введите шаблон с «*» для предпросмотра дисков.",
        "Enter a template with \"*\" to preview disks.",
    ),
    (
        "Значок «%1$s» можно добавить только один раз.",
        "Badge \"%1$s\" can be added only once.",
    ),
    (
        "Добавьте хотя бы один символ «*» в шаблон.",
        "Add at least one \"*\" character to the template.",
    ),
    ("Не удалось выполнить проверку совпадений.", "Could not run the match check."),
    ("должен содержать хотя бы один символ «*»", "must contain at least one \"*\" character"),
    ("Узел %1$s: отсутствует в текущем выборе.", "Host %1$s: not in the current selection."),
    ("Введите имя элемента для предпросмотра.", "Enter an item name for preview."),
    ("Значок %1$s: для ссылки URL обязателен.", "Badge %1$s: URL is required for link type."),
    ("Введите шаблон с «*» для предпросмотра.", "Enter a template with \"*\" for preview."),
    ("Сузьте запрос, чтобы укоротить список.", "Narrow the query to shorten the list."),
    ("Единственное частичное совпадение: %s.", "Single partial match: %s."),
    ("Подходящих имён элементов не найдено.", "No suitable item names were found."),
    ("Не учитывать подтверждённые проблемы", "Ignore acknowledged problems"),
    ("Подставлено точное имя элемента: %s.", "Inserted exact item name: %s."),
    ("требуется минимум %1$s символов «*»", "requires at least %1$s \"*\" characters"),
    ("Не удалось прочитать ответ сервера.", "Could not read the server response."),
    ("Некорректный параметр «%1$s»: %2$s.", "Invalid parameter \"%1$s\": %2$s."),
    ("Свой список значков (необязательно)", "Custom badge list (optional)"),
    ("Совпадающих интерфейсов не найдено.", "No matching interfaces."),
    ("Вместо JSON получена HTML-страница.", "Received an HTML page instead of JSON."),
    ("Значок %1$s: неподдерживаемый тип.", "Badge %1$s: unsupported type."),
    ('Пример: {"metrics_show":["0","1"]}', 'Example: {"metrics_show":["0","1"]}'),
    ("Не учитывать подавленные проблемы", "Ignore suppressed problems"),
    ("Перетащите для изменения порядка", "Drag to reorder"),
    ("Совпадающих разделов не найдено.", "No matching partitions."),
    ("Открывать ссылки в этой вкладке", "Open links in this tab"),
    ("Совпадающих дисков не найдено.", "No matching disks."),
    ("https://example.com или /path", "https://example.com or /path"),
    ("Профили узлов (синхронизация)", "Host profiles (sync)"),
    ("Процессор, память и нагрузка", "CPU, memory, and load"),
    ("Доп. переопределения (JSON)", "Extra overrides (JSON)"),
    ("должен быть корректным JSON", "must be valid JSON"),
    ("Сервер вернул пустой ответ.", "Server returned an empty response."),
    ("Переопределения по узлам", "Per-host overrides"),
    ("Пульсация значка проблем", "Problems badge pulse"),
    ("Рядом с именем (сводка)", "Next to the name (summary)"),
    ("Живость: предупреждение", "Liveliness: warning"),
    ("Сначала выберите узел.", "Select a host first."),
    ("Точное совпадение: %s.", "Exact match: %s."),
    ("Совпадений не найдено.", "No matches found."),
    ("Обновить панели узлов", "Refresh host panels"),
    ("Только в детализации", "Details only"),
    ("Открыть детали: %1$s", "Open details: %1$s"),
    ("не может быть пустым", "cannot be empty"),
    ("Интерфейсы: высокий", "Interfaces: high"),
    ("Просмотр спарклайна", "View sparkline"),
    ("Интерфейсы: средний", "Interfaces: medium"),
    ("Показывать метрики", "Show metrics"),
    ("Исключено фильтром", "Excluded by filter"),
    ("Потолок интерфейса", "Interface ceiling"),
    ("Фильтр интерфейсов", "Interface filter"),
    ("Элемент: процессор", "Item: CPU"),
    ("Процессор: высокий", "CPU: high"),
    ("Единица интерфейса", "Interface unit"),
    ("Инвертировать своп", "Invert swap"),
    ("Процессор: средний", "CPU: medium"),
    ("Элемент: нагрузка", "Item: load"),
    ("Нагрузка: средний", "Load: medium"),
    ("Элемент «живости»", "Liveliness item"),
    ("Поиск совпадений…", "Looking up matches…"),
    ("Нагрузка: высокий", "Load: high"),
    ("Общие: оформление", "Global: appearance"),
    ("Шаблон интерфейса", "Interface template"),
    ("Живость: устарело", "Liveliness: stale"),
    ("Потолок нагрузки", "Load ceiling"),
    ("Пороги «живости»", "Liveliness thresholds"),
    ("Последние данные", "Latest data"),
    ("Элемент аптайма", "Uptime item"),
    ("Фильтр разделов", "Partition filter"),
    ("Текст на значке", "Badge text"),
    ("Память: высокий", "Memory: high"),
    ("Память: средний", "Memory: medium"),
    ("Раздел: средний", "Partition: medium"),
    ("Раздел: высокий", "Partition: high"),
    ("Совпадения (%s)", "Matches (%s)"),
    ("Элемент: память", "Item: memory"),
    ("Шаблон раздела", "Partition template"),
    ("Цветовая схема", "Color scheme"),
    ("Загрузка диска", "Disk utilization"),
    ("Фильтр дисков", "Disk filter"),
    ("Сплошной цвет", "Solid color"),
    ("Цвет: обычный", "Color: normal"),
    ("Общие: значки", "Global: badges"),
    ("Своп: высокий", "Swap: high"),
    ("Цвет: средний", "Color: medium"),
    ("Диск: средний", "Disk: medium"),
    ("Элемент: своп", "Item: swap"),
    ("Меню элемента", "Item menu"),
    ("Цвет: высокий", "Color: high"),
    ("Диск: высокий", "Disk: high"),
    ("Своп: средний", "Swap: medium"),
    ("Высота полос", "Bar height"),
    ("Обслуживание", "Maintenance"),
    ("Шаблон диска", "Disk template"),
    ("Скруглённые", "Rounded"),
    ("Отображение", "Display"),
    ("Интерфейсы", "Interfaces"),
    ("По порогам", "By thresholds"),
    ("Нет данных", "No data"),
    ("Процессор", "CPU"),
    ("Проверить", "Test"),
    ("Псевдоним", "Alias"),
    ("К списку", "Back to list"),
    ("Добавить", "Add"),
    ("Сплошной", "Solid"),
    ("Устарело", "Stale"),
    ("Сплошная", "Solid fill"),
    ("Нагрузка", "Load"),
    ("К обзору", "Back to overview"),
    ("Предупр.", "Warn"),
    ("Короткие", "Short"),
    ("Разделы", "Partitions"),
    ("Обычный", "Normal"),
    ("Высокий", "High"),
    ("Удалить", "Remove"),
    ("Средний", "Medium"),
    ("Подписи", "Labels"),
    ("Метрики", "Metrics"),
    ("Справа", "Right"),
    ("Кбит/с", "Kbps"),
    ("Память", "Memory"),
    ("Мбит/с", "Mbps"),
    ("Гбит/с", "Gbps"),
    ("Значки", "Badges"),
    ("Прямые", "Square"),
    ("Полные", "Full"),
    ("Назад", "Back"),
    ("Слева", "Left"),
    ("этот", "this"),
    ("Углы", "Corners"),
    ("Своп", "Swap"),
    ("Узлы", "Hosts"),
    ("Нет данных", "No data"),
    ("Имя хоста", "Hostname"),
    ("Аптайм", "Uptime"),
    ("Живость", "Liveliness"),
    ("Проблемы", "Problems"),
    ("Текст", "Text"),
    ("Ссылка", "Link"),
    ("Теги", "Tags"),
]

seen_ru: set[str] = set()
_deduped: list[tuple[str, str]] = []
for r, e in RU_EN:
    if r in seen_ru:
        continue
    seen_ru.add(r)
    _deduped.append((r, e))
RU_EN = sorted(_deduped, key=lambda x: len(x[0]), reverse=True)

TEXT_FILES = [
    ROOT / "actions" / "WidgetView.php",
    ROOT / "includes" / "CWidgetFieldBadgesList.php",
    ROOT / "includes" / "WidgetForm.php",
    ROOT / "views" / "widget.edit.php",
    ROOT / "views" / "components" / "layout.metric.php",
    ROOT / "views" / "components" / "layout.multi_host.php",
    ROOT / "views" / "components" / "layout.sparkline.php",
]


def apply_replacements() -> dict[str, str]:
    en_ru: dict[str, str] = {}
    for ru, en in RU_EN:
        en_ru[en] = ru

    for path in TEXT_FILES:
        text = path.read_text(encoding="utf-8")
        orig = text
        for ru, en in RU_EN:
            text = text.replace(ru, en)
        if text != orig:
            path.write_text(text, encoding="utf-8")

    return en_ru


def _decode_po_piece(body: str) -> str:
    return (
        body.replace("\\\\", "\x00ESC\x00")
        .replace('\\"', '"')
        .replace("\\n", "\n")
        .replace("\\t", "\t")
        .replace("\x00ESC\x00", "\\")
    )


def _encode_po_piece(s: str) -> str:
    return (
        s.replace("\\", "\\\\")
        .replace('"', '\\"')
        .replace("\n", "\\n")
        .replace("\t", "\\t")
    )


def _read_po_string_block(lines: list[str], idx: int, keyword: str) -> tuple[str, int]:
    """Read msgid/msgstr block starting at lines[idx]. Returns decoded string and index after last consumed line."""
    first = lines[idx].rstrip("\n")
    prefix = f"{keyword} "
    if not first.startswith(prefix):
        return "", idx
    m = re.match(rf'^{re.escape(keyword)}\s+"(.*)"\s*$', first)
    if not m:
        return "", idx
    parts: list[str] = [_decode_po_piece(m.group(1))]
    j = idx + 1
    while j < len(lines):
        ln = lines[j].rstrip("\n")
        mm = re.match(r'^"(.*)"\s*$', ln)
        if not mm:
            break
        parts.append(_decode_po_piece(mm.group(1)))
        j += 1
    return "".join(parts), j


def build_po_from_pot(pot: Path, en_ru: dict[str, str], out_po: Path) -> None:
    raw_lines = pot.read_text(encoding="utf-8").splitlines(keepends=True)
    out: list[str] = []
    i = 0
    while i < len(raw_lines):
        if raw_lines[i].startswith("msgid "):
            block_start = i
            msgid, j = _read_po_string_block(raw_lines, i, "msgid")
            i = j
            while i < len(raw_lines) and not raw_lines[i].startswith("msgstr "):
                i += 1
            if i >= len(raw_lines):
                out.extend(raw_lines[block_start:])
                break
            msgstr_start = i
            _, i = _read_po_string_block(raw_lines, i, "msgstr")
            if msgid == "":
                out.extend(raw_lines[block_start:i])
            else:
                out.extend(raw_lines[block_start:msgstr_start])
                ru = en_ru.get(msgid, "")
                out.append(f'msgstr "{_encode_po_piece(ru)}"\n')
        else:
            out.append(raw_lines[i])
            i += 1
    out_po.write_text("".join(out), encoding="utf-8")


def main() -> None:
    en_ru = apply_replacements()
    # Save mapping for maintainers
    (ROOT / "locale" / "en-ru.msgmap.json").write_text(
        json.dumps(en_ru, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    locale_dir = ROOT / "locale"
    locale_dir.mkdir(parents=True, exist_ok=True)
    php_files = sorted(
        p for p in ROOT.rglob("*.php") if "locale" not in p.parts and p.name != "apply_english_msgids.py"
    )
    pot = locale_dir / "main_overview.pot"
    lst = Path("/tmp/main_overview_php_files.txt")
    lst.write_text("\n".join(str(p) for p in php_files) + "\n", encoding="utf-8")

    subprocess.run(
        [
            "xgettext",
            "--from-code=UTF-8",
            "-o",
            str(pot),
            "-L",
            "PHP",
            "-k_m:1",
            "-k_ms:1,2",
            "-f",
            str(lst),
            "--package-name=main_overview",
            "--package-version=0.5.5",
        ],
        check=True,
    )

    ru_dir = locale_dir / "ru_RU" / "LC_MESSAGES"
    ru_dir.mkdir(parents=True, exist_ok=True)
    ru_po = ru_dir.parent / "main_overview.po"
    build_po_from_pot(pot, en_ru, ru_po)

    mo = ru_dir / "main_overview.mo"
    subprocess.run(["msgfmt", "-o", str(mo), str(ru_po)], check=True)
    print("Wrote", pot, ru_po, mo)


if __name__ == "__main__":
    main()
