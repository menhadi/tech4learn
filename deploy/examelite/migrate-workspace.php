<?php
require '/home/examelite/public_html/vendor/autoload.php';
$app=require '/home/examelite/public_html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
if(!Schema::hasTable('tech4learn_workspaces'))Schema::create('tech4learn_workspaces',function(Blueprint $t){
 $t->uuid('id')->primary();$t->unsignedBigInteger('source_organization_id');$t->unsignedBigInteger('organization_id')->nullable()->unique();$t->integer('revision')->default(0);$t->text('restrictions');
});
if(!Schema::hasTable('tech4learn_workspace_users'))Schema::create('tech4learn_workspace_users',function(Blueprint $t){
 $t->uuid('workspace_id');$t->uuid('local_id');$t->string('kind',10);$t->unsignedBigInteger('external_id');$t->primary(['workspace_id','local_id','kind'],'t4l_ws_user_pk');$t->unique(['kind','external_id'],'t4l_ws_user_external');
});
if(!Schema::hasTable('tech4learn_workspace_tickets'))Schema::create('tech4learn_workspace_tickets',function(Blueprint $t){
 $t->string('hash',64)->primary();$t->uuid('workspace_id');$t->string('kind',10);$t->unsignedBigInteger('external_id');$t->string('name');$t->string('entry');$t->timestamp('expires_at');$t->timestamp('used_at')->nullable();
});
if(!Schema::hasTable('tech4learn_workspace_copies'))Schema::create('tech4learn_workspace_copies',function(Blueprint $t){
 $t->uuid('workspace_id');$t->string('kind',15);$t->unsignedBigInteger('source_id');$t->unsignedBigInteger('target_id');$t->primary(['workspace_id','kind','source_id'],'t4l_ws_copy_pk');
});
if(!Schema::hasTable('tech4learn_content_transfers'))Schema::create('tech4learn_content_transfers',function(Blueprint $t){
 $t->uuid('workspace_id');$t->uuid('request_id');$t->uuid('actor_id');$t->string('direction',5);$t->string('fingerprint',64);$t->text('result');$t->timestamp('created_at');$t->primary(['workspace_id','request_id'],'t4l_transfer_pk');
});
if(!Schema::hasTable('tech4learn_authoring_requests'))Schema::create('tech4learn_authoring_requests',function(Blueprint $t){
 $t->uuid('workspace_id');$t->uuid('request_id');$t->string('fingerprint',64);$t->text('result');$t->timestamp('created_at');$t->primary(['workspace_id','request_id'],'t4l_authoring_pk');
});

echo "Native exam workspace tables ready.\n";
