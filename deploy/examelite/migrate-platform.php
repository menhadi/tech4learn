<?php
// Explicit user-run migration. The web process never creates schema.
require '/home/examelite/public_html/vendor/autoload.php';
$app = require '/home/examelite/public_html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
if (!Schema::hasTable('tech4learn_student_links')) {
    Schema::create('tech4learn_student_links', function (Blueprint $t) {
        $t->uuid('tech4learn_organisation');
        $t->uuid('learner');
        $t->unsignedBigInteger('organization_id');
        $t->unsignedBigInteger('student_id')->nullable();
        $t->primary(['tech4learn_organisation','learner']);
        $t->unique(['organization_id','student_id']);
    });
}
echo "ExamElite identity mapping table is ready.\n";
