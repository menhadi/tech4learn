<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshNativeFoundationMigrationTest extends TestCase
{
    public function test_official_monitor_schema_can_resume_without_resetting_existing_sources(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            foreach (['organizations','users','languages','exams','source_exam_imports'] as $name) {
                Schema::create($name, fn (Blueprint $table) => $table->id());
            }
            $migration = require database_path('migrations/2026_08_17_000001_create_official_exam_monitor_tables.php');
            $migration->up();
            DB::table('organizations')->insert(['id'=>1]);
            DB::table('official_exam_sources')->insert(['organization_id'=>1, 'website_name'=>'Synthetic',
                'name'=>'Synthetic source', 'source_url'=>'https://example.invalid', 'discovery_settings'=>'{}', 'exam_defaults'=>'{}']);
            Schema::drop('official_exam_discoveries');
            $migration->up();
            $this->assertTrue(Schema::hasTable('official_exam_discoveries'));
            $this->assertSame(1, DB::table('official_exam_sources')->count());
            $this->assertSame('Synthetic source', DB::table('official_exam_sources')->value('name'));
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }

    public function test_legacy_students_gain_no_automatic_email_verification(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            (require database_path('migrations/2024_12_10_103101_create_students_table.php'))->up();
            DB::table('students')->insert(['name'=>'Synthetic learner', 'email'=>'synthetic@example.invalid',
                'phone'=>'synthetic-test-phone', 'password'=>'unused-test-value', 'address'=>'Synthetic', 'status'=>'Pending']);
            $migration = require database_path('migrations/2026_07_31_000000_add_student_email_verification_timestamp.php');
            $migration->up();
            $migration->up();
            $this->assertTrue(Schema::hasColumn('students', 'email_verified_at'));
            $this->assertNull(DB::table('students')->value('email_verified_at'));
            $this->assertSame('Pending', DB::table('students')->value('status'));
            $this->assertSame(1, DB::table('students')->count());
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }

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
