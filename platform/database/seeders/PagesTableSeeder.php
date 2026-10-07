<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Page;

class PagesTableSeeder extends Seeder
{
    public function run()
    {
        Page::create([
            'page_name' => 'Dashboard',
            'action_name' => 'dashboard',
            'icon' => 'mdi mdi-speedometer',
            'parent_id' => null,
            'ordering' => 1,
        ]);

        Page::create([
            'page_name' => 'Groups',
            'action_name' => 'groups.index',
            'icon' => 'mdi mdi-account-group',
            'parent_id' => null,
            'ordering' => 2,
        ]);

        Page::create([
            'page_name' => 'User Groups',
            'action_name' => 'ugroups.index',
            'icon' => 'mdi mdi-account-group',
            'parent_id' => null,
            'ordering' => 3,
        ]);
    }
}