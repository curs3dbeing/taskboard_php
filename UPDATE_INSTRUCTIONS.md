# Инструкция по обновлению базы данных

## Если база данных уже создана

Если вы уже использовали приложение и база данных `task_planner` уже существует, выполните следующие шаги:

### Вариант 1: Использование SQL скрипта (Рекомендуется)

1. Откройте MySQL командную строку или phpMyAdmin
2. Выполните SQL скрипт `database_update.sql`:

```sql
USE task_planner;

ALTER TABLE tasks 
ADD COLUMN priority TINYINT DEFAULT 2 COMMENT '1=Критическая, 2=Средней важности, 3=По мере возможности';

ALTER TABLE tasks 
ADD COLUMN completed TINYINT(1) DEFAULT 0 COMMENT '0=Не выполнена, 1=Выполнена';

ALTER TABLE tasks 
ADD INDEX idx_priority (priority),
ADD INDEX idx_completed (completed);
```

### Вариант 2: Через командную строку

```bash
mysql -u root -proot task_planner < database_update.sql
```

### Вариант 3: Через phpMyAdmin

1. Откройте phpMyAdmin: `http://localhost/phpmyadmin`
2. Выберите базу данных `task_planner`
3. Перейдите на вкладку "SQL"
4. Скопируйте и вставьте содержимое файла `database_update.sql`
5. Нажмите "Выполнить"

## Если база данных еще не создана

Просто выполните основной скрипт:

```bash
mysql -u root -proot < database.sql
```

Или импортируйте `database.sql` через phpMyAdmin.

## Проверка обновления

После выполнения обновления проверьте структуру таблицы `tasks`:

```sql
DESCRIBE tasks;
```

Должны появиться новые поля:
- `priority` (TINYINT, по умолчанию 2)
- `completed` (TINYINT(1), по умолчанию 0)

## Новые возможности

После обновления базы данных вы сможете:

1. ✅ Устанавливать важность задач (Критическая, Средней важности, По мере возможности)
2. ✅ Сортировать задачи по важности (критические сначала)
3. ✅ Отмечать задачи как выполненные
4. ✅ Просматривать выполненные задачи в отдельной вкладке
5. ✅ Возвращать выполненные задачи обратно в активные

