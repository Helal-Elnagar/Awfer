<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;

class AddDailyReportsPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $routes = [
            'admin.daily-reports.index',
            'admin.daily-reports.data',
        ];

        foreach ($routes as $route) {
            if (!Permission::where('role_id', 1)->where('permission', $route)->exists()) {
                Permission::create([
                    'role_id' => 1, // Admin role
                    'permission' => $route,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $routes = [
            'admin.daily-reports.index',
            'admin.daily-reports.data',
        ];

        Permission::whereIn('permission', $routes)->delete();
    }
}
