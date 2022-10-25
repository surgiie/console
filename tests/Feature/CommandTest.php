<?php

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

afterEach(fn () => m::close());

it('it validates options and arguments', function () {
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

    $command->run($input, $output);
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

it('it can have arbitrary options', function () {
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
