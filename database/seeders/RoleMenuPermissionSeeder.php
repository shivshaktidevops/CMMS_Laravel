<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Existing database role values
        $roles = [
            'admin',
            'operator',
            'maintenance',
            'management',
        ];

        // Reports menus
        $menus = [
            [
                'menu_key' => 'breakdown_report',
                'menu_name' => 'Breakdown Report',
            ],
            [
                'menu_key' => 'job_report',
                'menu_name' => 'Job Report',
            ],
            [
                'menu_key' => 'monitoring_report',
                'menu_name' => 'Monitoring Report',
            ],
            [
                'menu_key' => 'equipment_history',
                'menu_name' => 'Equipment History',
            ],
            [
                'menu_key' => 'shift_duty_report',
                'menu_name' => 'Shift Duty Report',
            ],
            [
                'menu_key' => 'general_shift_jobs',
                'menu_name' => 'General Shift Jobs',
            ],
        ];

        foreach ($roles as $role) {

            foreach ($menus as $menu) {

                DB::table('role_menu_permissions')
                    ->updateOrInsert(
                        [
                            'role' => $role,
                            'menu_key' => $menu['menu_key'],
                        ],
                        [
                            'menu_name' => $menu['menu_name'],
                            'updated_at' => now(),
                        ]
                    );
            }
        }
    }
}
