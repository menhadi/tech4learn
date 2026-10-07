<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Configuration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\QueryException;

class InstallationController extends Controller
{
    public function iwelcome()
    {
        if (File::exists(base_path('.env')) && env('APP_INSTALLED') == 'true') { 
             return redirect('/login')->with('status', 'Application is already installed.');
        }
        return view('installer.iwelcome');
    }

    public function purchaseCode()
    {
        if (File::exists(base_path('.env')) && env('APP_INSTALLED') == 'true') { 
             return redirect('/login');
        }
        return view('installer.purchase_code');
    }

    // ✅✅✅ JASOOS MODE ON 🕵️‍♂️ (License Verify) ✅✅✅
    public function purchaseCodePost(Request $request)
    {
        $request->validate([
            'purchase_code' => 'required|string',
        ]);

        $licenseKey = $request->purchase_code;
        $domain = $request->getHttpHost(); 
        
        // Aapka License Server URL
        $licenseServer = 'https://updates.examcentrelive.com'; 

        try {
            // 1. Server API Call
            $response = Http::post("$licenseServer/api/activate", [
                'purchase_code' => $licenseKey,
                'domain' => $domain,
            ]);

            $body = $response->json();

            // 2. Check Response
            if ($response->successful() && isset($body['status']) && $body['status'] == true) {
                
                // ✅ SUCCESS
                Session::put('validated_license_key', $licenseKey);
                Session::put('license_server_url', $licenseServer);

                return redirect()->route('installer.database')->with('status', 'License verified successfully! Welcome ' . ($body['buyer'] ?? 'User'));
            } else {
                // ❌ FAIL: Debugging Info Show karo
                $statusCode = $response->status();
                $rawBody = substr(strip_tags($response->body()), 0, 400); 
                $serverMessage = $body['message'] ?? 'Unknown Error';
                $fullDebugError = "SERVER ERROR ($statusCode): $serverMessage | Raw: $rawBody";

                return back()->withErrors(['purchase_code' => $fullDebugError])->withInput();
            }

        } catch (\Exception $e) {
            // ❌ CONNECTION ERROR
            return back()->withErrors(['connection' => 'CONNECTION FAILED: ' . $e->getMessage()])->withInput();
        }
    }

