# Настройка Resend API для отправки email

## Почему Resend API?

Railway блокирует исходящие SMTP соединения для защиты репутации IP. Resend API использует HTTPS, что позволяет обойти это ограничение.

## Шаги настройки

### 1. Создайте аккаунт на Resend

1. Перейдите на https://resend.com
2. Зарегистрируйтесь (бесплатно)
3. Подтвердите email

### 2. Получите API ключ

1. Войдите в Resend Dashboard
2. Перейдите в **API Keys** (https://resend.com/api-keys)
3. Нажмите **Create API Key**
4. Дайте имя ключу (например: "Railway Production")
5. Скопируйте API ключ (он показывается только один раз!)

### 3. Настройте домен (опционально, но рекомендуется)

Для production рекомендуется использовать свой домен:

1. В Resend Dashboard перейдите в **Domains**
2. Добавьте свой домен
3. Добавьте DNS записи, которые покажет Resend
4. Дождитесь верификации домена

**Без домена:** Можно использовать `onboarding@resend.dev` для тестирования (ограничено)

### 4. Настройте переменные окружения в Railway

1. Откройте Railway Dashboard
2. Перейдите в ваш проект → **Settings** → **Variables**
3. Добавьте переменные:

```
RESEND_API_KEY = ваш_api_ключ_из_resend
RESEND_FROM_EMAIL = ваш_email@ваш_домен.com
```

**Пример:**
```
RESEND_API_KEY = re_AbCdEfGh_123456789
RESEND_FROM_EMAIL = noreply@yourdomain.com
```

### 5. Обновите config.php (уже сделано)

Код уже обновлен для использования Resend API. Просто добавьте переменные окружения в Railway.

## Бесплатный тариф Resend

- **3,000 emails/месяц** бесплатно
- **100 emails/день** бесплатно
- HTTPS API (работает на Railway)
- Быстрая доставка
- Аналитика

## Проверка работы

После настройки:

1. Попробуйте сбросить пароль
2. Проверьте логи Railway - должно быть: "Resend email sent successfully"
3. Проверьте почту - письмо должно прийти

## Альтернативные сервисы

Если Resend не подходит, можно использовать:

1. **SendGrid** - https://sendgrid.com
2. **Mailgun** - https://www.mailgun.com
3. **Postmark** - https://postmarkapp.com
4. **AWS SES** - https://aws.amazon.com/ses/

Все они предоставляют HTTPS API и работают на Railway.

## Troubleshooting

### Email не отправляется

1. Проверьте, что `RESEND_API_KEY` установлен в Railway
2. Проверьте логи Railway на ошибки
3. Убедитесь, что email адрес валидный
4. Проверьте лимиты в Resend Dashboard

### Ошибка "Invalid API key"

- Убедитесь, что скопировали ключ полностью
- Проверьте, что нет лишних пробелов в переменной окружения

### Ошибка "Domain not verified"

- Используйте верифицированный домен
- Или используйте `onboarding@resend.dev` для тестирования

