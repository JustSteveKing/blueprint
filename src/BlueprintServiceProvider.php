<?php

declare(strict_types=1);

namespace Blueprint;

use Blueprint\Commands\BuildCommand;
use Blueprint\Commands\EraseCommand;
use Blueprint\Commands\InitCommand;
use Blueprint\Commands\NewCommand;
use Blueprint\Commands\PublishStubsCommand;
use Blueprint\Commands\TraceCommand;
use Blueprint\Contracts\Generator;
use Blueprint\Lexers\ConfigLexer;
use Blueprint\Lexers\ControllerLexer;
use Blueprint\Lexers\ModelLexer;
use Blueprint\Lexers\SeederLexer;
use Blueprint\Lexers\StatementLexer;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class BlueprintServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
        if (!defined('STUBS_PATH')) {
            define('STUBS_PATH', dirname(__DIR__) . '/stubs');
        }

        if (!defined('CUSTOM_STUBS_PATH')) {
            define('CUSTOM_STUBS_PATH', base_path('stubs/blueprint'));
        }

        $this->publishes([
            __DIR__ . '/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');

        $this->publishes([
            dirname(__DIR__) . '/stubs' => CUSTOM_STUBS_PATH,
        ], 'blueprint-stubs');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blueprint.php',
            'blueprint'
        );

        $this->app->make(
            abstract: Filesystem::class,
        )->mixin(
            mixin: new FileMixins(),
        );

        $this->app->bind(
            abstract: BuildCommand::class,
            concrete: fn (Application $app): BuildCommand => new BuildCommand(
                filesystem: $app['files'],
                builder: $app->make(
                    abstract: Builder::class,
                ),
            ),
        );
        $this->app->bind(
            abstract: EraseCommand::class,
            concrete: fn (Application $app): EraseCommand => new EraseCommand(
                filesystem: $app['files'],
            ),
        );
        $this->app->bind(
            abstract: TraceCommand::class,
            concrete: fn (Application $app): TraceCommand => new TraceCommand(
                filesystem: $app['files'],
                tracer: $app->make(
                    abstract: Tracer::class,
                ),
            ),
        );
        $this->app->bind(
            abstract: NewCommand::class,
            concrete: fn (Application $app): NewCommand => new NewCommand(
                filesystem: $app['files'],
            ),
        );
        $this->app->bind(
            abstract: InitCommand::class,
            concrete: fn (): InitCommand => new InitCommand(),
        );
        $this->app->bind(
            abstract: PublishStubsCommand::class,
            concrete: fn () => new PublishStubsCommand(),
        );

        $this->app->singleton(
            abstract: Blueprint::class,
            concrete: function (Application $app): Blueprint {
                $blueprint = Blueprint::registerLexers(
                    new ConfigLexer(
                        app: $app,
                    ),
                    new ModelLexer(),
                    new SeederLexer(),
                    new ControllerLexer(
                        statementLexer: new StatementLexer(),
                    ),
                );

                /**
                 * @var Generator $generator
                 */
                foreach (config('blueprint.generators') as $generator) {
                    $blueprint->registerGenerator(
                        generator: new $generator(
                            filesystem: $app['files'],
                        ),
                    );
                }

                return $blueprint;
            },
        );

        $this->app->make(
            abstract: Dispatcher::class,
        )->listen(
            events: CommandFinished::class,
            listener: function ($event) {
                if ($event->command === 'stub:publish') {
                    $this->app->make(
                        abstract: Kernel::class,
                    )->queue('blueprint:stubs');
                }
            },
        );

        $this->commands([
            BuildCommand::class,
            EraseCommand::class,
            TraceCommand::class,
            NewCommand::class,
            InitCommand::class,
            PublishStubsCommand::class,
        ]);
    }

    /**
     * @return array<int,class-string>
     */
    public function provides(): array
    {
        return [
            BuildCommand::class,
            EraseCommand::class,
            TraceCommand::class,
            NewCommand::class,
            InitCommand::class,
            PublishStubsCommand::class,
            Blueprint::class,
        ];
    }
}
