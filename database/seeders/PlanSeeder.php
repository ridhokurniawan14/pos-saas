<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $rintis = ['stock_management', 'promo'];
        $tumbuh = array_merge($rintis, ['wholesale', 'realtime', 'multi_outlet', 'offline_mode', 'advanced_report', 'printer', 'custom_receipt']);
        $sultan = array_merge($tumbuh, ['loyalty', 'receivable', 'custom_roles', 'audit_log', 'integrations', 'white_label']);

        $plans = [
            [
                'code' => 'trial',
                'name' => 'Free Trial',
                'monthly' => 0,
                'yearly' => 0,
                'sort' => 0,
                'limits' => ['max_outlets' => 1, 'max_cashiers' => 1, 'max_admins' => 1, 'max_warehouse' => 0, 'max_products' => 50, 'max_orders_per_month' => 100],
                'features' => [],
            ],
            [
                'code' => 'rintis',
                'name' => 'Rintis',
                'monthly' => 99000,
                'yearly' => 990000,
                'sort' => 1,
                'limits' => ['max_outlets' => 1, 'max_cashiers' => 2, 'max_admins' => 1, 'max_warehouse' => 1, 'max_products' => 500, 'max_orders_per_month' => -1],
                'features' => $rintis,
            ],
            [
                'code' => 'tumbuh',
                'name' => 'Tumbuh',
                'monthly' => 249000,
                'yearly' => 2490000,
                'sort' => 2,
                'limits' => ['max_outlets' => 5, 'max_cashiers' => 10, 'max_admins' => 3, 'max_warehouse' => 5, 'max_products' => 5000, 'max_orders_per_month' => -1],
                'features' => $tumbuh,
            ],
            [
                'code' => 'sultan',
                'name' => 'Sultan',
                'monthly' => 499000,
                'yearly' => 4990000,
                'sort' => 3,
                'limits' => ['max_outlets' => -1, 'max_cashiers' => -1, 'max_admins' => 10, 'max_warehouse' => -1, 'max_products' => -1, 'max_orders_per_month' => -1],
                'features' => $sultan,
            ],
        ];

        foreach ($plans as $p) {
            $plan = Plan::updateOrCreate(
                ['code' => $p['code']],
                ['name' => $p['name'], 'price_monthly' => $p['monthly'], 'price_yearly' => $p['yearly'], 'sort_order' => $p['sort']]
            );

            foreach ($p['limits'] as $key => $value) {
                $plan->limits()->updateOrCreate(['key' => $key], ['value' => $value]);
            }

            foreach ($p['features'] as $feature) {
                $plan->features()->updateOrCreate(['feature_key' => $feature], ['enabled' => true]);
            }
        }
    }
}
