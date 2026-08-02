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

    public function handle(): bool
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
        // 32 RAW random bytes — the full 256 bits AES-256-CBC expects. Str::random(32) returns
        // 32 printable characters drawn from the base64 alphabet, i.e. ~6 bits each (~192 bits),
        // so it is not a substitute here. Encrypter decodes the `base64:` prefix back to bytes.
        $key = 'base64:' . base64_encode(random_bytes(32));

        // Load the .env file content
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        // Replace the existing APP_KEY value with the new one
        if (Str::contains($envContent, 'APP_KEY=')) {
            $envContent = preg_replace('/^APP_KEY=.*/m', 'APP_KEY=' . $key, $envContent);
        } else {
            $envContent .= "\nAPP_KEY=$key";
        }

        // Save the updated .env file
        file_put_contents($envPath, $envContent);

        // Set the key in the environment
        // config(['app.key' => $key]);

        return $key;
    }
}
