<?php

return [
    // General
    'not_found' => 'Resource not found.',

    // Auth
    'invalid_credentials' => 'Invalid email or password.',
    'account_inactive' => 'Your account is not active. Contact the administrator.',
    'logged_out' => 'Logged out',

    // Partner
    'password_updated' => 'Password updated',
    'partner_deleted' => 'Partner deleted',
    'deposit_added' => 'Deposit added',
    'deposit_default_desc' => 'Deposit added',
    'outstanding_updated' => 'Outstanding amount updated',
    'limits_reset' => 'Limits reset',
    'permissions_updated' => 'Permissions updated',

    // Category
    'category_status_updated' => 'Category status updated',
    'sync_bad_response' => 'Synchronization failed - unexpected response format',
    'sync_completed' => 'Synchronization completed',
    'sync_error' => 'Synchronization error: :error',

    // Orders
    'insufficient_balance' => 'Insufficient balance. Please top up or contact admin.',
    'order_charge' => 'Charge for order :id',
    'order_refund' => 'Refund for order :id',
    'product_count_sync_queued' => 'Product count sync queued. Running in background.',
    'order_failed' => 'Failed to create order',
    'order_error' => 'Order error: :error',
    'payment_failed' => 'Payment failed. Order has been automatically cancelled.',
    'payment_error_NO_SIGN_UP_ERROR' => 'Alipay account is not linked on 1688. Contact admin to resolve.',
    'payment_error_BALANCE_NOT_ENOUGH' => 'Insufficient Alipay balance on 1688 account.',
    'order_not_found' => 'Order not found or product list is empty.',
    'refund_default_reason' => 'Refund request',
    'cancel_not_allowed' => 'Order cannot be cancelled because it is already paid. Please use the refund endpoint instead.',

    // Plan
    'plan_archived' => 'Plan archived',

    // Token
    'token_created' => 'Token created',
    'token_revoked' => 'Token revoked',
    'token_rotated' => 'Token rotated',
    'tokens_batch_revoked' => ':count tokens revoked',

    // Middleware - Auth
    'admin_only' => 'This action is for administrators only.',
    'partner_only' => 'This action is for partners only.',
    'token_required' => 'API token is required. Authorization: Bearer {token}',
    'token_invalid' => 'Invalid or revoked token.',
    'token_expired' => 'Token has expired.',
    'partner_inactive' => 'Partner account is not active.',
    'ip_not_allowed' => 'IP address (:ip) is not allowed.',
    'no_endpoint_permission' => 'You have no endpoint permissions. Contact the administrator.',
    'endpoint_not_allowed' => 'You do not have permission for this endpoint (:endpoint).',
    'no_category_permission' => 'You have no category permissions. Contact the administrator.',
    'category_not_allowed' => 'You do not have permission for this category (ID: :id).',
    'rpm_exceeded' => 'Per-minute limit (:limit req/min) exceeded.',
    'daily_exceeded' => 'Daily limit (:limit req/day) exceeded.',
    'monthly_exceeded' => 'Monthly limit (:limit req/month) exceeded.',
];
