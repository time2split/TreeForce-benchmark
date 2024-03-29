<?php
if (! function_exists('array_is_list')) {

    function array_is_list(array $array)
    {
        return $array === [] || \array_keys($array) === \range(0, \count($array) - 1);
    }
}

if (! function_exists('str_starts_with')) {

    function str_starts_with(string $haystack, string $needle)
    {
        return $needle === '' || \strncmp($haystack, $needle, \strlen($needle)) === 0;
    }
}

if (! function_exists('str_ends_with')) {

    function str_ends_with(string $haystack, string $needle): bool
    {
        $needle_len = strlen($needle);
        return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, - $needle_len));
    }
}

function ensureArray($element): array
{
    if (\is_array($element))
        return $element;

    return [
        $element
    ];
}

function error(string ...$params)
{
    foreach ($params as $p)
        fwrite(STDERR, implode('', $params));
}

function error_dump(...$params)
{
    foreach ($params as $p)
        fwrite(STDERR, print_r($p, true) . "\n");
}

function error_dump_exit(...$params)
{
    error_dump(...$params);
    exit();
}

function is_array_list($array): bool
{
    return \is_array($array) && \array_is_list($array);
}

function str_format(string $s, array $vars): string
{
    return \str_replace(\array_map(fn ($k) => "%$k", \array_keys($vars)), \array_values($vars), $s);
}

function srange($min, $max): string
{
    if ($min === $max)
        return "$min";

    return "$min,$max";
}

function rrmdir(string $dir, bool $rmRoot = true)
{
    $paths = new \RecursiveIteratorIterator( //
    new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), //
    \RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($paths as $pathInfo) {
        $p = $pathInfo->getPathName();

        if ($pathInfo->isFile() || $pathInfo->isLink())
            \unlink($p);
        else
            \rmdir($p);
    }
    if ($rmRoot)
        \rmdir($dir);
}

function printPHPFile(string $path, $data, bool $compact = false)
{
    $s = var_export($data, true);

    if ($compact)
        $s = \preg_replace('#\s#', '', $s);

    return \file_put_contents($path, "<?php return $s;");
}

function &wdStack(): array
{
    static $ret = [];
    return $ret;
}

function wdPush(string $path): void
{
    $stack = &wdStack();
    \array_push($stack, \getcwd());

    if (! \chdir($path))
        throw new \Exception("Cannot chdir to $path");
}

function wdPop(): void
{
    $stack = &wdStack();

    if (empty($stack))
        throw new \Exception("WD stack is empty");

    \chdir(\array_pop($stack));
}

function wdOp(string $workingDir, callable $exec)
{
    $wd = \getcwd();

    if (! \is_dir($workingDir))
        throw new \Exception("Cannot chdir to $workingDir: not a directory");

    \chdir($workingDir);
    $ret = $exec();
    \chdir($wd);
    return $ret;
}

function scandirNoPoints(string $path = '.', bool $getPath = false)
{
    $ret = \array_filter(\scandir($path), fn ($f) => $f[0] !== '.');
    \natcasesort($ret);

    if ($getPath)
        $ret = \array_map(fn ($v) => "$path/$v", $ret);

    return $ret;
}

function in_range($val, $min, $max)
{
    return $min <= $val && $val <= $max;
}

function parseArgv(array $argv): array
{
    return parseArgvShift($argv);
}

function parseArgvShift(array &$argv, string $endArg = ''): array
{
    $ret = [];
    while (null !== ($arg = \array_shift($argv))) {

        if ($arg === $endArg)
            break;
        if (str_starts_with($arg, '+-')) {
            $val = [
                true,
                false
            ];
            $name = \substr($arg, 2);
        } elseif (str_starts_with($arg, '-+')) {
            $val = [
                false,
                true
            ];
            $name = \substr($arg, 2);
        } elseif ($arg[0] === '+' || $arg[0] === '-') {
            $sign = $arg[0];
            $arg = \substr($arg, 1);
            list ($name, $val) = parseArgKeyValue($argv, $arg);

            if (\is_int($name)) {
                $name = $val;
                $val = ($sign === '+');
            }
        } else {
            list ($name, $val) = parseArgKeyValue($argv, $arg);
        }

        if (\is_int($name))
            $ret[] = $val;
        else
            $ret[$name] = $val;
    }
    return $ret;
}

