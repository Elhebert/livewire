<?php

namespace Livewire\Commands;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class MoveCommand extends FileManipulationCommand
{
    protected $signature = 'livewire:move {name} {new-name} {--force} {--inline}';

    protected $description = 'Move a Livewire component.';

    public function handle()
    {
        $this->parser = new ComponentParser(
            config('livewire.class_namespace', 'App\\Http\\Livewire'),
            config('livewire.view_path', resource_path('views/livewire')),
            $this->argument('name')
        );

        $this->newParser = new ComponentParserFromExistingComponent(
            config('livewire.class_namespace', 'App\\Http\\Livewire'),
            config('livewire.view_path', resource_path('views/livewire')),
            $this->argument('new-name'),
            $this->parser
        );

        $inline = $this->option('inline');

        $class = $this->renameClass();
        if (! $inline) $view = $this->renameView();

        $this->renameComponentInViews();

        $this->refreshComponentAutodiscovery();

        $this->line("<options=bold,reverse;fg=green> COMPONENT MOVED </> 🤙\n");
        $class && $this->line("<options=bold;fg=green>CLASS:</> {$this->parser->relativeClassPath()} <options=bold;fg=green>=></> {$this->newParser->relativeClassPath()}");
        if (! $inline) $view && $this->line("<options=bold;fg=green>VIEW:</>  {$this->parser->relativeViewPath()} <options=bold;fg=green>=></> {$this->newParser->relativeViewPath()}");
    }

    protected function renameClass()
    {
        if (File::exists($this->newParser->classPath())) {
            $this->line("<options=bold,reverse;fg=red> WHOOPS-IE-TOOTLES </> 😳 \n");
            $this->line("<fg=red;options=bold>Class already exists:</> {$this->newParser->relativeClassPath()}");

            return false;
        }

        $this->ensureDirectoryExists($this->newParser->classPath());

        File::put($this->newParser->classPath(), $this->newParser->classContents());

        return File::delete($this->parser->classPath());
    }

    protected function renameView()
    {
        $newViewPath = $this->newParser->viewPath();

        if (File::exists($newViewPath)) {
            $this->line("<fg=red;options=bold>View already exists:</> {$this->newParser->relativeViewPath()}");

            return false;
        }

        $this->ensureDirectoryExists($newViewPath);

        File::move($this->parser->viewPath(), $newViewPath);

        return $newViewPath;
    }

    protected function renameComponentInViews()
    {
        $componentName = Str::replaceFirst('livewire.', '', $this->parser->viewName());
        $newComponentName = Str::replaceFirst('livewire.', '', $this->newParser->viewName());

        collect(config('view.paths'))->each(function ($path) use ($componentName, $newComponentName) {
            collect(File::allFiles($path))->each(function ($file) use ($componentName, $newComponentName) {
                $content = preg_replace(
                    "/(<|@)livewire(:|\('){$componentName}/",
                    "$1livewire$2{$newComponentName}",
                    $file->getContents()
                );

                File::put($file->getPathName(), $content);
            });
        });
    }
}
