# mrakells.com

Личный сайт на WordPress: своя тема `mrakells` и mu-плагин `debloat`.

## Что в репозитории

- `wordpress/wp-content/themes/mrakells` — тема: шаблоны, `inc/` с логикой сайта, исходники в `src/`, собранные файлы в `dist/`.
- `wordpress/wp-content/mu-plugins/debloat.php` — отключение лишнего в WordPress и закрытый REST API.
- `.ddev/config.yaml` — локальное окружение.

Ядро WordPress, плагины и загрузки в репозиторий не входят.

## Локально

```sh
ddev start
ddev exec -d /var/www/html/wordpress/wp-content/themes/mrakells npm ci
ddev exec -d /var/www/html/wordpress/wp-content/themes/mrakells npm run build
```

Сайт: https://mrakells.ddev.site

`dist/` хранится в репозитории: на сервере нет Node, туда копируется уже собранная тема.

## Выкладка

Скопировать на сервер `themes/mrakells` (без `node_modules`) и `mu-plugins/debloat.php`, затем очистить кэш страниц Cache Enabler.
