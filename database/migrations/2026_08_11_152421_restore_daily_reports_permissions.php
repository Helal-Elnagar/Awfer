<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
     */
    public function down(): void
    {
        $routes = [
            'admin.daily-reports.index',
            'admin.daily-reports.data',
        ];

        Permission::where('role_id', 1)->whereIn('permission', $routes)->delete();
    }
};
