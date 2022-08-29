## SsoDepartmentPosition ###

### Кеширующая таблица для данных Sso ###

### Установка ###

```
composer require erg/sso-department-position

php artisan vendor:publich

```

Последняя команда добавит в проект следующие файлы:

```
config/sso_deparment_position.php
```

необходимо заполнить 'table_name'.

```
database/migrations/2022_09_29_090000_create_department_positions_table.php
```

При необходимости отредактируйте её, или уберите, если таблица уже создана ранее

```
App/Console/Utils/FunctionalDirections.php
```

Доработайте метод getFunctionalDirections()

Впишите команду Erg\SsoDepartmentPosition\Console\LoadDepartmentPositions.php для выполнения по расписанию в:
* app/Console/Kernel.php
* или непосредственно в Cron


