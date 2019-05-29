<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\addProductAPI::class,
        Commands\UpdateProductDetail::class,
        Commands\addCategoriesAPI::class,
        Commands\updateProductByCategory::class,
        Commands\addProductById::class,
        Commands\addProductByBranchAPI::class,
        Commands\updateProductNewAPI::class,
        Commands\addProductByIdNew::class,
        Commands\checkQtyAPI::class,
        Commands\updateNewProduct::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('add:product')
            ->hourly();
        $schedule->command('update:product')
            ->hourly();
        $schedule->command('add:categories')
            ->hourly();
        $schedule->command('update:product-by-category')
            ->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
