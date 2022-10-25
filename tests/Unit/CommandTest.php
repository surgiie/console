<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use Surgiie\Console\Command as ConsoleCommand;
use Surgiie\Console\Tests\RequirementExample;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $this->container = new Container;
});
afterEach(fn () => m::close());

it('can store values into properties property', function () {
    $command = new class extends ConsoleCommand
    {
        public function example()
        {
            return $this->getProperty('foo', function () {
                return 'bar';
            });
        }
    };
    $command = new $command;

    $value = $command->example();

    expect($value)->toBe('bar');

    expect($command->getProperty('foo'))->toBe('bar');
});

it('merges options and arguments into data property', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example {foo}
                                   {--bar=}';

        public function handle()
        {
        }
    };

    $command = new $command;

    $command->setLaravel($this->container);
    $outputStyle = m::mock(OutputStyle::class);
    $command->setOutput($outputStyle);

    $input = new ArrayInput([
        'foo' => 'bar',
        '--bar' => 'baz',
    ]);

    $command->run($input, new NullOutput);

    expect($command->getData()->all())->toBe(['foo' => 'bar', 'bar' => 'baz']);
});

it('shows performance stats', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function showPerformanceStats()
        {
            return true;
        }

        public function handle()
        {
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([]);

    $status = $command->run($input, $output);
    expect($status)->toBe(0);
    $commandOutput = $output->fetch();
    preg_match("/PEFORMANCE  Memory: [0-9]+\.[0-9]{1,2}(.+)|Execution Time: [0-9]+\.[0-9]{1,2}ms/", $commandOutput, $matches);

    $this->assertStringContainsString(
        trim($commandOutput),
        trim($matches[0])
    );
});

it('checks requirements', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function requirements()
        {
            return [
                'some-dependency-that-doesnt-exist',
            ];
        }

        public function handle()
        {
            $this->info('Doing stuff...');
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([]);

    $status = $command->run($input, $output);
    expect($status)->toBe(1);
    $commandOutput = trim($output->fetch());

    $this->assertStringContainsString(
        $commandOutput,
        'ERROR  This command requires some-dependency-that-doesnt-exist.'
    );
    $this->assertStringNotContainsString(
        $commandOutput,
        'Doing Stuff...'
    );
});

it('checks requirement callbacks', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function requirements()
        {
            return [
                function () {
                    return 'Something aint right.';
                },
            ];
        }

        public function handle()
        {
            $this->info('Doing stuff...');
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([]);

    $status = $command->run($input, $output);
    expect($status)->toBe(1);
    $commandOutput = $output->fetch();

    $this->assertStringContainsString(
        trim($commandOutput),
        'ERROR  Something aint right.'
    );
    $this->assertStringNotContainsString(
        $commandOutput,
        'Doing Stuff...'
    );
});

it('checks requirement via class methods', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function requirements()
        {
            return [
                'checkThing',
            ];
        }

        public function checkThing()
        {
            return 'The thing wasnt right';
        }

        public function handle()
        {
            $this->info('Doing stuff...');
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([]);

    $status = $command->run($input, $output);
    expect($status)->toBe(1);
    $commandOutput = $output->fetch();

    $this->assertStringContainsString(
        trim($commandOutput),
        'ERROR  The thing wasnt right.'
    );
    $this->assertStringNotContainsString(
        $commandOutput,
        'Doing Stuff...'
    );
});

it('checks requirement via invokable classes', function () {
    $command = new class extends ConsoleCommand
    {
        protected $signature = 'example';

        public function requirements()
        {
            return [
                RequirementExample::class,
            ];
        }

        public function handle()
        {
            $this->info('Doing stuff...');
        }
    };

    $command = new $command;
    $command->setLaravel($this->container);
    $output = new BufferedOutput();

    $input = new ArrayInput([]);

    $status = $command->run($input, $output);
    expect($status)->toBe(1);
    $commandOutput = $output->fetch();

    $this->assertStringContainsString(
        trim($commandOutput),
        'ERROR  Failed something.'
    );
    $this->assertStringNotContainsString(
        $commandOutput,
        'Doing Stuff...'
    );
});
