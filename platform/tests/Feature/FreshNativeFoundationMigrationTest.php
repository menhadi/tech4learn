<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshNativeFoundationMigrationTest extends TestCase
{
    public function test_platform_flag_migration_never_promotes_accounts_by_email(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->string('status');
            });
            // This value triggered the original upstream migration's promotion.
            DB::table('users')->insert([
                ['id'=>1, 'email'=>'menhadi@gmail.com', 'status'=>'Active'],
                ['id'=>2, 'email'=>'synthetic@example.invalid', 'status'=>'Active'],
            ]);
            $migration = require database_path('migrations/2026_07_04_000003_add_platform_admin_flag_to_users_table.php');
            $migration->up();
            $this->assertSame(0, DB::table('users')->where('is_platform_admin', true)->count());
            DB::table('users')->where('id', 2)->update(['is_platform_admin'=>true]);
            $migration->up();
            $this->assertSame(1, DB::table('users')->where('is_platform_admin', true)->count());
            $this->assertEquals(0, DB::table('users')->where('id', 1)->value('is_platform_admin'));
            $this->assertEquals(1, DB::table('users')->where('id', 2)->value('is_platform_admin'));
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }

    public function test_admission_schema_repairs_only_empty_partial_resources(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            $migration = require database_path('migrations/2026_08_22_000001_create_admission_prediction_foundation.php');
            $migration->up();
            foreach (['prediction_models','cutoff_observations','rank_observations','dataset_resources','dataset_versions','official_resources'] as $suffix) {
                Schema::drop('admission_'.$suffix);
            }
            Schema::create('admission_official_resources', fn (Blueprint $table) => $table->id());
            $migration->up();
            $this->assertCount(4, Schema::getForeignKeys('admission_official_resources'));
            $this->assertTrue(Schema::hasTable('admission_prediction_models'));
            $migration->up();
            $this->assertCount(4, Schema::getForeignKeys('admission_official_resources'));
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }

    public function test_admission_partial_table_with_records_is_never_dropped(): void
    {
        config(['database.default'=>'fresh_native', 'database.connections.fresh_native'=>[
            'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'', 'foreign_key_constraints'=>true,
        ]]);
        try {
            Schema::create('admission_official_resources', fn (Blueprint $table) => $table->id());
            DB::table('admission_official_resources')->insert(['id'=>1]);
            $migration = require database_path('migrations/2026_08_22_000001_create_admission_prediction_foundation.php');
            try {
                $migration->up();
                $this->fail('A populated partial table must stop migration.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('manual review', $error->getMessage());
            }
            $this->assertSame(1, DB::table('admission_official_resources')->count());
        } finally {
            DB::purge('fresh_native');
            config(['database.default'=>'sqlite']);
        }
    }

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
