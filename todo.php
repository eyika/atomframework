<?php
// https://github.com/EFTEC/ApiAssembler    use this to implement auto generation of API's from DB Schema
// https://github.com/EFTEC/CliOne    use this to implement better cli
// https://github.com/EFTEC/gentelella-bladeone implement template
// https://github.com/EFTEC/DashOne


Deprecated: Method ReflectionParameter::getClass() is deprecated since 8.0, use ReflectionParameter::getType() instead in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ClassDependencyResolver.php on line 37

Deprecated: Eyika\Atom\Framework\Support\Concerns\Conditionable::when(): Implicitly marking parameter $callback as nullable is deprecated, the explicit nullable type must be used instead in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Support/Concerns/Conditionable.php on line 21

Deprecated: Eyika\Atom\Framework\Support\Concerns\Conditionable::when(): Implicitly marking parameter $default as nullable is deprecated, the explicit nullable type must be used instead in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Support/Concerns/Conditionable.php on line 21

Deprecated: Eyika\Atom\Framework\Support\Concerns\Conditionable::unless(): Implicitly marking parameter $callback as nullable is deprecated, the explicit nullable type must be used instead in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Support/Concerns/Conditionable.php on line 53

Deprecated: Eyika\Atom\Framework\Support\Concerns\Conditionable::unless(): Implicitly marking parameter $default as nullable is deprecated, the explicit nullable type must be used instead in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Support/Concerns/Conditionable.php on line 53

Fatal error: Uncaught Error: Call to undefined method App\Console\Kernel::make() in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ClassDependencyResolver.php:46
Stack trace:
#0 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ClassDependencyResolver.php(26): Eyika\Atom\Framework\Foundation\ConsoleKernel->resolveDependencies(Array)
#1 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/ConsoleKernel.php(105): Eyika\Atom\Framework\Foundation\ConsoleKernel->resolve('Eyika\\Atom\\Fram...')
#2 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Support/NamespaceHelper.php(37): Eyika\Atom\Framework\Foundation\ConsoleKernel->{closure:Eyika\Atom\Framework\Foundation\ConsoleKernel::loadCommands():101}('Migrate', 'Eyika\\Atom\\Fram...')
#3 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/ConsoleKernel.php(101): Eyika\Atom\Framework\Support\NamespaceHelper::loadAndPerformActionOnClasses('Eyika\\Atom\\Fram...', '/Users/basttyy/...', Object(Closure), 'src')
#4 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/ConsoleKernel.php(42): Eyika\Atom\Framework\Foundation\ConsoleKernel->loadCommands()
#5 /Users/basttyy/Documents/Sites/Backend/mani-be/app/Console/Kernel.php(13): Eyika\Atom\Framework\Foundation\ConsoleKernel->__construct()
#6 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ServiceContainer.php(28): App\Console\Kernel->__construct()
#7 /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ServiceContainer.php(51): Eyika\Atom\Framework\Foundation\Application->{closure:Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer::singleton():24}(Object(Eyika\Atom\Framework\Foundation\Application))
#8 /Users/basttyy/Documents/Sites/Backend/mani-be/artisan(44): Eyika\Atom\Framework\Foundation\Application->make('Eyika\\Atom\\Fram...')
#9 {main}
  thrown in /Users/basttyy/Documents/Sites/Backend/mani-be/vendor/eyika/atom-framework/src/Foundation/Concerns/ClassDependencyResolver.php on line 46
Script @php artisan vendor:publish --tag=atom-assets --ansi --force handling the post-update-cmd event returned with error code 255