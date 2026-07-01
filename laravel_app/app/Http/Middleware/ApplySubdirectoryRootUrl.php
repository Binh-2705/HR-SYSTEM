<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ApplySubdirectoryRootUrl
{
    public function handle(Request $request, Closure $next)
    {
        $rootUrl = $request->getSchemeAndHttpHost() . $this->detectBasePath($request);

        URL::forceRootUrl($rootUrl);

        return $next($request);
    }

    private function detectBasePath(Request $request): string
    {
        $scriptName = (string) ($request->server('SCRIPT_NAME') ?: $request->server('PHP_SELF', ''));
        $normalizedScript = str_replace('\\', '/', $scriptName);

        if ($normalizedScript === '' || $normalizedScript === 'index.php' || $normalizedScript === '/index.php') {
            return '';
        }

        $directory = str_replace('\\', '/', dirname($normalizedScript));

        if ($directory === '.' || $directory === '/' || $directory === '\\') {
            return '';
        }

        return rtrim($directory, '/');
    }
}