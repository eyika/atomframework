<?php

namespace Eyika\Atom\Framwork\Foundation\Console\Commands\Make;

use Exception;
use Eyika\Atom\Framework\Exceptions\Console\InvalidInputException;
use Eyika\Atom\Framework\Exceptions\Console\RuntimeException;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framwork\Foundation\Console\Command;

class Controller extends Command
{
    public function handle(array $arguments = []): int
    {
        try {
            if (empty($name = $arguments[0] ?? '')) {
                throw new InvalidInputException('name of controller is not specified', 1);
            }
            $type = $arguments[1] ?? '--web';
    
            $name = Str::pascal($name);
            $controller_str = Str::snake($name);
            $controller_str_spc = str_replace('_', ' ', $controller_str);
            $controller_str_plural = Str::plural($controller_str);
            $controller_str_spc_plural = Str::plural($controller_str_spc);
            $slash = DIRECTORY_SEPARATOR;
    
            if ($type === '--api') {
                $api = 'Api'.$slash;
            } else {
                $api = '';
            }
            $controller_folder = base_path().$slash."Controllers".$slash.$api;
        
            $api = str_replace($slash, '', $api);
    
            $controller_template = "<?php
            namespace App\Http\Controllers$api;
            
            use Eyika\Atom\Framework\Http\JsonResponse;
            use Eyika\Atom\Framework\Support\Validator;
            use Eyika\Atom\Framework\Http\Request;
            use App\Models\\$name;
            use Exception;
            use LogicException;
            use PDOException;
            
            final class {$name}Controller
            {
                public function show(Request \$request, string \$id)
                {
                    \$id = sanitize_data(\$id);
                    try {
                        if (!\$$controller_str = $name::getBuilder()->find((int)\$id))
                            return JsonResponse::notFound('unable to retrieve $controller_str_spc');
            
                        return JsonResponse::ok('$controller_str_spc retrieved success', \${$controller_str}->toArray());
                    } catch (PDOException \$e) {
                        return JsonResponse::serverError('we encountered a db problem');
                    } catch (LogicException \$e) {
                        return JsonResponse::serverError('we encountered a runtime problem');
                    } catch (Exception \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    }
                }
            
                public function list(Request \$request)
                {
                    try {
                        \$$controller_str_plural = $name::getBuilder()->all();
                        if (!\$$controller_str_plural)
                            return JsonResponse::ok('no $controller_str_spc_plural found in list', []);
            
                        return JsonResponse::ok(\"$controller_str_spc_plural retrieved success\", \$$controller_str_plural);
                    } catch (PDOException \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    } catch (Exception \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    }
                }
            
                public function create(Request \$request)
                {
                    try {
                        if ( !\$request->hasBody()) {
                            return JsonResponse::badRequest('bad request', 'body is required');
                        }
            
                        \$body = sanitize_data(\$request->input());
                        \$status = 'some, values';
            
                        if (\$validated = Validator::validate(\$body, [
                            'foo' => 'required|string',
                            'bar' => 'sometimes|numeric',
                            'baz' => \"sometimes|string|in:\$status\",
                            //add more validation rules here
                        ])) {
                            return JsonResponse::badRequest('errors in request', \$validated);
                        }
            
                        if (!\$$controller_str = $name::getBuilder()->create(\$body)) {
                            return JsonResponse::serverError('unable to create $controller_str_spc');
                        }
            
                        return JsonResponse::created('$controller_str_spc creation successful', \$$controller_str);
                    } catch (PDOException \$e) {
                        if (str_contains(\$e->getMessage(), 'Duplicate entry'))
                            return JsonResponse::badRequest('$controller_str_spc already exist');
                        else \$message = 'we encountered a problem';
                        
                        return JsonResponse::serverError(\$message);
                    } catch (Exception \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    }
                }
            
                public function update(Request \$request, string \$id)
                {
                    try {
                        if ( !\$request->hasBody()) {
                            return JsonResponse::badRequest('bad request', 'body is required');
                        }
            
                        \$id = sanitize_data(\$id);
            
                        \$body = sanitize_data(\$request->input());
            
                        \$status = 'some, values';
            
                        if (\$validated = Validator::validate(\$body, [
                            'foo' => 'sometimes|boolean',
                            'bar' => 'sometimes|numeric',
                            'baz' => \"sometimes|string|in:\$status\",
                            //add more validation rules here
                        ])) {
                            return JsonResponse::badRequest('errors in request', \$validated);
                        }
            
                        if (!\$$controller_str = $name::getBuilder()->update(\$body, (int)\$id)) {
                            return JsonResponse::notFound('unable to update $controller_str_spc not found');
                        }
            
                        return JsonResponse::ok('$controller_str_spc updated successfull', \${$controller_str}->toArray());
                    } catch (PDOException \$e) {
                        if (str_contains(\$e->getMessage(), 'Unknown column'))
                            return JsonResponse::badRequest('column does not exist');
                        else \$message = 'we encountered a problem';
                        
                        return JsonResponse::serverError(\$message);
                    } catch (Exception \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    }
                }
            
                public function delete(Request \$request, int \$id)
                {
                    try {
                        \$id = sanitize_data(\$id);
            
                        \$user = \$request->auth_user;
            
                        // Uncomment this for role authorization
                        // if (!Guard::roleIs(\$user, 'admin')) {
                        //     return JsonResponse::unauthorized(\"you can't delete a $controller_str_spc\");
                        // }
            
                        if (!$name::getBuilder()->delete((int)\$id)) {
                            return JsonResponse::notFound('unable to delete $controller_str_spc or $controller_str_spc not found');
                        }
            
                        return JsonResponse::ok('$controller_str_spc deleted successfull');
                    } catch (PDOException \$e) {
                        if (str_contains(\$e->getMessage(), 'Unknown column'))
                            return JsonResponse::badRequest('column does not exist');
                        else \$message = 'we encountered a problem';
                        
                        return JsonResponse::serverError(\$message);
                    } catch (Exception \$e) {
                        return JsonResponse::serverError('we encountered a problem');
                    }
                }
            }
            ";
        
            if (file_exists($controller_folder.$name.'Controller.php')) {
                throw new RuntimeException("controller with name $name already exists", 1);
            }
            file_put_contents($controller_folder.$name.'Controller.php', $controller_template);
            $this->info("controller with name $name created successfully");
            return 0;
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return $e->getCode();
        }
    }
}
