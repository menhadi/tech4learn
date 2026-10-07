<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Hash};

class ProvisionFoundationAdministrator extends Command
{
    protected $signature = 'foundation:platform-admin {canonical-user-id} {--confirm-reviewed-canonical-superadmin}';
    protected $description = 'Explicit initial native administrator; pair with the canonical API mapping command';

    public function handle(): int
    {
        $canonical = (string)$this->argument('canonical-user-id');
        if (!$this->option('confirm-reviewed-canonical-superadmin')
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $canonical)
            || !config('attendance.api_url')) {
            $this->error('A reviewed canonical superadmin UUID and configured API bridge are required.');
            return self::FAILURE;
        }
        try {
            $ids = DB::transaction(function () use ($canonical) {
                // Lock the durable primary row, serializing first-account provisioning.
                $primary = DB::table('organizations')->where('status','active')
                    ->where('settings->is_primary_platform',true)->lockForUpdate()->get();
                if ($primary->count() !== 1) throw new \RuntimeException;
                $org = $primary->first();
                $existing = DB::table('foundation_platform_administrators')->where('organization_id',$org->id)->first();
                if ($existing) {
                    if ($existing->canonical_user_id !== $canonical
                        || !DB::table('users')->where('id',$existing->user_id)->where('status','Active')
                            ->where('deleted',false)->where('is_platform_admin',true)->exists()) throw new \RuntimeException;
                    return [$org->id,$existing->user_id];
                }
                // Never adopt, promote or overwrite an existing native account.
                if (DB::table('users')->exists()) throw new \RuntimeException;
                $now = now();
                $user = DB::table('users')->insertGetId([
                    'name'=>'Tech4Learn platform administrator', 'username'=>'foundation-'.$canonical,
                    'email'=>$canonical.'@identity.invalid', 'password'=>Hash::make(bin2hex(random_bytes(48))),
                    'status'=>'Active','deleted'=>false,'is_platform_admin'=>true,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
                DB::table('foundation_platform_administrators')->insert([
                    'organization_id'=>$org->id,'canonical_user_id'=>$canonical,'user_id'=>$user,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
                return [$org->id,$user];
            });
            $this->info('Native primary organisation ID: '.$ids[0].'; native administrator ID: '.$ids[1].'.');
            $this->info('Complete the explicit API mapping before sign-in; no canonical password was copied.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Provisioning blocked: verify schema, primary realm and existing identity state. No repair was attempted.');
            return self::FAILURE;
        }
    }
}
