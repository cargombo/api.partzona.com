<?php

return [
    // General
    'not_found' => 'Resurs tapılmadı.',

    // Auth
    'invalid_credentials' => 'Email və ya şifrə yanlışdır.',
    'account_inactive' => 'Hesabınız aktiv deyil. Admin ilə əlaqə saxlayın.',
    'logged_out' => 'Çıxış edildi',

    // Partner
    'password_updated' => 'Şifrə yeniləndi',
    'partner_deleted' => 'Partner silindi',
    'deposit_added' => 'Depozit əlavə edildi',
    'deposit_default_desc' => 'Depozit əlavə edildi',
    'outstanding_updated' => 'Ödəniləcək məbləğ yeniləndi',
    'limits_reset' => 'Limitlər sıfırlandı',
    'permissions_updated' => 'İcazələr yeniləndi',

    // Category
    'category_status_updated' => 'Kateqoriya statusu yeniləndi',
    'sync_bad_response' => 'Sinxronizasiya uğursuz oldu - cavab formatı gözlənilmir',
    'sync_completed' => 'Sinxronizasiya tamamlandı',
    'sync_error' => 'Sinxronizasiya xətası: :error',

    // Orders
    'insufficient_balance' => 'Balans kifayət etmir. Depozit artırın və ya admin ilə əlaqə saxlayın.',
    'order_charge' => ':id sifarişi üçün ödəniş',
    'order_failed' => 'Sifariş yaradıla bilmədi',
    'order_error' => 'Sifariş xətası: :error',
    'payment_failed' => 'Ödəniş uğursuz oldu. Sifariş avtomatik ləğv edildi.',
    'payment_error_NO_SIGN_UP_ERROR' => '1688 hesabında Alipay bağlı deyil. Həll üçün admin ilə əlaqə saxlayın.',
    'payment_error_BALANCE_NOT_ENOUGH' => '1688 hesabında Alipay balansı kifayət etmir.',

    // Plan
    'plan_archived' => 'Plan arxivləndi',

    // Token
    'token_created' => 'Token yaradıldı',
    'token_revoked' => 'Token ləğv edildi',
    'token_rotated' => 'Token yeniləndi',
    'tokens_batch_revoked' => ':count token ləğv edildi',

    // Middleware - Auth
    'admin_only' => 'Bu əməliyyat yalnız admin üçündür.',
    'partner_only' => 'Bu əməliyyat yalnız partner üçündür.',
    'token_required' => 'API token tələb olunur. Authorization: Bearer {token}',
    'token_invalid' => 'Etibarsız və ya ləğv edilmiş token.',
    'token_expired' => 'Token müddəti bitib.',
    'partner_inactive' => 'Partner hesabı aktiv deyil.',
    'ip_not_allowed' => 'IP ünvanı (:ip) icazəli deyil.',
    'no_endpoint_permission' => 'Heç bir endpoint icazəniz yoxdur. Admin ilə əlaqə saxlayın.',
    'endpoint_not_allowed' => 'Bu endpoint (:endpoint) üçün icazəniz yoxdur.',
    'no_category_permission' => 'Heç bir kateqoriya icazəniz yoxdur. Admin ilə əlaqə saxlayın.',
    'category_not_allowed' => 'Bu kateqoriya (ID: :id) üçün icazəniz yoxdur.',
    'rpm_exceeded' => 'Dəqiqəlik limit (:limit req/min) aşılıb.',
    'daily_exceeded' => 'Gündəlik limit (:limit req/gün) aşılıb.',
    'monthly_exceeded' => 'Aylıq limit (:limit req/ay) aşılıb.',
];
