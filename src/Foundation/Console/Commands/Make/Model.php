<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\InvalidInputException;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Foundation\Console\Command;

class Model extends Command
{
    public string $signature = 'make:model';

    public function handle(array $arguments = []): bool
    {
        try {
            if (empty($name = $arguments[0] ?? '')) {
                throw new InvalidInputException('Name of model is not specified', 1);
            }
    
            $name = Str::pascal($name);
            $name_lower = Str::plural(Str::snake($name));
            $slash = DIRECTORY_SEPARATOR;
            $model_folder = base_path().$slash."Models".$slash;
    
    
            if (file_exists($model_folder.$name.'.php')) {
                throw new Exception("Model with name $name already exists", 1);
            }
    
            $model_template = "<?php
            
            namespace App\Models;
            
            use App\Models\Model;
            
            final class {$name} extends Model
            {
                protected \$softdeletes = true;
            
                protected \$table = '$name_lower';
            
                protected \$primaryKey = 'id';
            
                //object properties
                public \$id;
                public \$created_at;
                public \$updated_at;
                public \$deleted_at;
                //add more $name's properties here
            
                /**
                 * Indicates what database attributes of the model can be filled at once
                 * 
                 * @var array
                 */
                protected const fillable = [
                    'id', 'created_at', 'updated_at', 'deleted_at',
                    //add more fillable columns here
                ];
            
                /**
                 * Indicates what database attributes of the model can be exposed outside the application
                 * 
                 * @var array
                 */
                protected const guarded = [
                    'deleted_at', 'created_at', 'updated_at'
                    //add more guarded columns here
                ];
            
                /**
                 * Create a new $name instance.
                 *
                 * @return void
                 */
                public function __construct(\$values = [])
                {
                    parent::__construct(\$values, \$this);
                }
            }
            ";
    
            file_put_contents($model_folder.$name.'.php', $model_template);
            $this->info("Model with name $name created successfully");
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return !(bool)$e->getCode();
        }
        return true;
    }
}
