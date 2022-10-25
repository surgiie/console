<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Mockery as m;
use Surgiie\Console\Command as ConsoleCommand;
use Symfony\Component\Console\Input\ArrayInput;
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

it('it merges options and arguments into data property', function () {
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
