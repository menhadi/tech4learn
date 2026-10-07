<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshNativeFoundationMigrationTest extends TestCase
{
    public function test_fresh_configuration_without_legacy_phone_can_bootstrap_and_resume(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            Schema::create('configurations', function (Blueprint $table) {
                $table->id();
                foreach (['email','logo','favicon'] as $name) $table->string($name)->nullable();
            });
            DB::table('configurations')->insert(['email'=>'synthetic@example.invalid']);
            $migration = require database_path('migrations/2026_06_24_222133_create_saas_foundation_tables.php');
            $migration->up();
            $migration->up();
            $this->assertSame(1, DB::table('organizations')->count());
            $this->assertSame(1, DB::table('saas_plans')->count());
            $this->assertNull(DB::table('organizations')->value('phone'));
            $this->assertSame('synthetic@example.invalid', DB::table('organizations')->value('email'));
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }
}
