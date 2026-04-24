<?php

return [
    // General
    'not_found' => 'Ресурс не найден.',

    // Auth
    'invalid_credentials' => 'Неверный email или пароль.',
    'account_inactive' => 'Ваш аккаунт неактивен. Свяжитесь с администратором.',
    'logged_out' => 'Выход выполнен',

    // Partner
    'password_updated' => 'Пароль обновлён',
    'partner_deleted' => 'Партнёр удалён',
    'deposit_added' => 'Депозит добавлен',
    'deposit_default_desc' => 'Депозит добавлен',
    'outstanding_updated' => 'Задолженность обновлена',
    'limits_reset' => 'Лимиты сброшены',
    'permissions_updated' => 'Разрешения обновлены',

    // Category
    'category_status_updated' => 'Статус категории обновлён',
    'sync_bad_response' => 'Синхронизация не удалась — неожиданный формат ответа',
    'sync_completed' => 'Синхронизация завершена',
    'sync_error' => 'Ошибка синхронизации: :error',

    // Orders
    'insufficient_balance' => 'Недостаточный баланс. Пополните депозит или свяжитесь с администратором.',
    'order_charge' => 'Оплата заказа :id',
    'order_refund' => 'Возврат по заказу :id',
    'product_count_sync_queued' => 'Синхронизация количества товаров запущена в фоне.',
    'order_failed' => 'Не удалось создать заказ',
    'order_error' => 'Ошибка заказа: :error',
    'payment_failed' => 'Оплата не прошла. Заказ автоматически отменён.',
    'payment_error_NO_SIGN_UP_ERROR' => 'Аккаунт Alipay не привязан к 1688. Свяжитесь с администратором.',
    'payment_error_BALANCE_NOT_ENOUGH' => 'Недостаточно средств на Alipay аккаунте 1688.',
    'order_not_found' => 'Заказ не найден или список товаров пуст.',
    'refund_default_reason' => 'Запрос на возврат',
    'cancel_not_allowed' => 'Заказ не может быть отменён, так как он уже оплачен. Используйте endpoint возврата (refund).',

    // Plan
    'plan_archived' => 'План архивирован',

    // Token
    'token_created' => 'Токен создан',
    'token_revoked' => 'Токен отозван',
    'token_rotated' => 'Токен обновлён',
    'tokens_batch_revoked' => ':count токенов отозвано',

    // Middleware - Auth
    'admin_only' => 'Это действие доступно только администраторам.',
    'partner_only' => 'Это действие доступно только партнёрам.',
    'token_required' => 'Требуется API токен. Authorization: Bearer {token}',
    'token_invalid' => 'Недействительный или отозванный токен.',
    'token_expired' => 'Срок действия токена истёк.',
    'partner_inactive' => 'Аккаунт партнёра неактивен.',
    'ip_not_allowed' => 'IP-адрес (:ip) не разрешён.',
    'no_endpoint_permission' => 'У вас нет разрешений на эндпоинты. Свяжитесь с администратором.',
    'endpoint_not_allowed' => 'У вас нет разрешения на этот эндпоинт (:endpoint).',
    'no_category_permission' => 'У вас нет разрешений на категории. Свяжитесь с администратором.',
    'category_not_allowed' => 'У вас нет разрешения на эту категорию (ID: :id).',
    'rpm_exceeded' => 'Превышен лимит в минуту (:limit запр/мин).',
    'daily_exceeded' => 'Превышен дневной лимит (:limit запр/день).',
    'monthly_exceeded' => 'Превышен месячный лимит (:limit запр/мес).',
];
