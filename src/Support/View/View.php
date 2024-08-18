<?php
namespace Eyika\Atom\Framework\Support\View;

use eftec\bladeone\BladeOne;
use eftec\bladeone\BladeOneCache;
use eftec\bladeone\BladeOneCacheRedis;
use eftec\bladeonehtml\BladeOneHtml;

class View extends BladeOne
{
    use BladeOneCache, BladeOneCacheRedis, BladeOneHtml;
    /**
     * Bob the constructor.
     * The folder at $compiledPath is created in case it doesn't exist.
     *
     * @param string|array $templatePath If null then it uses (caller_folder)/views
     * @param string       $compiledPath If null then it uses (caller_folder)/compiles
     * @param int          $mode         =[BladeOne::MODE_AUTO,BladeOne::MODE_DEBUG,BladeOne::MODE_FAST,BladeOne::MODE_SLOW][$i]
     */
    public function __construct($templatePath = null, $compiledPath = null)
    {
        $mode = config('view.mode', env('APP_ENV') == 'local' ? BladeOne::MODE_DEBUG : BladeOne::MODE_FAST);
        $templatePath = $templatePath ?? config('view.paths');
        $compiledPath = $compiledPath ?? config('view.compiled');

        parent::__construct($templatePath, $compiledPath, $mode);
    }
}