<?php

namespace Database\Seeders;

use Exception;
use Eyika\Atom\Framework\Database\Seeder\Seeder;

class DatabaseSeeder extends Seeder
{
    /** @property Seeder[] $seeders */
    protected $seeders;

    public function run(): void
    {
        try {
            foreach ($this->seeders as $seeder) {
                $this->call($seeder);
            }
        } catch (Exception $e) {

        }
        // $this->call(UserSeeder::class);
        // $this->call(PostSeeder::class);
    }

    protected function call(string $seederClass)
    {
        $seeder = new $seederClass();
        $seeder->run();
    }
}
