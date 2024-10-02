<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Foundation\Console\Command;
use Eyika\Atom\Framework\Support\Facade\File;
use Eyika\Atom\Framework\Support\Str;

class GenerateKey extends Command
{
    public string $signature = 'key:generate';
    public string $description = 'Generate an APP_KEY and set it in .env';

    public function handle(array $arguments = []): bool
    {
        try {
            $key = $this->generateAndSetAppKey();
            $this->info("APP_KEY set to: $key");
        } catch (BaseConsoleException $e) {
            $this->error($e->getMessage(), $e->getTrace());
            return !(bool)($e->getCode());
        }
        return true;
    }

    function generateAndSetAppKey()
    {
        // Generate a random 32 character key
        $key = 'base64:' . base64_encode(Str::random(32));

        // Load the .env file content
        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        // Replace the existing APP_KEY value with the new one
        if (Str::contains($envContent, 'APP_KEY=')) {
            $envContent = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $envContent);
        } else {
            $envContent .= "\nAPP_KEY=$key";
        }

        // Save the updated .env file
        File::put($envPath, $envContent);

        // Set the key in the environment
        // config(['app.key' => $key]);

        return $key;
    }
}