function parseArgKeyValue(array &$argv, string $currentArg): array
{
    if (false !== \strpos($currentArg, '=')) {
        list ($name, $val) = \explode('=', $currentArg, 2);
        return [
            $name,
            \parseArgValue($val)
        ];
    } elseif ($currentArg[\strlen($currentArg) - 1] === ':') {
        return [
            \substr($currentArg, 0, - 1),
            parseArgValue(\array_shift($argv))
        ];
    } else
        return [
            0,
            parseArgValue($currentArg)
        ];
}

function parseArgValue(string $val)
{
    if (\preg_match('#^\s*\[(.*)\]\s*$#', $val, $matches))
        return \array_map('trim', \preg_split('#\W+#', $matches[1]));

    return $val;
}

function argPrefixed(array $args, string $prefix)
{
    $ret = [];

    foreach ($args as $arg => $v) {

        if (0 !== \strpos($arg, $prefix))
            continue;

        $ret[\substr($arg, \strlen($prefix))] = $v;
    }
    return $ret;
}

function argShift(array &$args, string $key, $default = null)
{
    if (count($args) == 0)
        return $default;

    if (\array_key_exists($key, $args)) {
        $v = $args[$key];
        unset($args[$key]);
    } else {
        $keys = \array_values(\array_filter(\array_keys($args), 'is_int'));

        if (empty($keys))
            return $default;

        $v = $args[$keys[0]];
        unset($args[$keys[0]]);
    }
    return $v;
}

function array_map_merge(callable $callback, array $array): array
{
    return \array_merge(...\array_map($callback, $array));
}

function array_map_key(?callable $callback, array $array): array
{
    return \array_combine(\array_map($callback, \array_keys($array)), $array);
}

function &array_follow(array &$array, array $path, $default = null)
{
    $p = &$array;

    for (;;) {
        $k = \array_shift($path);

        if (! \array_key_exists($k, $p))
            return $default;

        $p = &$p[$k];

        if (! is_array($p)) {

            if (! empty($path))
                return $default;
        } elseif (empty($path))
            return $p;
    }
}

function &array_ufollow(array &$array, array $path, $default = null, $validKey = null)
{
    if (null === $validKey)
        $validKey = function ($k, $array) {
            if (! \array_key_exists($k, $array))
                return false;

            return $k;
        };

    $array = &$array;

    for (;;) {
        $k = \array_shift($path);
        $k = $validKey($k, $array);

        if (false === $k)
            return $default;

        $array = &$array[$k];

        if (! is_array($array)) {

            if (! empty($path))
                return $default;
        } elseif (empty($path))
            return $array;
    }
}

function array_kdelete_get(array &$array, $key, $default = null)
{
    if (! \array_key_exists($key, $array))
        return $default;

    $ret = $array[$key];
    unset($array[$key]);
    return $ret;
}

function array_delete(array &$array, ...$vals): bool
{
    $ret = true;

    foreach ($vals as $val) {
        $k = \array_search($val, $array);

        if (false === $k)
            $ret = false;
        else
            unset($array[$k]);
    }
    return $ret;
}

function array_delete_branches(array &$array, array $branches): bool
{
    $ret = true;

    foreach ($branches as $branch)
        $ret = \array_delete_branch($array, $branch) && $ret;

    return $ret;
}

function array_delete_branch(array &$array, array $branch): bool
{
    $def = (object) [];
    $p = \array_pop($branch);
    $a = &\array_follow($array, $branch, $def);

    if ($a === $def)
        return false;

    do {
        unset($a[$p]);

        if (\count($a) > 0) {
            break;
        }
        $p = \array_pop($branch);
        $a = &\array_follow($array, $branch);
    } while (null !== $p);

    return true;
}

function array_partition(array $array, callable $filter, int $mode = 0): array
{
    $a = \array_filter($array, $filter, $mode);
    $b = \array_diff_key($array, $a);
    return [
        $a,
        $b
    ];
}

function array_filter_shift(array &$array, ?callable $filter = null, int $mode = 0): array
{
    $drop = [];
    $ret = [];

    if ($mode === 0)
        $fmakeParams = fn ($k, $v) => [
            $v
        ];
    elseif ($mode === ARRAY_FILTER_USE_KEY)
        $fmakeParams = fn ($k, $v) => (array) $k;
    elseif ($mode === ARRAY_FILTER_USE_BOTH)
        $fmakeParams = fn ($k, $v) => [
            $k,
            $v
        ];
    else
        throw new \Exception("Invalid mode $mode");

    foreach ($array as $k => $v) {
        $valid = $filter(...$fmakeParams($k, $v));

        if ($valid) {
            $drop[] = $k;
            $ret[$k] = $v;
        }
    }
    foreach ($drop as $d)
        unset($array[$d]);

    return $ret;
}

