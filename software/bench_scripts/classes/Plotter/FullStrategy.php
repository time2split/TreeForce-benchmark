<?php
namespace Plotter;

final class FullStrategy extends AbstractFullStrategy
{

    private const toPlot = [
        'dir.elements' => 'summary',
        'answers' => 'total',
        'summary.create' => 'r',
        'rewriting.rules.apply' => 'r',
        'rewriting.total' => 'r',
        'rewritings.generation' => 'r',
        'threads.time|stats.db.time' => 'r',
        'partitions' => 'total',
        'partitions.used' => 'total',
        'partitions.hasAnswer' => 'total',
        'queries' => 'total',
        'error.timeout' => 'value',
//         'rules' => 'queries.cleaned.total',
        'filter.prefix' => 'total'
    ];

    private const stackedMeasuresToPlot = [
        [
            4 => 'rewriting.total'
        ],
        [
            5 => 'rewritings.generation'
        ],
        [
            6 => 'time'
        ]
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setToPlot(self::toPlot);
        $this->setStackedMeasuresToPlot(self::stackedMeasuresToPlot);
    }

    public function getID(): string
    {
        return 'group_query';
    }

    public function groupTests(array $testGroups): array
    {
        $queries = \array_unique(\array_map(fn ($p) => \basename($p), $testGroups));
        $dirs = \array_unique(\array_map(fn ($p) => \dirname($p), $testGroups));
        $groups = \array_map(function ($p) {
            $dirName = \basename($p);
            $elements = \Help\Plotter::extractDirNameElements($dirName);
            return \Help\Strings::append('.', $elements['group'], $elements['full_partition']);
        }, $dirs);
        $groups = \array_unique($groups, SORT_REGULAR);
        \natcasesort($groups);

        foreach ($groups as $group) {
            $regex = "#^\[$group(?:\..+)?\]#U";
            $gdirs = \array_filter($dirs, fn ($d) => \preg_match($regex, \basename($d)));
            $gscores = \array_map(fn ($d) => $this->sortScore(\basename($d)), $gdirs);
            $gdirs = \array_map(null, $gscores, $gdirs);

            \usort($gdirs, function ($a, $b) {

                if ($a[0] !== $b[0])
                    return $a[0] - $b[0];

                return \strnatcasecmp($a[1], $b[1]);
            });
            $gdirs = \array_column($gdirs, 1);

            foreach ($queries as $query) {
                $dd = \array_map(fn ($p) => "$p/$query", $gdirs);
                $ret["{$group}_$query"] = \array_values($dd);
            }
        }
        return $ret;
    }
}