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
            $this->getOrAskForInput('foo', confirm: false);
        }
    };

    $input = new ArrayInput([]);
    $output = m::mock(OutputStyle::class.'[ask]', [$input, new BufferedOutput]);

    $command = new $command;
    $command->setLaravel($this->container);
    $command->setOutput($output);

    $output->shouldReceive('ask')->once()->andReturn('Bar');

    $command->run($input, $output);
});