function array_walk_branches(array &$data, callable $walk, ?callable $fdown = null): void
{
    $ret = [];

    $toProcess = [
        [
            [],
            &$data
        ]
    ];
    if (null === $fdown)
        $fdown = fn () => true;

    while (! empty($toProcess)) {
        $nextToProcess = [];

        foreach ($toProcess as $tp) {
            $path = $tp[0];
            $array = &$tp[1];

            foreach ($array as $k => &$val) {
                $path[] = $k;

                if (\is_array($val) && ! empty($val)) {

                    if ($fdown($path, $val))
                        $nextToProcess[] = [
                            $path,
                            &$val
                        ];
                } else
                    $walk($path, $val);

                \array_pop($path);
            }
        }
        $toProcess = $nextToProcess;
    }
}

function array_delete_branches_end(array &$array, array $branches, $delVal = null): void
{
    foreach ($branches as $branch)
        \array_delete_branch_end($array, $branch, $delVal);
}

function array_delete_branch_end(array &$array, array $branch, $delVal = null): void
{
    $a = &\array_follow($array, $branch);
    $a = $delVal;
}

function array_walk_depth(array &$data, callable $walk): void
{
    $ret = [];

    $toProcess = [
        &$data
    ];

    while (! empty($toProcess)) {
        $nextToProcess = [];

        foreach ($toProcess as &$item) {
            $walk($item);

            if (\is_array($item))
                foreach ($item as $k => &$val)
                    $nextToProcess[] = &$val;
        }
        $toProcess = $nextToProcess;
    }
}

function array_is_almost_list(array $array)
{
    $notInt = \array_filter(\array_keys($array), fn ($k) => ! \is_int($k));
    return empty($notInt);
}

function array_reindex_list(array &$array)
{
    if (! \array_is_almost_list($array))
        return;

    $array = \array_values($array);
}

function array_reindex_lists_recursive(array &$array)
{
    \array_walk_depth($array, function (&$val) {
        if (\is_array($val))
            \array_reindex_list($val);
    });
}

function array_depth(array $data): int
{
    $ret = 0;
    array_walk_branches($data, function ($path) use (&$ret) {
        $ret = \max($ret, \count($path));
    });
    return $ret;
}

function array_nb_branches(array $data): int
{
    $ret = 0;
    array_walk_branches($data, function () use (&$ret) {
        $ret ++;
    });
    return $ret;
}

function array_branches(array $data): array
{
    $ret = [];
    array_walk_branches($data, function ($path) use (&$ret) {
        $ret[] = $path;
    });
    return $ret;
}

function mapArgKey_replace($search, $replace, ?callable $onCondition = null): callable
{
    return fn ($k) => ($onCondition ? $onCondition($k) : true) ? //
    \str_replace($search, $replace, $k) : //
    $k;
}

function mapArgKey_default(?callable $onCondition = null): callable
{
    return \mapArgKey_replace('.', '_', fn ($k) => ! \is_int($k) && ($onCondition ? $onCondition($k) : true));
}

function updateArray(array $args, array &$array, ?callable $onUnexists = null, ?callable $mapKey = null)
{
    if (null === $mapKey)
        $mapKey = \mapArgKey_default();

    foreach ($args as $k => $v) {
        $k = $mapKey($k);

        if (! \array_key_exists($k, $array)) {

            if ($onUnexists === null)
                throw new \Exception("The key '$key' does not exists in the array: " . implode(',', \array_kets($array)));
            else
                $onUnexists($array, $k, $v);
        } else
            $array[$k] = $v;
    }
}

function updateArray_getRemains(array $args, array &$array, ?callable $mapKey = null): array
{
    $remains = [];
    $fstore = function ($array, $k, $v) use (&$remains): void {
        $remains[$k] = $v;
    };

    updateArray($args, $array, $fstore, $mapKey);
    return $remains;
}

