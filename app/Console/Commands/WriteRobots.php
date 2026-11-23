<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WriteRobots extends Command
{
    protected $signature = 'marketplace:write-robots';

    protected $description = 'Write public/robots.txt with the sitemap URL from APP_URL';

    public function handle(): int
    {
        $url = rtrim((string) config('app.url'), '/');
        $rules = "User-agent: *\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /profile\nDisallow: /listings\nDisallow: /login\nDisallow: /register\nDisallow: /search\n\nSitemap: {$url}/sitemap.xml\n";

        file_put_contents(public_path('robots.txt'), $rules);
        $this->info('robots.txt written to '.public_path('robots.txt'));

        return self::SUCCESS;
    }
}
