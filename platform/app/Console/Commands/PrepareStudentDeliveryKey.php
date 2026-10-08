<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepareStudentDeliveryKey extends Command
{
    protected $signature='foundation:student-signing-key {--confirm-native-installation}';
    protected $description='Prepare an installation-only student delivery key; never rotate an existing key';

    public function handle(): int
    {
        if (!$this->option('confirm-native-installation')) {
            $this->error('Confirm this Tech4Learn native installation before preparing its key.');
            return self::FAILURE;
        }
        $directory=storage_path('app/private');$path=$directory.'/student-delivery-signing.pem';
        $public=$directory.'/student-delivery-signing-public.pem';$handle=null;
        try {
            if (is_link(storage_path('app')) || is_link($directory) || is_link($path) || is_link($public))throw new \RuntimeException;
            if (!is_dir($directory) && !mkdir($directory,0700,true))throw new \RuntimeException;
            if (file_exists($path)) {
                if (!is_file($path) || filesize($path)>8192 || (PHP_OS_FAMILY!=='Windows' && (fileperms($path)&0077)!==0))throw new \RuntimeException;
                $key=openssl_pkey_get_private(file_get_contents($path));
            } else {
                $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
                if (!$key || !openssl_pkey_export($key,$pem))throw new \RuntimeException;
                $mask=umask(0077);
                try {$handle=fopen($path,'x');} finally {umask($mask);}
                if (!$handle || !chmod($path,0600) || fwrite($handle,$pem)!==strlen($pem) || !fflush($handle))throw new \RuntimeException;
                fclose($handle);$handle=null;
            }
            $details=$key?openssl_pkey_get_details($key):false;
            if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048)throw new \RuntimeException;
            if (file_exists($public)) {
                if (!is_file($public) || filesize($public)>8192 || file_get_contents($public)!==$details['key'])throw new \RuntimeException;
            } else {
                $mask=umask(0077);
                try {$handle=fopen($public,'x');} finally {umask($mask);}
                if (!$handle || !chmod($public,0600) || fwrite($handle,$details['key'])!==strlen($details['key']) || !fflush($handle))throw new \RuntimeException;
                fclose($handle);$handle=null;
            }
            $this->info('Installation signing key prepared; existing key preserved.');
            $this->info('Review and register the public key file: '.$public);
            return self::SUCCESS;
        } catch (\Throwable $error) {
            if (is_resource($handle))fclose($handle);
            // A failed partial write is retained for operator review, never silently replaced.
            $this->error('Signing key preparation blocked; inspect private storage and key state. No key was rotated.');
            return self::FAILURE;
        }
    }
}
