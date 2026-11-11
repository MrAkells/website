# mrakells.com

Единое рабочее место для сайта https://mrakells.com/: локальная копия в DDEV, git с нашим кодом и работа с продом по ssh.

## Окружения
- **Локалка** — https://mrakells.ddev.site (DDEV, WordPress в `wordpress/`). Все правки сначала здесь.
- **Прод** — `ssh m-srv-hetzner` (пользователь `mrakells`, sudo без пароля). Сайт живёт в Docker: `/home/mrakells/docker/wordpress` (`compose.yaml`, сервисы `wordpress` и `db`), файлы WordPress — `./html`, владелец `www-data:www-data`. Caddy на хосте проксирует на `127.0.0.1:8088`.
  В `/home/mrakells/docker/` рядом другие сервисы (`forgejo` — это сам git.mrakells.com, `mailserver`, `vaultwarden`, `wg-easy` и др.) — их не трогать.

Основной путь изменений: **локалка → прод**.

## Стек
- WordPress со своей темой, без конструкторов. Плагины: Secure Custom Fields, All-in-One WP Migration, на проде ещё Cache Enabler.
- PHP: локально 8.4, на проде 8.3 (образ `wordpress:latest`) — синтаксис и функции из 8.4 не использовать. БД: локально MariaDB 11.8, на проде 11.4 (том `wordpress_db_data`).
- Фронт темы: Vite + Tailwind 4, исходники в `src/`, сборка в `dist/`. `dist/` хранится в git: на сервере Node нет, туда уезжает уже собранная тема.
- Кэш на проде: Cache Enabler держит готовый HTML 24 часа в `html/wp-content/cache/cache-enabler`. Локально его нет.
- Игровые панели (сервер GTNH, квесты) от кэша страниц не зависят: их отдаёт публичный REST-маршрут темы `/mrakells/v1/server` (`inc/live.php`). Контейнер ходит к `gtnh.mrakells.com` по приватному адресу через `extra_hosts` в `compose.yaml`.
- WP-Cron на проде дёргает `run-cron.sh` из crontab пользователя раз в минуту, ошибки — в `cron.log`.
- WP-CLI: локально `ddev wp ...`. На проде в контейнере `wp` нет; `wp-cli.phar` лежит рядом с `compose.yaml`, но в контейнер не смонтирован — если понадобится, сначала выяснить у пользователя, как его запускать, и дописать сюда.

## Наш код (в git)
- Тема `wordpress/wp-content/themes/mrakells` (активная): шаблоны, `inc/` с логикой сайта, `src/`, `dist/`, `images/`, `languages/`.
- `wordpress/wp-content/mu-plugins/debloat.php` — отключение лишнего в WordPress и закрытый REST API.
- Ядра, плагинов, `uploads` и дампов в git нет: `.gitignore` — whitelist, пропускает только наш код. Файлы сторонних плагинов не править ни локально, ни на проде.
- Серверная обвязка (`compose.yaml`, `.env`, `run-cron.sh`, серверный `README.md`) в репозиторий не входит и живёт только на проде.

## Правила кода
- Одна фича сайта — один файл в `inc/` темы (`minecraft.php`, `quests.php`, `i18n.php`, `seo.php`…). Не дробить фичу между темой и mu-плагином; `debloat.php` — исключение, это общая чистка WordPress.
- Ширина страниц единая, правило описано в README.md (раздел «Ширина страниц») и в комментарии к токенам в `src/css/app.css`. Новые шаблоны занимают весь контейнер; текст — `max-w-measure` или сетка `split`; произвольные `max-w-[…]` для раскладки не добавлять.
- Палитра и шрифты — только токены из `@theme` в `src/css/app.css`; умолчания Tailwind сброшены.
- Сайт работает без JavaScript: всё, что включает скрипт, в разметке скрыто или отсутствует.
- Языки: основной (en) в корне, `/uk/` и `/ru/` — префиксом (`inc/i18n.php`). Новые строки шаблонов оборачивать в функции перевода и добавлять в `languages/uk.l10n.php` и `languages/ru_RU.l10n.php`.
- Комментарии в коде — на русском, объясняют «зачем».

## Репозиторий
- `git@git.mrakells.com:MrAkells/website.git` (Forgejo), ветка `main`.
- `main` = то, что сейчас на проде. Каждая выкатка и каждый хотфикс сразу коммитятся.
- История старого сайта на Astro сохранена ниже коммита от 2026-11-11, который заменил его темой.

## Команды
- `ddev start` / `ddev stop`.
- Сборка темы (на хосте Node нет, только в DDEV):
  `ddev exec -d /var/www/html/wordpress/wp-content/themes/mrakells npm run build`, затем `ddev mutagen sync`. Первый раз — сначала `npm ci` тем же способом.
- После сборки меняются имена файлов в `dist/assets` (хэш в имени) — старые удаляются, новые добавляются, всё это коммитится вместе с исходниками.
- Быстрый скриншот без браузера пользователя: `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --ignore-certificate-errors --hide-scrollbars --window-size=1440,900 --screenshot=<файл> https://mrakells.ddev.site/`. Окно уже ~500px так не проверить.

