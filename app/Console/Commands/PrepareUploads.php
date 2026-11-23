<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrepareUploads extends Command
{
    protected $signature = 'marketplace:prepare-uploads';

    protected $description = 'Create upload directories and block script execution inside them';

    public function handle(): int
    {
        $disk = Storage::disk('uploads');
        $root = config('filesystems.disks.uploads.root');

        foreach (config('marketplace.upload_dirs') as $dir) {
            $disk->makeDirectory($dir);
        }

        $htaccess = "# Uploaded files must never execute\n"
            ."<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n"
            ."Options -ExecCGI\n";
        file_put_contents($root.'/.htaccess', $htaccess);

        $this->info("Upload directories ready at {$root}");

        return self::SUCCESS;
    }
}
