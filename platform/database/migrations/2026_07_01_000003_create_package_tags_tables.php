<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('package_tag_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_tag_id')->constrained('package_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'package_tag_id']);
        });

        $defaultTags = [
            'PYP',
            'Mock Test',
            'Year Wise',
            'Subject Wise',
            'Topic Wise',
            'Full Length',
            'Chapter Wise',
            'Free Practice',
            'Scholarship',
            'Popular',
        ];

        foreach ($defaultTags as $tag) {
            DB::table('package_tags')->updateOrInsert(
                ['organization_id' => null, 'slug' => Str::slug($tag)],
                [
                    'name' => $tag,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $tagIds = DB::table('package_tags')
            ->whereNull('organization_id')
            ->pluck('id', 'slug');

        DB::table('packages')
            ->select('id', 'name', 'package_type')
            ->chunkById(200, function ($packages) use ($tagIds) {
                foreach ($packages as $package) {
                    $name = strtolower((string) $package->name);
                    $slugs = [];

                    if (($package->package_type ?? null) === 'free') {
                        $slugs[] = 'free-practice';
                    }

                    if (str_contains($name, 'previous') || str_contains($name, 'pyp')) {
                        $slugs[] = 'pyp';
                    }

                    if (str_contains($name, 'mock')) {
                        $slugs[] = 'mock-test';
                    }

                    if (str_contains($name, 'full length') || str_contains($name, 'full-length')) {
                        $slugs[] = 'full-length';
                    }

                    if (str_contains($name, 'scholarship')) {
                        $slugs[] = 'scholarship';
                    }

                    foreach (array_unique($slugs) as $slug) {
                        if (! isset($tagIds[$slug])) {
                            continue;
                        }

                        DB::table('package_tag_package')->insertOrIgnore([
                            'package_id' => $package->id,
                            'package_tag_id' => $tagIds[$slug],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_tag_package');
        Schema::dropIfExists('package_tags');
    }
};