function updateObject(array $args, object &$obj, string $k_prefix = '')
{
    foreach ($args as $k => $v) {
        $k = \str_replace('.', '_', $k);
        $obj->{"$k_prefix$k"} = $v;
    }
}

function getVal(array $a, $default, string ...$key)
{
    $p = $a;

    foreach ($key as $k) {
        if (! isset($a[$k])) {
            $path = implode(' ', $key);
            fputs(STDERR, "'$path' is not a valid array path\n");
            return $default;
        }
        $p = $a[$k];
    }
    return $p;
}

function get_ob(callable $f)
{
    ob_start();
    $f();
    return ob_get_clean();
}

function include_script(string $filename, array $argv)
{
    if (is_file($filename))
        return include $filename;

    return false;
}

function isStream($var)
{
    return \is_resource($var) && \get_resource_type($var) === "stream";
}

function lastLineStreamBuffer(int $nbLines, &$lineBuffer, callable $lineFilter = null): callable
{
    if ($lineFilter === null)
        $lineFilter = fn ($l) => true;

    return function (?string $s) use (&$lineBuffer, $nbLines, $lineFilter) {

        if (! isset($lineBuffer[0]))
            $lineBuffer[0] = '';

        if ($s === null)
            $s = "\n";

        $pos = \strpos($s, "\n");

        // No end of line
        if ($pos === false) {
            $lineBuffer[0] .= $s;
        } else {
            $lastIsNL = $s[\strlen($s) - 1] == "\n";

            $slines = \explode("\n", $s);
            $lineBuffer[] = $lineBuffer[0] . $slines[0];
            unset($lineBuffer[0]);
            unset($slines[0]);

            foreach ($slines as $s)
                $lineBuffer[] = $s;

            $lineBuffer = \array_filter($lineBuffer, $lineFilter);

            $bf = $lastIsNL ? '' : array_pop($lineBuffer);

            $size = \count($lineBuffer);

            if ($size > $nbLines)
                $lineBuffer = \array_slice($lineBuffer, - $nbLines);

            $lineBuffer = \array_merge([
                $bf
            ], $lineBuffer);
        }
    };
}

function simpleExec(string $cmd, &$output, &$err, ?string $input = null, array $params = []): int
{
    $outErr = [
        1 => &$output,
        2 => &$err
    ];
    $descriptors = [
        [
            'pipe',
            'r'
        ],
        [
            'pipe',
            'w'
        ],
        [
            'pipe',
            'w'
        ]
    ];
    \extract($params + [
        'blockSize' => 1024,
        'usleep' => 10
    ]);

    // ===========================================

    $proc = \proc_open($cmd, $descriptors, $pipes);

    foreach ($outErr as $k => &$out) {
        $pipe = $pipes[$k];
        \stream_set_blocking($pipe, false);

        if (\is_callable($out))
            $callback = $out;
        else if (isStream($out))
            $callback = function ($streamData) use (&$out) {
                \fwrite($out, $streamData);
            };
        else
            $callback = function ($streamData) use (&$out) {
                $out .= $streamData;
            };

        $callbacks[$k] = $callback;
    }

    if (null !== $input)
        \fwrite($pipes[0], $input);

    \fclose($pipes[0]);
    unset($pipes[0]);

    while (! empty($pipes)) {

        foreach ($pipes as $k => &$pipe) {
            $streamData = \stream_get_contents($pipe, $blockSize);

            if (0 < strlen($streamData))
                $callbacks[$k]($streamData);

            if (\feof($pipe)) {
                $callbacks[$k](null);
                unset($pipes[$k]);
            }
        }
        \usleep($usleep);
    }
    return \proc_close($proc);
}

function get_include_contents(string $filename, array $variables, string $uniqueVar = '')
{
    if (is_file($filename)) {

        if (empty($uniqueVar))
            \extract($variables);
        else
            $$uniqueVar = $variables;

        ob_start();
        include $filename;
        return ob_get_clean();
    }
    return false;
}

function getSoftwareBinBasePath(): string
{
    return getBenchmarkBasePath() . '/software/bin';
}

function getPHPScriptsBasePath(): string
{
    return getBenchmarkBasePath() . '/software/bench_scripts';
}

function getBenchmarkBasePath(): string
{
    static $p = null;
    return $p ?? ($p = \realpath(getcwd()));
    // return \realpath(__DIR__ . "/../../..");
}
getBenchmarkBasePath();

