<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

final class HookSandbox
{
    public const string ZERO_SHA = '0000000000000000000000000000000000000000';

    public const array INHERITED_GIT_VARIABLES = ['GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE', 'GIT_PREFIX', 'GIT_OBJECT_DIRECTORY', 'GIT_COMMON_DIR', 'GIT_NAMESPACE'];

    /**
     * @var list<string>
     */
    private static array $roots = [];

    private function __construct(
        public readonly string $root,
        public readonly string $repo,
        public readonly string $bin,
        public readonly string $log,
    ) {}

    public static function create(string $branch = 'main'): self
    {
        $root = self::temporaryDirectory();
        $sandbox = new self($root, $root.'/repo', $root.'/bin', $root.'/npm.log');

        mkdir($sandbox->repo);
        mkdir($sandbox->bin);
        mkdir($root.'/nohooks');

        $npm = $sandbox->bin.'/npm';
        file_put_contents($npm, "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> \"\$NPM_LOG\"\nif [ -n \"\$NPM_SCRIPT\" ]; then\n    sh \"\$NPM_SCRIPT\"\nfi\nexit \"\${NPM_EXIT:-0}\"\n");
        chmod($npm, 0755);

        $sandbox->git('init', '-q', '-b', $branch)->throw();
        $sandbox->git('config', 'user.name', 'Sandbox User')->throw();
        $sandbox->git('config', 'user.email', 'sandbox@example.test')->throw();
        $sandbox->git('config', 'commit.gpgsign', 'false')->throw();
        $sandbox->git('config', 'core.autocrlf', 'false')->throw();
        $sandbox->git('config', 'core.hooksPath', $root.'/nohooks')->throw();

        $sandbox->write('tracked.txt', "original\n");
        $sandbox->git('add', 'tracked.txt')->throw();
        $sandbox->git('commit', '-q', '-m', 'init')->throw();

        return $sandbox;
    }

    public static function runCommitMessageHook(?string $message): ProcessResult
    {
        $directory = self::temporaryDirectory();
        $file = $directory.'/COMMIT_EDITMSG';

        if ($message !== null) {
            file_put_contents($file, $message);
        }

        return self::execute(Repo::path('.husky/commit-msg'), [$file], Repo::root(), '', '');
    }

    public static function runCommitMessageHookWithoutArgument(): ProcessResult
    {
        return self::execute(Repo::path('.husky/commit-msg'), [], Repo::root(), '', '');
    }

    public static function scratch(): string
    {
        return self::temporaryDirectory();
    }

    public static function cleanup(): void
    {
        foreach (self::$roots as $root) {
            self::remove($root);
        }

        self::$roots = [];
    }

    public function write(string $relative, string $contents): void
    {
        $path = $this->repo.'/'.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    public function stage(string $relative, string $contents): void
    {
        $this->write($relative, $contents);
        $this->git('add', '--', $relative)->throw();
    }

    public function git(string ...$arguments): ProcessResult
    {
        return Process::path($this->repo)->env(['GIT_TERMINAL_PROMPT' => '0', ...self::unsetGitVariables()])->run(['git', ...$arguments]);
    }

    public function detachHead(): void
    {
        $this->git('checkout', '-q', '--detach')->throw();
    }

    public function runHook(string $hook, string $stdin = '', int $npmExit = 0, string $npmScript = ''): ProcessResult
    {
        return self::execute(Repo::path('.husky/'.$hook), [], $this->repo, $this->bin, $stdin, $this->log, $npmExit, $npmScript);
    }

    public function npmScriptThatAppends(string $relative, string $line): string
    {
        $script = $this->root.'/npm-script.sh';
        file_put_contents($script, "printf '%s\\n' '".$line."' >> '".$relative."'\n");

        return $script;
    }

    public function headSha(): string
    {
        return trim($this->git('rev-parse', 'HEAD')->throw()->output());
    }

    /**
     * @return list<string>
     */
    public function npmCalls(): array
    {
        if (! is_file($this->log)) {
            return [];
        }

        $lines = file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? [] : $lines;
    }

    /**
     * @return list<string>
     */
    public function status(): array
    {
        $output = rtrim($this->git('status', '--porcelain')->throw()->output(), "\n");

        return $output === '' ? [] : explode("\n", $output);
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function execute(string $hook, array $arguments, string $cwd, string $prependPath, string $stdin, string $npmLog = '', int $npmExit = 0, string $npmScript = ''): ProcessResult
    {
        $path = self::searchPath($prependPath);
        $environment = ['PATH' => $path, 'NPM_LOG' => $npmLog, 'NPM_EXIT' => (string) $npmExit, 'NPM_SCRIPT' => $npmScript, ...self::unsetGitVariables()];

        if (PHP_OS_FAMILY === 'Windows') {
            $environment['Path'] = $path;
        }

        return Process::path($cwd)
            ->env($environment)
            ->input($stdin)
            ->timeout(120)
            ->run([Shell::sh(), '-e', $hook, ...$arguments]);
    }

    /**
     * @return array<string, false>
     */
    private static function unsetGitVariables(): array
    {
        return array_fill_keys(self::INHERITED_GIT_VARIABLES, false);
    }

    private static function searchPath(string $prepend): string
    {
        $current = getenv('PATH');

        if ($current === false) {
            $current = getenv('Path');
        }

        $current = $current === false ? '' : $current;
        $entries = array_values(array_filter([$prepend, Shell::toolDirectory(), $current], static fn (string $entry): bool => $entry !== ''));

        return implode(PATH_SEPARATOR, $entries);
    }

    private static function temporaryDirectory(): string
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()).'/abp-tooling-'.bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        self::$roots[] = $directory;

        return $directory;
    }

    private static function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = iterator_to_array(
            Finder::create()->files()->in($directory)->ignoreDotFiles(false)->ignoreVCS(false)->getIterator(),
            false,
        );

        foreach ($files as $file) {
            chmod($file->getPathname(), 0666);
            unlink($file->getPathname());
        }

        File::deleteDirectory($directory);
    }
}