    // ✅✅✅ FIXED DATABASE FUNCTION ✅✅✅
    public function database(Request $request)
    {
        if (File::exists(base_path('.env')) && env('APP_INSTALLED') == 'true') {
             return redirect('/login')->with('status', 'Application is already installed.');
        }

        if ($request->isMethod('post')) {
            try {
                $request->validate([
                    'app_name' => 'required|string',
                    'app_url' => 'required|url',
                    'db_host' => 'required|string',
                    'db_port' => 'required|numeric',
                    'db_database' => 'required|string',
                    'db_username' => 'required|string',
                    'db_password' => 'nullable|string',
                ]);

                if (!File::exists(public_path('templates/examframe.sql'))) {
                    return back()->withErrors(['sql_file' => 'examframe.sql file does not exist.']);
                }
                
                $envPath = base_path('.env');
                if (!File::exists($envPath)) {
                    return back()->withErrors(['env_file' => '.env file not found. Please re-upload the package.']);
                }

                $appName = preg_replace('/[^a-zA-Z]/', '', $request->app_name);
                $dbPassword = $request->db_password ? $request->db_password : ''; 

                Artisan::call('key:generate', ['--force' => true]);

                // Retrieve License Key from Session
                $licenseKey = Session::get('validated_license_key', '');
                $licenseServer = Session::get('license_server_url', 'https://updates.examcentrelive.com');

                $dbConfig = [
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $request->db_host,
                    'DB_PORT' => $request->db_port,
                    'DB_DATABASE' => $request->db_database,
                    'DB_USERNAME' => $request->db_username,
                    'DB_PASSWORD' => $dbPassword,
                    'APP_NAME' => '"' . $appName . '"',
                    'APP_URL' => $request->app_url,
                    "DEMO_MODE" => "false",
                    "APP_INSTALLED" => "false", 
                    'LICENSE_SERVER_URL' => $licenseServer,
                    'LICENSE_KEY' => $licenseKey,
                ];

                foreach ($dbConfig as $key => $value) {
                    $this->setEnvValue($key, $value);
                }

                // 🛑 FIXED: Removed 'config:cache' to prevent caching empty/old credentials
                Artisan::call('config:clear');
                
                // ⚡ FORCE RUNTIME CONFIG (Important for immediate connection)
                config(['database.connections.mysql.host' => $request->db_host]);
                config(['database.connections.mysql.port' => $request->db_port]);
                config(['database.connections.mysql.database' => $request->db_database]);
                config(['database.connections.mysql.username' => $request->db_username]);
                config(['database.connections.mysql.password' => $dbPassword]);
                
                // 🔄 Reconnect with new credentials
                DB::purge('mysql');
                DB::reconnect('mysql');

                if (!$this->check_database_connection()) {
                    $this->setEnvValue('APP_INSTALLED', 'false');
                    return back()->withErrors(['db_connection' => 'Could not connect to the database. Please check your username/password.'])->withInput();
                }

                // Run Migrations / Import SQL
                Artisan::call('db:wipe', ['--force' => true]);
                $sqlPath = public_path('templates/examframe.sql');
                DB::unprepared(file_get_contents($sqlPath));

                $this->setEnvValue('APP_INSTALLED', 'true');

                Artisan::call('config:clear');
                return redirect()->route('installer.information');
                
            } catch (\Exception $e) {
                $this->setEnvValue('APP_INSTALLED', 'false');
                Log::error('Installer Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                return back()->withErrors(['unexpected' => 'An error occurred: ' . $e->getMessage()])->withInput();
            }
        }

        return view('installer.database');
    }

    function check_database_connection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error('DB Connection Check Failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function setEnvValue($key, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            $env = file_get_contents($path);
            
            if (strpos($value, ' ') !== false && strpos($value, '"') !== 0) {
                $value = '"' . $value . '"';
            }

            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
    
            if (preg_match($pattern, $env)) {
                $env = preg_replace($pattern, $replacement, $env);
            } else {
                $env .= "\n" . $replacement;
            }
            
            file_put_contents($path, $env);
        }
    }

    public function checkSymlink()
    {
        return response()->json(['symlink_exists' => function_exists('symlink')]);
    }

    public function information(Request $request)
    {
        if ($request->isMethod('post')) {
            $data = $request->all();

            $validator = Validator::make($data, [
                'organization_name' => ['required', 'string', 'max:255'],
                'domain_name' => ['required', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $mainRoutesPath = base_path('routes/main_routes.php');
            if (!File::exists($mainRoutesPath)) {
                return back()->withErrors(['routes_file' => 'main_routes.php file does not exist.']);
            }
            
            try {
                $admin = User::where('username', 'admin')->first();

                if ($admin) {
                    $admin->update([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                    ]);
                } else {
                    $admin = User::create([
                        'name' => $data['name'],
                        'username' => $data['name'],
                        'ugroup_id' => '0',
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                        'status' => 'Active',
                    ]);
                }

                $configuration = Configuration::first() ?? new Configuration();
                $configuration->name = $data['organization_name'];
                $configuration->domain_name = $data['domain_name'];
                $configuration->email = $data['email'];
                $configuration->save();

                $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

                Permission::firstOrCreate(['name' => 'system.update', 'guard_name' => 'web']);
                Permission::firstOrCreate(['name' => 'manage groups', 'guard_name' => 'web']);
                
                $admin->assignRole($adminRole);
                $adminRole->givePermissionTo('system.update'); 
                $adminRole->givePermissionTo('manage groups'); 

                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

                $webRoutesPath = base_path('routes/web.php');
                if (File::exists($mainRoutesPath)) {
                    File::copy($mainRoutesPath, $webRoutesPath);
                    File::delete($mainRoutesPath);
                }

                Artisan::call('optimize:clear');
                Artisan::call('config:clear');
                Artisan::call('cache:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                
                try {
                    Artisan::call('storage:link');
                } catch (\Exception $e) {
                    Log::warning('Installer could not create symlink: ' . $e->getMessage());
                }

                return redirect('/login');
            } catch (\Exception $e) {
                Log::error('Installer Info Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                return back()->withErrors(['installation_error' => 'An error occurred during installation. Please try again. ' . $e->getMessage()])->withInput();
            }
        }

        return view('installer.information');
    }
}