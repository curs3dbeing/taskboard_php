# Решение проблем с деплоем на Railway

## Ошибка: "Application failed to respond"

Эта ошибка означает, что приложение не запускается или не отвечает на запросы. Вот основные причины и решения:

### 1. Проверьте логи Railway

**Самое важное!** Откройте Railway Dashboard:
- Перейдите в ваш проект
- Откройте вкладку "Deployments"
- Выберите последний деплой
- Нажмите "View Logs"

Ищите ошибки типа:
- `Fatal error`
- `Parse error`
- `Class not found`
- `Database connection failed`

### 2. Проблемы с запуском PHP сервера

**Симптомы:** В логах нет ошибок, но приложение не отвечает

**Решение:**
- Убедитесь, что `Procfile` или `nixpacks.toml` правильно настроены
- Проверьте, что используется переменная `$PORT` (Railway автоматически устанавливает её)

**Проверка:**
```bash
# В Procfile должно быть:
web: php -S 0.0.0.0:$PORT
```

### 3. Ошибка подключения к базе данных

**Симптомы:** В логах видно "Database connection failed"

**Причины:**
- Неправильный хост базы данных
- База данных не запущена
- Неверные учетные данные

**Решение:**

1. **Проверьте хост базы данных:**
   - В Railway MySQL используйте **публичный хост**, а не `mysql.railway.internal`
   - Публичный хост находится в настройках MySQL сервиса → "Connect" → "Public Network"

2. **Проверьте переменные окружения:**
   - Railway автоматически создает `MYSQLHOST`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
   - Убедитесь, что config.php использует их правильно

3. **Обновите config.php:**
```php
// Используйте публичный хост из Railway
define('DB_HOST', getenv('MYSQLHOST') ?: 'your-public-mysql-host.railway.app');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: 'your-password');
```

### 4. Отсутствие файлов

**Симптомы:** "File not found" или "Class not found"

**Проверьте:**
- Все файлы загружены в GitHub репозиторий
- Папка `PHPMailer/` присутствует
- Нет ошибок в `.gitignore`, которые исключают нужные файлы

### 5. Ошибки синтаксиса PHP

**Симптомы:** "Parse error" в логах

**Решение:**
- Проверьте все PHP файлы на синтаксические ошибки локально
- Убедитесь, что используется правильная версия PHP (Railway использует PHP 8.2)

### 6. Проблемы с путями

**Симптомы:** "require_once failed" или "file not found"

**Решение:**
- Используйте абсолютные пути: `__DIR__ . '/config.php'`
- Проверьте, что все `require_once` указывают на правильные файлы

### 7. Проблемы с сессиями

**Симптомы:** Сессии не сохраняются

**Решение:**
- Railway может использовать несколько инстансов
- Добавьте в `config.php`:
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
```

## Диагностика

### Шаг 1: Откройте test.php

После деплоя откройте:
```
https://your-app.up.railway.app/test.php
```

Этот файл покажет:
- Работает ли PHP
- Подключение к базе данных
- Наличие всех файлов
- Настройки окружения

### Шаг 2: Проверьте health.php

Railway может автоматически проверять health endpoint:
```
https://your-app.up.railway.app/health.php
```

Должен вернуть: `OK`

### Шаг 3: Проверьте логи

В Railway Dashboard → Deployments → View Logs ищите:
- Ошибки PHP
- Ошибки подключения к БД
- Предупреждения о недостающих файлах

## Частые ошибки и решения

### Ошибка: "Class 'PHPMailer\PHPMailer\PHPMailer' not found"

**Решение:**
1. Убедитесь, что папка `PHPMailer/` в репозитории
2. Или установите через Composer:
```bash
composer require phpmailer/phpmailer
```

### Ошибка: "Access denied for user"

**Решение:**
- Проверьте учетные данные MySQL в Railway
- Убедитесь, что используете правильный хост (публичный, не внутренний)

### Ошибка: "Table doesn't exist"

**Решение:**
- Запустите `init_db.php` для создания таблиц
- Или выполните SQL из `database.sql` вручную

### Ошибка: "Port already in use"

**Решение:**
- Убедитесь, что используете `$PORT` переменную, а не фиксированный порт
- Проверьте `Procfile` или `nixpacks.toml`

## Быстрая проверка

1. ✅ Откройте `/test.php` - все должно быть зеленым
2. ✅ Проверьте логи Railway - не должно быть ошибок
3. ✅ Проверьте, что MySQL сервис запущен
4. ✅ Убедитесь, что база данных инициализирована
5. ✅ Проверьте переменные окружения в Railway Settings

## Если ничего не помогает

1. Проверьте логи Railway детально
2. Попробуйте пересоздать проект
3. Убедитесь, что локально все работает
4. Проверьте документацию Railway: https://docs.railway.app

