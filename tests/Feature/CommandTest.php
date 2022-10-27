<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use Surgiie\Console\Command as ConsoleCommand;
use Surgiie\Console\Concerns\WithValidation;
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
        use WithValidation;

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

it('can ask for input if option or argument is not set.', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {--foo=}';

        public function handle()
        {
            $this->getOrAskForInput('foo');
        }
    };

    $input = new ArrayInput([]);

    $outputStyle = m::mock(OutputStyle::class.'[ask]', [$input, $output = new BufferedOutput()]);
    $outputStyle->shouldReceive('ask')->once()->andReturn('Bar');

    $this->container->bind(OutputStyle::class, function () use ($outputStyle) {
        return $outputStyle;
    });
    $command = new $command;
    $command->setLaravel($this->container);
    $command->setOutput($outputStyle);

    $command->run($input, $outputStyle);

    $output = trim($output->fetch());
    expect($output)->toBe('INPUT  Enter foo:');
    expect($command->getData('foo'))->toBe('Bar');
});

it('can confirm ask for input.', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {--foo=}';

        public function handle()
        {
            $this->getOrAskForInput('foo', confirm: true);
        }
    };

    $input = new ArrayInput([]);

    $outputStyle = m::mock(OutputStyle::class.'[ask]', [$input, $output = new BufferedOutput()]);
    $outputStyle->shouldReceive('ask')->twice()->andReturn('Bar');

    $this->container->bind(OutputStyle::class, function () use ($outputStyle) {
        return $outputStyle;
    });
    $command = new $command;
    $command->setLaravel($this->container);
    $command->setOutput($outputStyle);

    $command->run($input, $outputStyle);

    $output = $output->fetch();
    expect($output)->toContain('INPUT  Enter foo:');
    expect($output)->toContain('CONFIRM INPUT  Confirm foo:');

    expect($command->getData('foo'))->toBe('Bar');
});

it('can ask for input and validate', function () {
    $command = new class extends ConsoleCommand
    {
        use WithValidation;

        protected $signature = 'example {--dooms-day=}';

        public function handle()
        {
            $this->getOrAskForInput('dooms-day', rules: [
                'date',
            ]);
        }
    };

    $input = new ArrayInput([]);

    $outputStyle = m::mock(OutputStyle::class.'[ask]', [$input, $output = new BufferedOutput()]);
    $outputStyle->shouldReceive('ask')->once()->andReturn('Bar');

    $this->container->bind(OutputStyle::class, function () use ($outputStyle) {
        return $outputStyle;
    });
    $command = new $command;
    $command->setLaravel($this->container);
    $command->setOutput($outputStyle);

    $command->run($input, $outputStyle);

    $output = $output->fetch();
    expect($output)->toContain('INPUT  Enter dooms day:');
    expect($output)->toContain('ERROR  The dooms day is not a valid date.');
    expect($command->getData('foo'))->toBeNull();
});

it('can compile files with blade', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function handle()
        {
            $testFilePath = test_mock_file_path('test-blade-file');
            $contents = $this->compile($testFilePath, [
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
