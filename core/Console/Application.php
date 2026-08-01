<?php

namespace Core\Console;

/**
 * 极简 CLI 调度器
 */
class Application
{
    private array $commands = [];

    public function add(string $name, callable $handler, string $description = ''): void
    {
        $this->commands[$name] = ['handler' => $handler, 'description' => $description];
    }

    public function run(array $argv): int
    {
        $script = array_shift($argv);
        $command = array_shift($argv);

        if (!$command || $command === 'list' || $command === '--list' || $command === '-h' || $command === 'help') {
            echo "Blog CMS CLI\n\n";
            echo "Usage: php blog <command> [options]\n\n";
            echo "Available commands:\n";
            foreach ($this->commands as $name => $c) {
                printf("  %-20s %s\n", $name, $c['description']);
            }
            return 0;
        }

        if (!isset($this->commands[$command])) {
            echo "Unknown command: $command\n";
            echo "Run 'php blog list' for available commands.\n";
            return 1;
        }

        try {
            return (int) call_user_func($this->commands[$command]['handler'], $argv);
        } catch (\Throwable $e) {
            fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
            return 1;
        }
    }
}
