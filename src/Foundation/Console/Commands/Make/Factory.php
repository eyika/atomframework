<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Eyika\Atom\Framework\Exceptions\Console\BaseConsoleException;
use Eyika\Atom\Framework\Support\Str;

class Factory extends BaseMake
{
    public string $description = 'Create a new factory class';

    public string $signature = 'make:factory';
    protected string $type = 'Factory';
    protected string $directory = 'database/factories';

    protected string $stub = <<<EOT
<?php

namespace Database\Factories;

use Atom\Framework\Database\Factories\Factory;

class {{name}} extends Factory
{
    public function definition(): array
    {
        return [
            // Define default attributes here
        ];
    }

    protected function getTable(): string
    {
        return '{{table_name}}';
    }
}

// 3. Using States, Hooks, Sequences, and Relationships
// A. Applying a State

// {{dollar_sign}}user = (new UserFactory())->state(['role' => 'admin'])->create();

// B. Using After Hooks
// {{dollar_sign}}user = (new UserFactory())
//     ->afterCreating(function ({{dollar_sign}}user) {
//         // Send a welcome email
//         mail({{dollar_sign}}user['email'], "Welcome!", "Thanks for joining.");
//     })
//     ->create();

// C. Using Sequences
// {{dollar_sign}}users = (new UserFactory())
//     ->sequence([
//         ['email' => 'user1@example.com'],
//         ['email' => 'user2@example.com'],
//         ['email' => 'user3@example.com'],
//     ])
//     ->times(3)
//     ->create();
// This creates three users with predefined emails.

// One-to-One (for())
// {{dollar_sign}}user = (new UserFactory())
//     ->for(new ProfileFactory(), 'user_id')
//     ->create();

// This creates a user with a profile, setting user_id in the profile.

// One-to-Many (has())
// {{dollar_sign}}user = (new UserFactory())
//     ->has(new PostFactory(), 5, 'user_id')
//     ->create();

// This creates a user with 5 posts.

EOT;

    protected function stubContent(): string
    {
        info('', $this->options());
        $table = $this->option('table') ?? Str::plural(str_replace('_factory', '', Str::snake($this->argument(0))) ?? 'table');
        $content = str_replace('{{table_name}}', $table, $this->stub);
        $content = str_replace('{{dollar_sign}}', '$', $content);
        return $content;
    }

    protected function filename(): string
    {
        $name =  $this->argument(0);
        if (is_null($name)) {
            throw new BaseConsoleException("Please provide a name for the {$this->type}");
        }
        if (!Str::isPascal($name)) {
            throw new BaseConsoleException("Seeder name should be in PascalCase");
        }

        return $name;
    }
}
