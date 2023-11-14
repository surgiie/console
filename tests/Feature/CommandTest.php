<?php

use Carbon\Carbon;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use Surgiie\Console\Command as ConsoleCommand;
use Surgiie\Console\Concerns\LoadsEnvFiles;
use Surgiie\Console\Concerns\LoadsJsonFiles;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    @mkdir(test_mock_file_path());
    $this->container = new Container;
});

it('validates options and arguments', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {foo}
                                   {--dooms-day=}';

        public function rules()
        {
            return ['foo' => 'min:4', 'dooms-day' => 'required|date'];
        }

        public function handle()
        {
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([
        'foo' => 'bar',
        '--dooms-day' => 'not-a-date',
    ]);

    $status = $command->run($input, $output);
    expect($status)->toBe(1);

    $commandOutput = $output->fetch();
    $this->assertStringContainsString(
        'ERROR  The foo argument must be at least 4 characters.',
        $commandOutput
    );

    $this->assertStringContainsString(
        'ERROR  The --dooms-day option is not a valid date.',
        $commandOutput
    );
});
it('can run task', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function handle()
        {
            $this->succeeded = $this->runTask('Doing something', function () {
                return true;
            });
        }
    };

    $outputMock = m::mock(OutputStyle::class);

    $command->setOutput($outputMock);

    $this->container->bind(OutputStyle::class, function () use ($outputMock) {
        return $outputMock;
    });

    $command->setLaravel($this->container);

    $outputMock
        ->shouldReceive('isDecorated')->andReturn(true)
        ->shouldReceive('write')
        ->shouldReceive('writeln')->andReturn('Finished - [Doing Something]');

    $command->run(new ArrayInput([]), $outputMock);
    expect($command->succeeded)->toBeTrue();
});

it('can have transformers', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {--first-name=}{--last-name=}';

        public function handle()
        {
        }

        protected function transformers(): array
        {
            return [
                'first-name' => 'ucfirst',
                'last-name' => 'ucfirst',
            ];
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);

    $input = new ArgvInput([
        'application',
        '--first-name=jim',
        '--last-name=thompson',
    ]);

    $command->run($input, new NullOutput);

    expect($command->getData()->all())->toBe(['first-name' => 'Jim', 'last-name' => 'Thompson']);
});

it('can have transformers after validation', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {foo}
                                   {--dooms-day=}';

        public function rules()
        {
            return ['foo' => 'numeric|min:4', 'dooms-day' => 'required|date'];
        }

        protected function transformers(): array
        {
            return [
                'foo' => ['intval'],
            ];
        }

        protected function transformersAfterValidation(): array
        {
            return [
                'foo' => [fn ($v) => $v + 1],
                'dooms-day' => Carbon::class,
            ];
        }

        public function handle()
        {
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);

    $input = new ArgvInput([
        'application',
        '4',
        '--dooms-day=01/01/2000',
    ]);

    $command->run($input, new NullOutput);

    $data = $command->getData();

    expect($data->get('foo'))->toBe(5);
    expect($data->get('dooms-day'))->toBeInstanceOf(Carbon::class);
});

it('can have arbitrary options', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {--a=}{--b=}';

        public function arbitraryOptions()
        {
            return true;
        }

        public function handle()
        {
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);

    $input = new ArgvInput([
        'application',
        '--a=1',
        '--b=2',
        '--c=3',
        '--d=4',
    ]);

    $command->run($input, new NullOutput);

    expect($command->getData()->all())->toBe(['a' => '1', 'b' => '2']);
    expect($command->getArbitraryData()->all())->toBe(['c' => '3', 'd' => '4']);
});