Готовых команд `ddev deploy` / `ddev prod-diff`, как в других проектах, здесь нет — выкладка делается шагами ниже.

## Обычная правка кода (локалка → прод)
1. Правка, сборка темы и проверка на локалке.
2. Коммит.
3. Собрать релиз **из последнего коммита**, а не из рабочей копии (так не уедут `node_modules` и незакоммиченное):
   ```sh
   REL=$(mktemp -d)
   git archive HEAD wordpress/wp-content/themes/mrakells wordpress/wp-content/mu-plugins | tar -x -C "$REL"
   ```
4. Предпросмотр — что изменится на проде:
   ```sh
   rsync -rcn --delete --itemize-changes --rsync-path="sudo rsync" \
     "$REL/wordpress/wp-content/themes/mrakells/" \
     m-srv-hetzner:/home/mrakells/docker/wordpress/html/wp-content/themes/mrakells/
   ```
   Если в списке есть файлы, которых мы не меняли, — это правка на проде: сначала забрать её в рабочую копию и закоммитить, потом заново.
5. Бэкап темы на сервере:
   ```sh
   ssh m-srv-hetzner 'cd /home/mrakells/docker/wordpress && sudo tar czf backups/theme-$(date +%Y%m%d-%H%M%S).tar.gz -C html/wp-content/themes mrakells'
   ```
6. Выкладка, владелец и сброс кэша страниц:
   ```sh
   rsync -rc --delete-after --delay-updates --itemize-changes --rsync-path="sudo rsync" \
     "$REL/wordpress/wp-content/themes/mrakells/" \
     m-srv-hetzner:/home/mrakells/docker/wordpress/html/wp-content/themes/mrakells/
   ssh m-srv-hetzner 'cd /home/mrakells/docker/wordpress && sudo chown -R www-data:www-data html/wp-content/themes/mrakells && sudo find html/wp-content/cache/cache-enabler -type f -delete'
   ```
   У rsync из macOS нет `--chown`, поэтому владелец выставляется отдельно. `debloat.php` выкатывается так же, но в `html/wp-content/mu-plugins/` и без `--delete`.
7. Проверить https://mrakells.com/ (и `/uk/`, `/ru/`): страница отдаёт новые `app-*.js` / `app-*.css`, в логах контейнера нет ошибок PHP.
8. `git push`.

## Правила для прода
- Агенту разрешено работать на проде по ssh самостоятельно: чтение, диагностика, выкладка нашего кода по шагам выше. Подтверждение нужно для деструктивного и массового (см. ниже).
- Объём бэкапа — по тому, насколько трудно откатить:
  - **Точечное и легко обратимое** (одна опция, один пост, настройка в админке): без бэкапа, но старое значение сначала вывести в чат.
  - **Правка кода**: архив затрагиваемой темы или файла.
  - **Массовое или необратимое в БД** (много записей, `search-replace`, удаление, импорт, обновление плагинов, меняющих схему): дамп БД.
- Все бэкапы и технические файлы на сервере — только в `/home/mrakells/docker/wordpress/backups/`, не в `html/` (веб-корень доступен снаружи). Имена с датой: `theme-YYYYMMDD-HHMMSS.tar.gz`.
- После правки PHP: `docker compose exec -T wordpress php -l /var/www/html/wp-content/themes/mrakells/<файл>` — это PHP прода (8.3), затем открыть сайт и посмотреть `docker compose logs --tail=100 wordpress`.
- Новые и изменённые файлы в `html/` — владелец `www-data:www-data`.
- После изменений фронта сбросить кэш страниц: удалить файлы в `html/wp-content/cache/cache-enabler` (каталог оставить).
- Секреты из `.env`, `compose.yaml` и `wp-config.php` не выводить в чат и не копировать в файлы. Читая `compose.yaml`, строки с паролями отфильтровывать.
- Только после подтверждения пользователя: удаление, `search-replace`, массовые изменения в БД, обновление/установка/удаление плагинов и ядра, `docker compose up/down/restart`, обновление образов, правка `compose.yaml`, Caddy и crontab.
- Серверное время — UTC.

## Хотфикс прямо на проде
Когда пользователь просит «сделай сразу на проде» и это мелочь:
1. Править на сервере по правилам выше (архив в `backups/`, `php -l` в контейнере, логи, сброс кэша).
2. Если менялся наш код — сразу забрать файл в рабочую копию, коммит `fix: ...`, `git push`. Git не должен отставать от прода.
3. Изменения в админке и БД в git не попадают — старое значение выводится в чат.

## Логи на проде
- PHP и Apache: `cd /home/mrakells/docker/wordpress && docker compose logs --tail=100 wordpress`.
- Cron: `/home/mrakells/docker/wordpress/cron.log`.
- Caddy: `/etc/caddy/Caddyfile`.
