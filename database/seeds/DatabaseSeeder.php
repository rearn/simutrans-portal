<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(ProdSeeder::class);

        if(App::environment(['local', 'development'])) {
            // $this->call(DevSeeder::class);
        }

    }
}
