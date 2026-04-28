<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('inventory_role')->default('owner')->after('admin');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('circuits', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $userRows = DB::table('users')->select(['id', 'username', 'email'])->orderBy('id')->get();
        $fallbackCompanyId = null;

        foreach ($userRows as $index => $user) {
            $baseName = $user->username ?: explode('@', (string) $user->email)[0];
            $companyName = sprintf('%s-company-%d', $baseName ?: 'company', $user->id);

            $companyId = DB::table('companies')->insertGetId([
                'name' => $companyName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($index === 0) {
                $fallbackCompanyId = $companyId;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'company_id' => $companyId,
                    'inventory_role' => 'owner',
                ]);
        }

        if ($fallbackCompanyId === null) {
            $fallbackCompanyId = DB::table('companies')->insertGetId([
                'name' => 'default-company',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('categories')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        DB::table('items')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
        DB::table('circuits')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);

        DB::statement('DROP INDEX IF EXISTS categories_name_unique');

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
        });

        Schema::table('circuits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->unique('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('inventory_role');
        });
    }
};
