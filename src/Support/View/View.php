<?php
namespace Eyika\Atom\Framework\Support\View;

use eftec\bladeone\BladeOne;
use eftec\bladeone\BladeOneCache;
use eftec\bladeone\BladeOneCacheRedis;
use eftec\bladeonehtml\BladeOneHtml;

class View extends BladeOne
{
    use BladeOneCache, BladeOneHtml;
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
        if (!$mode = config('view.mode')) {
            $mode = env('APP_ENV') == 'local' ? BladeOne::MODE_DEBUG : BladeOne::MODE_FAST;
        }
        $templatePath = $templatePath ?? config('view.paths');
        $compiledPath = $compiledPath ?? config('view.compiled');

        if (!file_exists($compiledPath)) {
            mkdir($compiledPath, 0744, true);
        }
        parent::__construct($templatePath, $compiledPath, $mode);
    }
}