it('can render files with blade', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        protected function bladeCompiledPath(): string
        {
            return __DIR__.'/.compiled';
        }

        public function handle()
        {
            $testFilePath = test_mock_file_path('test-blade-file');

            $contents = $this->render($testFilePath, [
                'name' => 'Bob',
                'favoriteFood' => 'Pizza',
                'includeAddress' => true,
                'dogs' => ['Rex', 'Charlie'],
            ]);

            $this->line($contents);
        }
    };

    $command = new $command;
    $input = new ArrayInput([]);
    $output = new BufferedOutput();
    $command->setLaravel($this->container);

    file_put_contents($testFilePath = test_mock_file_path('test-blade-file'), <<< 'EOL'
    name: {{ $name }}
    favorite_food: {{ $favoriteFood }}
    pets:
        @foreach($dogs as $dog)
        - {{ $dog }}
        @endforeach
    contact_info:
        phone: 1234567890
        @if($includeAddress)
        street_info: 123 Lane.
        @endif
    EOL);

    $command->run($input, $output);

    $output = rtrim($output->fetch());
    expect($output)->toBe(<<<'EOL'
    name: Bob
    favorite_food: Pizza
    pets:
        - Rex
        - Charlie
    contact_info:
        phone: 1234567890
        street_info: 123 Lane.
    EOL);

    unlink($testFilePath);
});

it('can load json files with trait', function () {
    file_put_contents(test_mock_file_path('test.json'), json_encode(['foo' => 'bar']));

    $command = new class extends ConsoleCommand
    {
        use LoadsJsonFiles;

        public $loadedData = null;

        protected $signature = 'example';

        public function handle()
        {
            $this->loadedData = $this->loadJsonFile(test_mock_file_path('test.json'));
        }
    };

    $command->setLaravel($this->container);

    $command->run(new ArrayInput([]), new BufferedOutput());

    expect($command->loadedData)->toBe(['foo' => 'bar']);
});

it('throws exception when loading bad json with trait', function () {
    expect(function () {
        file_put_contents(test_mock_file_path('test.json'), '{ bad');

        $command = new class extends ConsoleCommand
        {
            use LoadsJsonFiles;

            public $loadedData = null;

            protected $signature = 'example';

            public function handle()
            {
                $this->loadedData = $this->loadJsonFile(test_mock_file_path('test.json'));
            }
        };

        $command->setLaravel($this->container);
        $command->run(new ArrayInput([]), new BufferedOutput());
    })->toThrow(JsonException::class);
});
it('env file must exist to load with trait', function () {
    $path = test_mock_file_path('.env');

    expect(function () {
        $command = new class extends ConsoleCommand
        {
            use LoadsEnvFiles;

            public $loadedData = null;

            protected $signature = 'example';

            public function handle()
            {
                $path = test_mock_file_path('.env');

                $this->loadedData = $this->loadEnvFileVariables($path);
            }
        };

        $command->setLaravel($this->container);

        $command->run(new ArrayInput([]), new BufferedOutput());
    })->toThrow(\InvalidArgumentException::class, "The env file '$path' does not exist.");
});
it('can load env files with trait', function () {
    file_put_contents(test_mock_file_path('.env'), 'APP_ENV=local');

    $command = new class extends ConsoleCommand
    {
        use LoadsEnvFiles;

        public $loadedData = null;

        protected $signature = 'example';

        public function handle()
        {
            $this->loadedData = $this->loadEnvFileVariables(test_mock_file_path('.env'));
        }
    };

    $command->setLaravel($this->container);

    $command->run(new ArrayInput([]), new BufferedOutput());

    expect($command->loadedData)->toBe(['APP_ENV' => 'local']);

    expect($_ENV['APP_ENV'])->toBe('local');
});

it('can soft load env files with trait', function () {
    file_put_contents(test_mock_file_path('.env'), 'EXAMPLE_VAR=bar');

    $command = new class extends ConsoleCommand
    {
        use LoadsEnvFiles;

        public $loadedData = null;

        protected $signature = 'example';

        public function handle()
        {
            $this->loadedData = $this->getEnvFileVariables(test_mock_file_path('.env'));
        }
    };

    $command->setLaravel($this->container);

    $command->run(new ArrayInput([]), new BufferedOutput());

    expect($command->loadedData)->toBe(['EXAMPLE_VAR' => 'bar']);

    expect($_ENV['EXAMPLE_VAR'] ?? null)->toBeNull();
});
