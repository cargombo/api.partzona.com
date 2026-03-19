<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Partner;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Plans
        Plan::create(['name' => 'free', 'display_name' => 'Free', 'rpm_limit' => 10, 'daily_limit' => 500, 'monthly_limit' => 5000, 'max_concurrent' => 5, 'max_categories' => 3, 'sandbox' => true, 'ip_whitelist' => false, 'webhook' => false, 'sla' => '—', 'price_monthly' => 0]);
        Plan::create(['name' => 'basic', 'display_name' => 'Basic', 'rpm_limit' => 60, 'daily_limit' => 5000, 'monthly_limit' => 100000, 'max_concurrent' => 5, 'max_categories' => 10, 'sandbox' => true, 'ip_whitelist' => true, 'webhook' => false, 'sla' => 'Email', 'price_monthly' => 49]);
        Plan::create(['name' => 'pro', 'display_name' => 'Pro', 'rpm_limit' => 200, 'daily_limit' => 50000, 'monthly_limit' => 1000000, 'max_concurrent' => 10, 'max_categories' => null, 'sandbox' => true, 'ip_whitelist' => true, 'webhook' => true, 'sla' => 'Prioritet', 'price_monthly' => 199]);
        Plan::create(['name' => 'enterprise', 'display_name' => 'Enterprise', 'rpm_limit' => 0, 'daily_limit' => 0, 'monthly_limit' => 0, 'max_concurrent' => 50, 'max_categories' => null, 'sandbox' => true, 'ip_whitelist' => true, 'webhook' => true, 'sla' => 'Dedicated', 'price_monthly' => 499]);

        // Admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@partzona.com',
            'password' => Hash::make('admin123'),
        ]);

        // Pro plan partner
        Partner::create([
            'company_name' => 'TechBazar MMC',
            'contact_name' => 'Elvin Məmmədov',
            'email' => 'elvin@techbazar.az',
            'password' => Hash::make('partner123'),
            'plain_password' => 'partner123',
            'phone' => '+994 50 555 12 34',
            'country' => 'Azərbaycan',
            'industry' => 'E-ticarət',
            'website' => 'https://techbazar.az',
            'status' => 'active',
            'plan_id' => 3,
            'payment_model' => 'deposit',
            'deposit_balance' => 5000,
            'rpm_limit' => 200,
            'daily_limit' => 50000,
            'monthly_limit' => 1000000,
            'max_concurrent' => 10,
            'allow_negative' => true,
            'outstanding_balance' => 1200,
            'approved_at' => now(),
        ]);

        // Enterprise plan partner (debit)
        Partner::create([
            'company_name' => 'AzImport LLC',
            'contact_name' => 'Nigar Həsənova',
            'email' => 'nigar@azimport.com',
            'password' => Hash::make('partner123'),
            'plain_password' => 'partner123',
            'phone' => '+994 55 444 56 78',
            'country' => 'Azərbaycan',
            'industry' => 'İdxal-İxrac',
            'website' => 'https://azimport.com',
            'status' => 'active',
            'plan_id' => 4,
            'payment_model' => 'debit',
            'debit_limit' => 25000,
            'debit_used' => 18740,
            'rpm_limit' => 0,
            'daily_limit' => 0,
            'monthly_limit' => 0,
            'max_concurrent' => 50,
            'allow_negative' => true,
            'outstanding_balance' => 18740,
            'approved_at' => now(),
        ]);

        // Basic plan partner
        Partner::create([
            'company_name' => 'ShopTR A.Ş.',
            'contact_name' => 'Ahmet Yılmaz',
            'email' => 'ahmet@shoptr.com.tr',
            'password' => Hash::make('partner123'),
            'plain_password' => 'partner123',
            'phone' => '+90 532 111 22 33',
            'country' => 'Türkiyə',
            'industry' => 'Topdan Satış',
            'website' => 'https://shoptr.com.tr',
            'status' => 'active',
            'plan_id' => 2,
            'payment_model' => 'deposit',
            'deposit_balance' => 800,
            'rpm_limit' => 60,
            'daily_limit' => 5000,
            'monthly_limit' => 100000,
            'max_concurrent' => 5,
            'allow_negative' => false,
            'outstanding_balance' => 350,
            'approved_at' => now(),
        ]);

        // Basic plan partner
        Partner::create([
            'company_name' => 'Bakı Elektronik',
            'contact_name' => 'Rəşad Quliyev',
            'email' => 'rashad@bakuelektronik.az',
            'password' => Hash::make('partner123'),
            'plain_password' => 'partner123',
            'phone' => '+994 70 222 33 44',
            'country' => 'Azərbaycan',
            'industry' => 'Elektronika',
            'website' => 'https://bakuelektronik.az',
            'status' => 'active',
            'plan_id' => 2,
            'payment_model' => 'deposit',
            'deposit_balance' => 1500,
            'rpm_limit' => 60,
            'daily_limit' => 5000,
            'monthly_limit' => 100000,
            'max_concurrent' => 5,
            'allow_negative' => false,
            'outstanding_balance' => 200,
            'approved_at' => now(),
        ]);
    }
}
