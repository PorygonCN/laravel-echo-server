<?php

namespace Porygon\LaravelEchoServer;

use Symfony\Component\Console\Output\ConsoleOutput as OutputConsoleOutput;

/**
 * 继承此类可对控制台使用artisan 输入输出
 */
class ConsoleOutput
{
    use \Illuminate\Console\Concerns\InteractsWithIO;

    public function __construct()
    {
        $this->output = new OutputConsoleOutput();
    }
}
