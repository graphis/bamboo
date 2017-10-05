<?php

namespace tyam/bamboo;

use ArrayObject;
use ArrayAccess;

class Engine
{
    const SEPARATOR = '/';
    const SUFFIX = '.php';

    private $basedirs;
    private $variableProvider;

    public static function loadFunctions()
    {
        require_once(__DIR__ . DIRECTORY_SEPARATOR . 'functions.php');
    }

    public function __construct(array $basedirs, VariableProvider $variableProvider = null)
    {
        $this->basedirs = $basedirs;
        $this->variableProvider = $variableProvider;

    public function render(string $template, array $variables = null, ArrayAccess $sections = null)
    {
        if (is_null($sections)) {
            $sections = new ArrayObject();
        }
        $renderer = new Renderer([$this, 'resolve'], $sections);
        $output = $renderer->render($template, $variables);
        return $output;
    }

    public function resolve(string $template, array $variables) 
    {
        return [
            $this->resolvePath($template), 
            $this->resolveEnv($template, $variables)
        ];
    }

    protected function resolvePath(string $template)
    {
        foreach ($this->basedirs as $basedir) {
            $path = str_replace(self::SEPARATOR, DIRECTORY_SEPARATOR, $basedir . $template . self::SUFFIX);
            if (file_exists($path)) {
                return $path;
            }
        }
        // template not found
        throw new \LogicException('template not found: '.$template);
    }

    protected function resolveEnv(string $template, array $variables)
    {
        // explicit-bindings > auto-bindings
        $env = $this->getAutoBindings($template);
        if (is_null($variables)) {
            $variables = [];
        }
        return array_merge($env, $variables);
    }

    protected function getAutoBindings(string $template)
    {
        if (is_null($this->variableProvider)) {
            return [];
        }

        $bindings = $this->variableProvider->provideVariables($template);

        return $bindings;
    }
}