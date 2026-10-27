# tolk.onlyoffice — ONLYOFFICE Docs для коробочного Битрикс24

## Document Server (Docker)

```yaml
services:
  onlyoffice:
    image: onlyoffice/documentserver:<закрепите версию>
    restart: unless-stopped
    environment:
      JWT_ENABLED: "true"
      JWT_SECRET: "<длинный случайный секрет>"
      JWT_IN_BODY: "true"              # токен в теле callback — не зависит от проброса Authorization
      ALLOW_PRIVATE_IP_ADDRESS: "true" # портал во внутренней сети
      ALLOW_META_IP_ADDRESS: "true"
      # USE_UNAUTHORIZED_STORAGE: "true"  # если у портала самоподписанный сертификат
    volumes:
      - ./local-production-linux.json:/etc/onlyoffice/documentserver/local-production-linux.json:ro
      - ./data:/var/www/onlyoffice/Data
      - ./logs:/var/log/onlyoffice
    ports:
      - "443:443"
```

`local-production-linux.json` — периодическое сохранение открытых документов в Битрикс:

```json
{ "services": { "CoAuthoring": { "autoAssembly": { "enable": true, "interval": "5m", "step": "1m" } } } }
```

Если файл не подхватился, добавьте эту секцию в `/etc/onlyoffice/documentserver/local.json`
внутри контейнера и выполните `supervisorctl restart all`.

## Битрикс

1. Скопировать `tolk.onlyoffice` в `/local/modules/`.
2. Админка → Marketplace → Установленные решения → установить.
3. Настройки → Настройки модулей → «ONLYOFFICE для Диска»: адрес Document Server и JWT-секрет.
4. Проверить вручную: `/onlyoffice/edit.php?fileId=<ID файла на Диске>`.
5. Настроить атрибуты в `/bitrix/js/tolk.onlyoffice/disk.js` под разметку своей версии Диска.

Лог ошибок: `AddMessage2Log` → файл из константы `LOG_FILENAME` в `dbconn.php`.

## Блокировка файлов (0.2.0)

- Первый, кто открыл файл на редактирование, ставит штатную блокировку Диска
  (значок замка, загрузка новых версий другими пользователями запрещена).
- Остальные пользователи с правом изменения присоединяются к той же сессии совместного редактирования.
- Если файл заблокирован вручную (через «Заблокировать» на Диске), он открывается только на просмотр с плашкой.
- Блокировка снимается, когда все закрыли документ (статус 2 или 4).
- Агент `\Tolk\OnlyOffice\Agent::cleanup()` раз в 10 минут проверяет сессии, от которых 30 минут
  не было сигналов, через Command Service и снимает блокировки умерших сессий.
  Рекомендуется перевести агенты на cron, иначе на тихом портале они запускаются редко.
- Обновление с 0.1.0: переустановить модуль, когда никто не редактирует документы
  (таблица сессий пересоздаётся).

## Создание документов (0.3.0)

- В меню «Добавить» на Диске появляются пункты «Документ», «Таблица», «Презентация».
  Файл создаётся в текущей папке из шаблонов `templates/new.*` и сразу открывается в ONLYOFFICE.
- Пункты Google Docs, MS Office Online, Office365 и «Программы на компьютере» скрываются
  (список — `HIDE_ITEMS` в disk.js).
- Шаблоны можно заменить своими (бланк компании, поля, шрифты), имена файлов должны остаться new.docx/new.xlsx/new.pptx.
