# Инструкция по деплою на Railway

## Шаги для деплоя

### 1. Подключение GitHub репозитория к Railway

1. Зайдите на [Railway](https://railway.app)
2. Нажмите "New Project"
3. Выберите "Deploy from GitHub repo"
4. Выберите ваш репозиторий
5. Railway автоматически определит PHP проект

### 2. Настройка базы данных MySQL

1. В Railway проекте нажмите "+ New" → "Database" → "MySQL"
2. Railway создаст базу данных автоматически
3. Скопируйте данные подключения:
   - `MYSQLHOST`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLPORT`

### 3. Обновление config.php (рекомендуется использовать переменные окружения)

**Вариант A: Использовать переменные окружения (БЕЗОПАСНО)**

Обновите `config.php` чтобы использовать переменные окружения:

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'task_planner');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:8000');
```

Затем в Railway:
1. Перейдите в Settings → Variables
2. Добавьте переменные:
   - `DB_HOST` = значение из Railway MySQL
   - `DB_NAME` = значение из Railway MySQL
   - `DB_USER` = значение из Railway MySQL
   - `DB_PASS` = значение из Railway MySQL
   - `SITE_URL` = ваш Railway URL (например: `https://your-app.up.railway.app`)

**Вариант B: Прямое указание в config.php (текущий способ)**

Убедитесь, что в `config.php` указаны правильные данные из Railway MySQL.

### 4. Инициализация базы данных

После первого деплоя нужно создать таблицы в базе данных:

**Вариант A: Через Railway MySQL Console**

1. В Railway откройте вашу MySQL базу данных
2. Перейдите в "Data" → "Query"
3. Выполните SQL из `database.sql`:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    password_reset_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_reset_token (password_reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    priority TINYINT DEFAULT 2 COMMENT '1=Критическая, 2=Средней важности, 3=По мере возможности',
    completed TINYINT(1) DEFAULT 0 COMMENT '0=Не выполнена, 1=Выполнена',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_priority (priority),
    INDEX idx_completed (completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Вариант B: Через PHP скрипт (автоматически)**

Создайте временный файл `init_db.php` в корне проекта:

```php
<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Создание таблицы users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        password_reset_token VARCHAR(255) DEFAULT NULL,
        password_reset_expires DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_username (username),
        INDEX idx_reset_token (password_reset_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Создание таблицы tasks
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        priority TINYINT DEFAULT 2,
        completed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_priority (priority),
        INDEX idx_completed (completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "База данных успешно инициализирована!";
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

Откройте `https://your-app.up.railway.app/init_db.php` один раз, затем удалите файл.

### 5. Настройка домена (опционально)

1. В Railway Settings → Networking
2. Нажмите "Generate Domain" или добавьте свой домен
3. Обновите `SITE_URL` в config.php на новый домен

### 6. Проверка деплоя

1. Откройте ваш Railway URL
2. Должна открыться страница логина
3. Зарегистрируйте первого пользователя
4. Проверьте создание задач

## Важные замечания

1. **Безопасность**: Не коммитьте `config.php` с реальными паролями в GitHub. Используйте переменные окружения.

2. **PHPMailer**: Убедитесь, что папка `PHPMailer/` загружена в репозиторий, или используйте Composer для установки.

3. **Сессии**: Railway может использовать несколько инстансов, поэтому сессии могут не работать. Рассмотрите использование Redis для сессий.

4. **Логи**: Проверяйте логи в Railway Dashboard → Deployments → View Logs

## Решение проблем

### Ошибка подключения к базе данных
- Проверьте, что MySQL сервис запущен в Railway
- Убедитесь, что используете правильный хост (может быть `mysql.railway.internal` или публичный хост)
- Проверьте переменные окружения

### 404 ошибки
- Убедитесь, что `index.php` в корне проекта
- Проверьте настройки веб-сервера

### Ошибки с сессиями
- Добавьте в `config.php`:
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Только для HTTPS
```

## Полезные команды Railway CLI

```bash
# Установка Railway CLI
npm i -g @railway/cli

# Логин
railway login

# Подключение к проекту
railway link

# Просмотр логов
railway logs

# Открыть базу данных
railway connect mysql
```

